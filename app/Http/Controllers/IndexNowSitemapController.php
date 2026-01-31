<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Ymigval\LaravelIndexnow\Facade\IndexNow;

class IndexNowSitemapController extends Controller
{
    /**
     * POST /api/indexnow/submit-sitemap
     * Body (opcjonalnie): { "sitemap": "https://sanipro.pl/sitemap.xml" }
     */
    public function submitFromSitemap(Request $request)
    {
        $sitemapUrl = $request->input('sitemap', 'https://sanipro.pl/sitemap.xml');

        // Bezpiecznik: nie pozwól submitować cudzych domen
        $allowedHost = 'sanipro.pl';
        $host = parse_url($sitemapUrl, PHP_URL_HOST);
        if (!is_string($host) || !Str::endsWith($host, $allowedHost)) {
            return response()->json([
                'ok' => false,
                'error' => 'Dozwolone są tylko sitemap-y z sanipro.pl',
            ], 422);
        }

        try {
            // 1) Zbierz URL-e z sitemapindex + urlset (rekurencyjnie)
            $urls = $this->collectUrlsFromSitemap($sitemapUrl);

            // 2) Filtr hosta + unique
            $urls = array_values(array_unique(array_filter($urls, function ($u) use ($allowedHost) {
                $h = parse_url($u, PHP_URL_HOST);
                return is_string($h) && Str::endsWith($h, $allowedHost);
            })));

            if (empty($urls)) {
                return response()->json([
                    'ok' => true,
                    'sitemap' => $sitemapUrl,
                    'submitted_batches' => 0,
                    'submitted_urls' => 0,
                    'message' => 'Brak URL-i do wysłania (sitemap pusta albo nie udało się sparsować).',
                ]);
            }

            // 3) IndexNow: max 10k URL-i na request
            $batchSize = 10000;
            $batches = array_chunk($urls, $batchSize);

            $submitted = 0;
            foreach ($batches as $batch) {
                IndexNow::submit($batch);
                $submitted += count($batch);
            }

            return response()->json([
                'ok' => true,
                'sitemap' => $sitemapUrl,
                'submitted_batches' => count($batches),
                'submitted_urls' => $submitted,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rekurencyjnie zwraca listę <loc> URL-i:
     * - jeśli sitemapindex -> pobiera kolejne sitemap-y
     * - jeśli urlset -> zwraca URL-e stron
     */
    private function collectUrlsFromSitemap(string $sitemapUrl): array
    {
        $xml = $this->fetchSitemapXml($sitemapUrl);

        $sxml = @simplexml_load_string($xml);
        if ($sxml === false) {
            throw new \RuntimeException("Nie udało się sparsować XML: {$sitemapUrl}");
        }

        // Namespace-safe parsing (Twoja sitemap ma xmlns="http://www.sitemaps.org/schemas/sitemap/0.9")
        $ns = $sxml->getNamespaces(true);
        $defaultNs = $ns[''] ?? null;

        if ($defaultNs) {
            $sxml->registerXPathNamespace('sm', $defaultNs);
        }

        $rootName = $sxml->getName();
        $result = [];

        if ($rootName === 'sitemapindex') {
            $locNodes = $defaultNs
                ? ($sxml->xpath('//sm:sitemap/sm:loc') ?: [])
                : ($sxml->xpath('//sitemap/loc') ?: []);

            foreach ($locNodes as $locNode) {
                $loc = trim((string)$locNode);
                if ($loc !== '') {
                    $result = array_merge($result, $this->collectUrlsFromSitemap($loc));
                }
            }
            return $result;
        }

        if ($rootName === 'urlset') {
            $locNodes = $defaultNs
                ? ($sxml->xpath('//sm:url/sm:loc') ?: [])
                : ($sxml->xpath('//url/loc') ?: []);

            foreach ($locNodes as $locNode) {
                $loc = trim((string)$locNode);
                if ($loc !== '') {
                    $result[] = $loc;
                }
            }
            return $result;
        }

        throw new \RuntimeException("Nieobsługiwany typ sitemap ({$rootName}): {$sitemapUrl}");
    }

    /**
     * Pobiera XML (wspiera .xml.gz).
     */
    private function fetchSitemapXml(string $url): string
    {
        $resp = Http::timeout(30)
            ->withHeaders([
                'Accept' => 'application/xml,text/xml,*/*',
                'User-Agent' => 'Sanipro-IndexNow-Bot/1.0',
            ])
            ->get($url);

        if (!$resp->successful()) {
            throw new \RuntimeException("Nie udało się pobrać sitemap ({$resp->status()}): {$url}");
        }

        $body = $resp->body();

        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $isGzByExt = is_string($path) && Str::endsWith($path, '.gz');
        $contentType = (string)($resp->header('Content-Type') ?? '');
        $isGzByHeader = Str::contains(Str::lower($contentType), 'gzip');

        if ($isGzByExt || $isGzByHeader) {
            $decoded = @gzdecode($body);
            if ($decoded === false) {
                throw new \RuntimeException("Nie udało się rozpakować gzip: {$url}");
            }
            return $decoded;
        }

        return $body;
    }
}
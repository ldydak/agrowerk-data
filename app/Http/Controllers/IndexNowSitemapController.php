<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Ymigval\LaravelIndexnow\Facade\IndexNow;

class IndexNowSitemapController extends Controller
{
    private string $allowedHost = 'sanipro.pl';

    // Domyślne ustawienia
    private string $defaultSitemap = 'https://sanipro.pl/sitemap.xml';
    private int $defaultDays = 7; // domyślnie 7 dni (zalecane)

    public function show()
    {
        return view('settings.indexnow', [
            'sitemapUrl' => config('services.sanipro.sitemap', $this->defaultSitemap),
            'days' => (int)config('services.sanipro.indexnow_days', $this->defaultDays),
        ]);
    }

    /**
     * WEB: GET /settings/indexnow/submit-sitemap
     * Uruchamia submit i wraca na poprzednią stronę z flash message.
     */
    public function submitFromSitemapWeb(Request $request)
    {
        $sitemapUrl = config('services.sanipro.sitemap', $this->defaultSitemap);
        $days = (int)config('services.sanipro.indexnow_days', $this->defaultDays);

        try {
            $result = $this->submitRecentUrls($sitemapUrl, $days);

            return back()->with([
                'status_ok' => true,
                'status_message' => "IndexNow: wysłano {$result['submitted_urls']} URL-i (batchy: {$result['submitted_batches']}) z ostatnich {$days} dni.",
            ]);
        } catch (\Throwable $e) {
            return back()->with([
                'status_ok' => false,
                'status_message' => "IndexNow błąd: " . $e->getMessage(),
            ]);
        }
    }

    /**
     * API: POST /api/indexnow/submit-sitemap
     * Body (opcjonalnie):
     * - sitemap: url (default https://sanipro.pl/sitemap.xml)
     * - days: int (default 7)
     */
    public function submitFromSitemapApi(Request $request)
    {
        $sitemapUrl = $request->input('sitemap', config('services.sanipro.sitemap', $this->defaultSitemap));
        $days = (int)$request->input('days', config('services.sanipro.indexnow_days', $this->defaultDays));

        try {
            $result = $this->submitRecentUrls($sitemapUrl, $days);

            return response()->json([
                'ok' => true,
                'sitemap' => $sitemapUrl,
                'days' => $days,
                'submitted_batches' => $result['submitted_batches'],
                'submitted_urls' => $result['submitted_urls'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Wspólna logika: wyślij tylko URL-e z lastmod >= (now - days)
     */
    private function submitRecentUrls(string $sitemapUrl, int $days): array
    {
        $days = max(1, min($days, 365));
        $cutoff = Carbon::now()->subDays($days);

        // Bezpiecznik: nie submitujemy cudzych domen
        $host = parse_url($sitemapUrl, PHP_URL_HOST);
        if (!is_string($host) || !Str::endsWith($host, $this->allowedHost)) {
            throw new \RuntimeException('Dozwolone są tylko sitemap-y z sanipro.pl');
        }

        // 1) Zbierz URL-e recent z sitemapindex/urlset
        $urls = $this->collectUrlsFromSitemapRecent($sitemapUrl, $cutoff);

        // 2) Filtr hosta + unique
        $urls = array_values(array_unique(array_filter($urls, function ($u) {
            $h = parse_url($u, PHP_URL_HOST);
            return is_string($h) && Str::endsWith($h, $this->allowedHost);
        })));

        if (empty($urls)) {
            return [
                'submitted_batches' => 0,
                'submitted_urls' => 0,
            ];
        }

        // 3) IndexNow: max 10k URL-i / request
        $batchSize = 10000;
        $batches = array_chunk($urls, $batchSize);

        $submitted = 0;
        foreach ($batches as $batch) {
            IndexNow::submit($batch);
            $submitted += count($batch);
        }

        return [
            'submitted_batches' => count($batches),
            'submitted_urls' => $submitted,
        ];
    }

    /**
     * RECENT: sitemapindex -> rekurencyjnie; urlset -> <loc> tylko gdy <lastmod> >= cutoff
     * Jeśli <lastmod> brak / nieparsowalny => pomijamy (żeby "recent" było prawdziwe).
     */
    private function collectUrlsFromSitemapRecent(string $sitemapUrl, Carbon $cutoff): array
    {
        [$sxml, $defaultNs] = $this->loadSitemapXml($sitemapUrl);
        $rootName = $sxml->getName();
        $result = [];

        if ($rootName === 'sitemapindex') {
            $locNodes = $defaultNs
                ? ($sxml->xpath('//sm:sitemap/sm:loc') ?: [])
                : ($sxml->xpath('//sitemap/loc') ?: []);

            foreach ($locNodes as $locNode) {
                $loc = trim((string)$locNode);
                if ($loc !== '') {
                    $result = array_merge($result, $this->collectUrlsFromSitemapRecent($loc, $cutoff));
                }
            }
            return $result;
        }

        if ($rootName === 'urlset') {
            $urlNodes = $defaultNs
                ? ($sxml->xpath('//sm:url') ?: [])
                : ($sxml->xpath('//url') ?: []);

            foreach ($urlNodes as $urlNode) {
                $loc = $defaultNs
                    ? trim((string)($urlNode->children($defaultNs)->loc ?? ''))
                    : trim((string)($urlNode->loc ?? ''));

                if ($loc === '') {
                    continue;
                }

                $lastmodStr = $defaultNs
                    ? trim((string)($urlNode->children($defaultNs)->lastmod ?? ''))
                    : trim((string)($urlNode->lastmod ?? ''));

                if ($lastmodStr === '') {
                    continue;
                }

                try {
                    $lastmod = Carbon::parse($lastmodStr);
                } catch (\Throwable $e) {
                    continue;
                }

                if ($lastmod->greaterThanOrEqualTo($cutoff)) {
                    $result[] = $loc;
                }
            }
            return $result;
        }

        throw new \RuntimeException("Nieobsługiwany typ sitemap ({$rootName}): {$sitemapUrl}");
    }

    /**
     * Ładuje sitemap XML (obsługa .xml.gz) + rejestruje namespace sitemaps.org (sm:)
     * Zwraca: [SimpleXMLElement $sxml, ?string $defaultNs]
     */
    private function loadSitemapXml(string $sitemapUrl): array
    {
        $xml = $this->fetchSitemapXml($sitemapUrl);

        $sxml = @simplexml_load_string($xml);
        if ($sxml === false) {
            throw new \RuntimeException("Nie udało się sparsować XML: {$sitemapUrl}");
        }

        $ns = $sxml->getNamespaces(true);
        $defaultNs = $ns[''] ?? null;

        if ($defaultNs) {
            $sxml->registerXPathNamespace('sm', $defaultNs);
        }

        return [$sxml, $defaultNs];
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

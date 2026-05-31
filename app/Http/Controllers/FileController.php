<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
class FileController extends Controller
{
    public function downloadFile($hash){

        // dekodujemy
        $hash = urldecode($hash);

        // Jeśli nie przeszedł hCaptcha dla tego hash → pokaż widok CAPTCHA
        if (!session("hcaptcha_passed_{$hash}")) {
            return view('captcha_required', compact('hash'));
        }
        // Jeśli CAPTCHA zaliczona → kontynuujemy


        // odkoduj link do pliku na hygi
        $fileOryginalUrl = decrypt($hash);

        $proxyAddress= env('ROTATING_PROXY_HOST');
        $proxyPort= env('ROTATING_PROXY_PORT');
        $proxyUsername= env('ROTATING_PROXY_LOGIN');
        $proxyPassword= env('ROTATING_PROXY_PASSWORD');

        $context['https'] = array(
            'proxy' => "https://".$proxyUsername.":".$proxyPassword."@".$proxyAddress.":".$proxyPort,
            'request_fulluri' => true
        );

        $cxContext = stream_context_create($context);
        $downloadedFile = @file_get_contents($fileOryginalUrl, false, $cxContext);

        if ($downloadedFile === false) {
            // Logujem błąd do logów, np. z zachowaniem URL
            Log::error("Błąd pobierania pliku karty z URL:" . $fileOryginalUrl);

            echo "Niestety, nie udało się pobrać pliku. Spróbuj ponownie później.";
        } else {    
            // nazwa losowa dla pliku
            $fileName = uniqid(rand(), true) . '.pdf';
            // wgraj na media.agrowerk.pl/pliki-temp/
            $folder = 'pliki-temp/';
            Storage::disk('media_sftp')->put($folder . $fileName, $downloadedFile, 'r+');

            // przekieruj na URL z media.agrowerk.pl
            return redirect(env('MEDIA_SFTP_FILES_PRE_URL').$fileName);
        }
    }

    public function verifyCaptcha(Request $request, $hash)
    {
        $token = $request->get('h-captcha-response');

        if (!$token) {
            return back()->with('error', 'hCaptcha nie została wypełniona. Spróbuj ponownie. Jest to niezbędny krok, aby pobrać plik.');
        }

        // Weryfikacja po stronie hCaptcha
        $response = Http::asForm()->post('https://hcaptcha.com/siteverify', [
            'secret'   => env('HCAPTCHA_SECRET'),
            'response' => $token,
        ]);

        $result = $response->json();

        if (!($result['success'] ?? false)) {
            return back()->with('error', 'Weryfikacja hCaptcha nie powiodła się.');
        }

        // ZAPAMIĘTUJEMY, ŻE CAPTCHA DLA KONKRETNEGO HASH PRZESZŁA
        session(["hcaptcha_passed_{$hash}" => true]);

        // Redirect z powrotem do procesu pobierania
        return redirect()->route('download-file', ['hash' => $hash]);
    }
}

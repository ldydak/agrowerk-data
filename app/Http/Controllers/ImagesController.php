<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\SimpleExcel\SimpleExcelReader;
use Image;

class ImagesController extends Controller
{
    public function show()
    {
        return view('images.import');
    }

    private $importType;

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
            'imagesImportType' => 'required|in:skipExisted,updateAll'
        ]);

        $request->file('file')->move(public_path('import_temp'), $request->file('file')->getClientOriginalName());
        $uploadedImportFile = public_path('import_temp') . '/' . $request->file('file')->getClientOriginalName();
        $this->importType = $request->imagesImportType;

        try {
            // automatyczne wykrywanie separatora
            $firstLine = fgets(fopen($uploadedImportFile, 'r'));
            $delimiter = str_contains($firstLine, ';') ? ';' : (str_contains($firstLine, ',') ? ',' : "\t");

            // usuń ewentualny BOM
            $csvContent = file_get_contents($uploadedImportFile);
            $csvContent = preg_replace('/^\xEF\xBB\xBF/', '', $csvContent);
            file_put_contents($uploadedImportFile, $csvContent);

            // Ustal od którego wiersza ma się zaczynać import
            $startRow = 402; // tutaj zmień numer wiersza startowego
            $rowIndex = 0;

            $reader = SimpleExcelReader::create($uploadedImportFile)
                ->useDelimiter($delimiter);

            $reader->getRows()->each(function (array $rowProperties) use (&$rowIndex, $startRow) {
                $rowIndex++;

                // Pomijaj wiersze przed startem
                if ($rowIndex < $startRow) {
                    return;
                }

                $sku = $rowProperties['SKU'] ?? null;
                if (!$sku || !$this->productExist($sku)) {
                    return; // pomiń, jeśli brak SKU lub produktu
                }

                $product = DB::connection('mysql-sklep')->table('products')->where('sku', $sku)->first();
                $productID = $product->id;
                $productSlug = $product->slug;

                $baseImage = $rowProperties['base_image'] ?? null;
                $additionalImages = $rowProperties['additional_images'] ?? '';

                // Base image
                if ($baseImage) {
                    $this->mainImportImageFunction($baseImage, $productID, $sku, $productSlug, 0, 'base_image');
                }

                // Additional images
                $i = 0;
                foreach (explode(',', $additionalImages) as $additionalImage) {
                    $additionalImage = trim($additionalImage);
                    if (empty($additionalImage) || $additionalImage === '#') {
                        continue;
                    }
                    $i++;
                    $this->mainImportImageFunction($additionalImage, $productID, $sku, $productSlug, $i, 'additional_images');
                }
            });

            unlink($uploadedImportFile);

            return redirect()->back()->with('success', 'Zdjęcia wgrano pomyślnie na serwer media.schomann.pl');
        } catch (\Exception $error) {
            return $error->getMessage();
        }
    }

    public function mainImportImageFunction($imageUrl, $productID, $sku, $productSlug, $i, $zoneImageType)
    {
        $filesDB = DB::connection('mysql-sklep')->table('files');
        $entityFilesDB = DB::connection('mysql-sklep')->table('entity_files');

        $imageExtension = strtolower(pathinfo($imageUrl, PATHINFO_EXTENSION));

        // Nazwa finalna
        $imageName = sprintf('%s_%s_%d.%s', $sku, $productSlug, $i, $imageExtension === 'jpg' ? 'webp' : $imageExtension);

        // Pomijanie / aktualizacja
        if ($this->importType === 'skipExisted' && Storage::disk('media_sftp')->exists($imageName)) {
            return;
        }

        // Pobierz i wgraj
        $uploadInfo = $this->downloadAndUploadToFTP($imageUrl, $imageName);

        if (!$uploadInfo) {
            return;
        }

        $finalUrl = env('MEDIA_SFTP_IMAGES_PRE_URL') . $imageName;

        $fileID = $filesDB->insertGetId([
            'user_id'   => 1,
            'filename'  => $imageName,
            'disk'      => 'media_schomann',
            'path'      => 'produkty/' . $imageName,
            'extension' => pathinfo($imageName, PATHINFO_EXTENSION),
            'mime'      => $uploadInfo['mime'] ?? null,
            'size'      => $uploadInfo['size'] ?? null,
        ]);

        $entityFilesDB->insert([
            'file_id'      => $fileID,
            'entity_type'  => 'Modules\Product\Entities\Product',
            'entity_id'    => $productID,
            'zone'         => $zoneImageType,
        ]);
    }

    public function downloadAndUploadToFTP($imageUrl, $imageName)
    {
        // 1. Sprawdzenie istnienia pliku po stronie dostawcy
        if (!$this->checkFileExistOnSupplierPage($imageUrl)) {
            \Log::warning("Importer: dostawca zwrócił brak pliku", ['url' => $imageUrl]);
            return false;
        }

        // 2. Pobranie obrazu CURL-em
        $ch = curl_init($imageUrl);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (ImageImporter)',
            CURLOPT_TIMEOUT => 25,
        ]);

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($httpCode !== 200 || !$raw) {
            \Log::error("Importer: nie udało się pobrać obrazu", [
                'url' => $imageUrl,
                'http' => $httpCode,
                'bytes' => strlen($raw),
            ]);
            return false;
        }

        // 3. Walidacja — czy to obraz, a nie HTML
        if (strlen($raw) < 100 || str_starts_with($raw, '<')) {
            \Log::error("Importer: dostawca zwrócił nieobrazkowe dane", [
                'url' => $imageUrl,
                'sample' => substr($raw, 0, 300),
            ]);
            return false;
        }

        // 4. Próba utworzenia obiektu obrazu 
        try {
            $image = Image::make($raw)->encode('webp', 85);
        } catch (\Exception $e) {
            \Log::error("Importer: błąd konwersji do WEBP", [
                'url' => $imageUrl,
                'error' => $e->getMessage(),
            ]);
            return false;
        }

        // 5. Upload na SFTP
        Storage::disk('media_sftp')->put($imageName, $image);

        // 6. Metadane
        return [
            'mime' => 'image/webp',
            'size' => strlen($image),
        ];
    }


    public function productExist($sku)
    {
        return DB::connection('mysql-sklep')->table('products')->where('sku', $sku)->exists();
    }

    public function checkFileExistOnSupplierPage($imageUrl)
    {
        $headers = @get_headers($imageUrl);
        if (!$headers) return false;
        return str_contains($headers[0], '200');
    }

    public function wariantsImageImport(Request $request) {

    }
}

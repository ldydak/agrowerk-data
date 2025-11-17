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
        if ($this->importType === 'skipExisted' && Storage::disk('media_ftp')->exists($imageName)) {
            return;
        }

        // Pobierz i wgraj
        $uploadInfo = $this->downloadAndUploadToFTP($imageUrl, $imageName);

        if (!$uploadInfo) {
            return;
        }

        $finalUrl = env('MEDIA_FTP_IMAGES_PRE_URL') . $imageName;

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
        if (!$this->checkFileExistOnSupplierPage($imageUrl)) {
            return false;
        }

        $downloadedImage = @file_get_contents($imageUrl);
        if (!$downloadedImage) {
            return false;
        }

        // Kompresja i konwersja do webp
        $image = Image::make($downloadedImage)->encode('webp', 85);

        Storage::disk('media_ftp')->put($imageName, $image, 'r+');

        // MIME i size po uploadzie
        $mime = 'image/webp';
        $size = strlen($image); // w bajtach

        return [
            'mime' => $mime,
            'size' => $size,
        ];
    }

    public function productExist($sku)
    {
        return DB::connection('mysql-sklep')->table('products')->where('sku', $sku)->exists();
    }

    public function checkFileExistOnSupplierPage($imageUrl)
    {
        // STARA WERSJA
        // $headers = @get_headers($imageUrl);
        // if (!$headers) return false;
        // return str_contains($headers[0], '200');

        // NOWA
        $ch = curl_init($imageUrl);
        curl_setopt($ch, CURLOPT_NOBODY, true);  // <<< NIE pobiera treści
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode >= 200 && $httpCode < 300);
    }
}

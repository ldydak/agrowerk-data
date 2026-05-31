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
    private $importProductsOrVariants;

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
            'imagesImportType' => 'required|in:skipExisted,updateAll',
            'productsOrVariants' => 'required|in:products,variants'
        ]);


        $request->file('file')->move(public_path('import_temp'), $request->file('file')->getClientOriginalName());
        $uploadedImportFile = public_path('import_temp') . '/' . $request->file('file')->getClientOriginalName();
        $this->importType = $request->imagesImportType;
        $this->importProductsOrVariants = $request->input('productsOrVariants');

        try {
            // automatyczne wykrywanie separatora
            $firstLine = fgets(fopen($uploadedImportFile, 'r'));
            $delimiter = str_contains($firstLine, ';') ? ';' : (str_contains($firstLine, ',') ? ',' : "\t");

            // usuń ewentualny BOM
            $csvContent = file_get_contents($uploadedImportFile);
            $csvContent = preg_replace('/^\xEF\xBB\xBF/', '', $csvContent);
            file_put_contents($uploadedImportFile, $csvContent);

            // Ustal od którego wiersza ma się zaczynać import
            $startRow = 0; // tutaj zmień numer wiersza startowego
            $rowIndex = 0;

            $reader = SimpleExcelReader::create($uploadedImportFile)
                ->useDelimiter($delimiter);

            $reader->getRows()->each(function (array $rowProperties) use (&$rowIndex, $startRow) {
                $rowIndex++;

                // Pomijaj wiersze przed startem
                if ($rowIndex < $startRow) {
                    return;
                }

                // Jesli importujesz warianty, pomijaj produkty parenty z pliku importowego
                if($this->importProductsOrVariants == 'variants' && $rowProperties['product_type'] == 'parent_has_variants'){
                    return;
                } 

                // sprawdz czy istniej juz taki produkt, albo wariant
                $sku = $rowProperties['SKU'] ?? null;
                if (!$sku || !$this->productExist($sku, $this->importProductsOrVariants)) {
                    return; // pomiń, jeśli brak SKU lub produktu
                }
                if($this->importProductsOrVariants == 'products') {
                    // Produkt
                    $product = DB::connection('mysql-sklep')->table('products')->where('sku', $sku)->first();
                    $productSlug = $product->slug;
                } else {
                    // Wariant
                    $product = DB::connection('mysql-sklep')->table('product_variants')->where('sku', $sku)->first();
                    $productsController = new ProductsController;
                    $productSlug = $productsController->makeSlug($rowProperties['Name']);
                }
                $productID = $product->id;
                $baseImage = $rowProperties['base_image'] ?? null;
                $additionalImages = $rowProperties['additional_images'] ?? '';

                // Base image
                if ($baseImage) {
                    $this->mainImportImageFunction($baseImage, $productID, $sku, $productSlug, 0, 'base_image', $this->importProductsOrVariants);
                }

                // Additional images
                $i = 0;
                foreach (explode(',', $additionalImages) as $additionalImage) {
                    $additionalImage = trim($additionalImage);
                    if (empty($additionalImage) || $additionalImage === '#') {
                        continue;
                    }
                    $i++;
                    $this->mainImportImageFunction($additionalImage, $productID, $sku, $productSlug, $i, 'additional_images', $this->importProductsOrVariants);
                }
            });

            unlink($uploadedImportFile);

            return redirect()->back()->with('success', 'Zdjęcia wgrano pomyślnie na serwer media.agrowerk.pl');
        } catch (\Exception $error) {
            return $error->getMessage();
        }
    }

    public function mainImportImageFunction($imageUrl, $productID, $sku, $productSlug, $i, $zoneImageType, $productsOrVariants)
    {
        $filesDB = DB::connection('mysql-sklep')->table('files');
        $entityFilesDB = DB::connection('mysql-sklep')->table('entity_files');

        $imageExtension = strtolower(pathinfo($imageUrl, PATHINFO_EXTENSION));


        // Produkty czy warianty
        if($productsOrVariants == 'products'){
            // dodajesz zdjecia produktow
            // Zmienne
            $path_folder = 'produkty/';
            $entity_type = 'Modules\Product\Entities\Product';
            // Nazwa finalna pliku
            $imageName = sprintf('%s_%s_%d.%s', $sku, $productSlug, $i, $imageExtension === 'jpg' ? 'webp' : $imageExtension);

            $finalUrl = env('MEDIA_SFTP_IMAGES_PRE_URL') . 'produkty/' . $imageName;

             // Pomijanie / aktualizacja
            if ($this->importType === 'skipExisted' && Storage::disk('media_sftp')->exists($imageName)) {
                return;
            }
        } else {
            // dodajesz zdjecia wariantow
            // Zmienne
            $path_folder = 'produkty/warianty/';
            $entity_type = 'Modules\Product\Entities\ProductVariant';
            // Nazwa finalna pliku
            $imageName = sprintf('%s_wariant_%s_%d.%s', $sku, $productSlug, $i, $imageExtension === 'jpg' ? 'webp' : $imageExtension);

            $finalUrl = env('MEDIA_SFTP_IMAGES_PRE_URL') . 'produkty/warianty/' . $imageName;

            // Pommijanie / aktualizacja
            if ($this->importType === 'skipExisted' && Storage::disk('media_sftp')->exists('warianty/' . $imageName)) {
                return;
            }
        }

        // Pobierz i wgraj
        $uploadInfo = $this->downloadAndUploadToFTP($imageUrl, $imageName);

        if (!$uploadInfo) {
            return;
        }

        $fileID = $filesDB->insertGetId([
            'user_id'   => 1,
            'filename'  => $imageName,
            'disk'      => 'media_sanipro',
            'path'      =>  $path_folder . $imageName,
            'extension' => pathinfo($imageName, PATHINFO_EXTENSION),
            'mime'      => $uploadInfo['mime'] ?? null,
            'size'      => $uploadInfo['size'] ?? null,
        ]);

        $entityFilesDB->insert([
            'file_id'      => $fileID,
            'entity_type'  => $entity_type,
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
        // $folder = $this->importProductsOrVariants === 'variants' ? 'produkty/warianty/' : '';
        if ($this->importProductsOrVariants === 'variants') {
            $folder = 'produkty/warianty/';
        } elseif ($this->importProductsOrVariants === 'products') {
            $folder = 'produkty/';
        } elseif ($this->importProductsOrVariants === 'brands') {
            $folder = 'marki/';
        }
        Storage::disk('media_sftp')->put($folder . $imageName, $image);
        
        // 6. Metadane
        return [
            'mime' => 'image/webp',
            'size' => strlen($image),
        ];
    }


    public function productExist($sku, $productsOrVariants)
    {
        if($productsOrVariants == 'products') {
            // Produkty
            return DB::connection('mysql-sklep')->table('products')->where('sku', $sku)->exists();
        } else {
            // Warianty
            return DB::connection('mysql-sklep')->table('product_variants')->where('sku', $sku)->exists();
        }
    }
    

    public function checkFileExistOnSupplierPage($imageUrl)
    {
        $headers = @get_headers($imageUrl);
        if (!$headers) return false;
        return str_contains($headers[0], '200');
    }

    public function brandsImageImport(Request $request)
    {
        $this->importProductsOrVariants = 'brands';

        $request->file('file')->move(
            public_path('import_temp'),
            $request->file('file')->getClientOriginalName()
        );
        $uploadedImportFile = public_path('import_temp') . '/' . $request->file('file')->getClientOriginalName();

        try {
            // automatyczne wykrywanie separatora
            $firstLine = fgets(fopen($uploadedImportFile, 'r'));
            $delimiter = str_contains($firstLine, ';') ? ';' : (str_contains($firstLine, ',') ? ',' : "\t");

            // usuń ewentualny BOM
            $csvContent = file_get_contents($uploadedImportFile);
            $csvContent = preg_replace('/^\xEF\xBB\xBF/', '', $csvContent);
            file_put_contents($uploadedImportFile, $csvContent);

            // Ustal od którego wiersza ma się zaczynać import
            $startRow = 0;
            $rowIndex = 0;

            $reader = SimpleExcelReader::create($uploadedImportFile)
                ->useDelimiter($delimiter);

            $reader->getRows()->each(function (array $rowProperties) use (&$rowIndex, $startRow) {
                $rowIndex++;

                if ($rowIndex < $startRow) {
                    return;
                }

                $brandName = $rowProperties['Brand'] ?? null;
                $brandLogoOryginalUrl = $rowProperties['brand_logo'] ?? null;

                if (!$brandName || !$brandLogoOryginalUrl) {
                    return;
                }

                // Pobierz ID brandu bez ryzyka null->brand_id
                $brandRow = DB::connection('mysql-sklep')
                    ->table('brand_translations')
                    ->where('name', $brandName)
                    ->first();

                if (!$brandRow) {
                    return;
                }

                $brandID = $brandRow->brand_id;

                $productsController = new ProductsController;

                // Baza nazwy pliku
                $brandLogoFileBase = $productsController->makeSlug('brand_logo_' . $brandName);

                // ZAWSZE webp
                $imageName = $brandLogoFileBase . '.webp';

                // Upload (Twoja funkcja już koduje do webp)
                $uploadInfo = $this->downloadAndUploadToFTP($brandLogoOryginalUrl, $imageName);

                if (!$uploadInfo) {
                    return;
                }

                // Zmienne
                $path_folder = 'marki/';
                $entity_type = 'Modules\Brand\Entities\Brand';
                $zoneImageType = 'logo';

                // (opcjonalnie) final URL — teraz też ma .webp
                $finalUrl = env('MEDIA_SFTP_IMAGES_PRE_URL') . $path_folder . $imageName;

                $fileID = DB::connection('mysql-sklep')->table('files')->insertGetId([
                    'user_id'   => 1,
                    'filename'  => $imageName,
                    'disk'      => 'media_sanipro',
                    'path'      => $path_folder . $imageName,
                    'extension' => 'webp',
                    'mime'      => 'image/webp',
                    'size'      => $uploadInfo['size'] ?? null,
                ]);

                DB::connection('mysql-sklep')->table('entity_files')->insert([
                    'file_id'      => $fileID,
                    'entity_type'  => $entity_type,
                    'entity_id'    => $brandID,
                    'zone'         => $zoneImageType,
                ]);

            });

            unlink($uploadedImportFile);

            return redirect()->back()->with(
                'success',
                'Zdjęcia logotypów wgrano jako WEBP na serwer media.agrowerk.pl i przypisano'
            );
        } catch (\Exception $error) {
            return $error->getMessage();
        }
    }

}

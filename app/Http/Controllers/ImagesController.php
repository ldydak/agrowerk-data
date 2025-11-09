<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\SimpleExcel\SimpleExcelReader;
use Illuminate\Support\Facades\Storage;
use Image;

class ImagesController extends Controller
{
    public function show(){
        return view('images.import');
    }

    private $importType;
    private $productsDB;
    private $productTranslationsDB;
    private $productSlug;
    // private $productID;

    public function import(Request $request){
        $request->file('file')->move(public_path('import_temp'),$request->file('file')->getClientOriginalName());
        $uploadedImportFile = public_path('import_temp') . '/' . $request->file('file')->getClientOriginalName();
        $this->importType = $request->imagesImportType;

        try{
            SimpleExcelReader::create($uploadedImportFile)->getRows()
            ->each(function(array $rowProperties) {
                $productsDB = DB::connection('mysql-sklep')->table("products");
                $productTranslationsDB = DB::connection('mysql-sklep')->table("product_translations");
                
                // pętla dla kazdego rzędu pliku csv

                // wykonaj dla produktu ktory istnieje w bazie
                if($this->productExist($rowProperties['SKU']) == true){
                    $product = $productsDB->where('sku','=', $rowProperties['SKU'])->first();
                    $productID = $product->id;
                    $productSlug = $product->slug;
                    $baseImage = $rowProperties['base_image'];
                    $additionalImages = $rowProperties['additional_images'];
                    $imagesUrlToDatabase = array();

                    // Base image
                    // wykonaj funkcje i zaktualizuj w bazie dla base image
                    $this->mainImportImageFunction($baseImage, $productID, $rowProperties['sku'], $this->importType, 'base_image');

                    // Additional images
                    $i = 0;
                    foreach(explode(',', $additionalImages) as $additionalImage){
                        // wyjątek jesli w plikach do importu wystepują błędny pusty link dla zdjęcia albo ze znakiem hash, to pomiń wszystko
                        if($additionalImage == '' OR $additionalImage == '#'){
                            break;
                        }
                        // koniec wyjątku

                        $i++;

                        // wykonaj funkcje i zaktualizuj w bazie dla additional images
                        $this->mainImportImageFunction($additionalImage, $productID, $rowProperties['sku'], $this->importType, 'additional_images');
                    }
                    
                }

            });
            // usun plik tymczasowy z ktorego importuje dane
            unlink($uploadedImportFile);
            return redirect()->back()->with('success', 'Zdjęcia wgrano pomyślnie na serwer media.schomann.pl');

        }
        catch(\Exception $error){
            return $error->getMessage();
        }
    }


    public function mainImportImageFunction($imageUrl, $productID, $sku, $importType, $zoneImageType) {
        $filesDB = DB::connection('mysql-sklep')->table("files");
        $entityFilesDB = DB::connection('mysql-sklep')->table("entity_files");

        // rozszerzenie pliku
        $imageExtension = pathinfo($imageUrl, PATHINFO_EXTENSION);
        // nadaj mu nazwe jak nazwa produktu, w przypadku jpg zamien na .webp, jesli inny typ pliku, pozstaw orygonalne rozszerzenie
        if($imageExtension == "jpg"){
            $imageName = $sku.'_'.$productSlug.'_'.$i.'.webp';
        }else{
            $imageName = $sku.'_'.$productSlug.'_'.$i.'.'.$imageExtension;
        }
        // jesli mam pomijać zdjecia juz istniejace i tylko wgrac nowe:
        if($importType == 'skipExisted'){
            // sprawdz czy plik istnieje juz na FTP
            if(Storage::disk('media_ftp')->exists($imageName)){
                // jesli istnieje, nie rob nic, pomiń ten plik
            }else{
                $this->downloadAndUploadToFTP($imageUrl, $imageName);
            }
            // jesli mam zaktualizowac wszystkie zdjecia i wgrac nowe
        }elseif($this->importType == 'updateAll'){
            $this->downloadAndUploadToFTP($imageUrl, $imageName);
        }
        
        $finalImageName =  (string)env('MEDIA_FTP_IMAGES_PRE_URL').$imageName;

        $fileID = $filesDB->insertGetId(
            [
                'user_id' => 1,
                'filename' => $finalImageName,
                'disk' => 'media_schomann',
                'path' => 'produkty/'.$finalImageName,
                'extension' => pathinfo($finalImageName, PATHINFO_EXTENSION),
                'mime' => '',
                'size' => ''
            ]
        );
        $entityFilesDB->insert(
            [
                'file_id' => $fileID,
                'entity_type' => 'Modules\Product\Entities\Product',
                'entity_id' => $productID,
                'zone' => $zoneImageType
            ]
        );

    }

    public function downloadAndUploadToFTP($imageUrl, $imageName){
        // sprawdz czy zdjecie istnieje u dostawcy (Hygiene-shop, Hygi)
        if($this->checkFileExistOnSupplierPage($imageUrl) == true){
            // pobierz zdjecie
            $downloadedImage = file_get_contents($imageUrl);
            // kompresuj na webp i jakosc 80%
            $compressedImage = Image::make($downloadedImage)->encode('webp', 90);
            // wgraj zdjecie na serwer ftp media.chemianiemcy.pl do folderu /produkty/
            Storage::disk('media_ftp')->put($imageName, $compressedImage, 'r+');
        }
    }

    public function getProductSlug($productID){
        $productsDB->where('id','=', $productID)->first()->slug;
        $productSlug = $productsDB->where('id','=', $productID)->first()->slug;
        return $productSlug;
    }

    public function checkFileExistOnSupplierPage($imageUrl){
        $file_headers = @get_headers($imageUrl);
        if ($file_headers) {
            // jesli header jest 200 - czyli plik istnieje
            if (strpos($file_headers[0], '200')) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
            // cos z serwerem
        }
    }

    public function productExist($sku){
        if ($productsDB->where('sku','=', $sku)->exists()) {
            // exists
            return true;
        }
    }
}

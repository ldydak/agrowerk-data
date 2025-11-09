<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\SimpleExcel\SimpleExcelReader;
use Cocur\Slugify\Slugify;
use Carbon\Carbon;

class ProductsController extends Controller
{
    public function show(){
        return view('products.import');
    }

    public function import(Request $request){
        
        $request->file('file')->move(public_path('import_temp'),$request->file('file')->getClientOriginalName());
        $uploadedImportFile = public_path('import_temp') . '/' . $request->file('file')->getClientOriginalName();
        try{
            SimpleExcelReader::create($uploadedImportFile)->useDelimiter(';')->getRows()
            ->each(function(array $rowProperties) {

                // szukaj czy numer artykułu z CSV istnieje juz w bazie sklepu
                // pętla dla kazdego rzędu pliku csv
                $productsDB = DB::connection('mysql-sklep')->table("products");
                $productTranslationsDB = DB::connection('mysql-sklep')->table("product_translations");

                $metaDataDB = DB::connection('mysql-sklep')->table("meta_data");
                $metaDataTranslationsDB = DB::connection('mysql-sklep')->table("meta_data_translations");

                $productExistAlready = $productsDB->where('sku','=', $rowProperties['SKU'])->first();

                if($productExistAlready){
                // jesli produkt istnieje, to nie rób nic...
                }else{
                // jesli produkt nie istnieje to:

                    // sprawdź producenta i zwróć jego ID
                    $brandID = $this->brand($rowProperties['Brand']);

                    // sprawdź kategorię i zwróć jej ID
                    // $categoryID = $this->category($rowProperties['Categories']);

                    // sprawdź wszystkie kategorie i zwróć wszystkie ID
                    $categoryIDs = $this->category($rowProperties['Categories']);

                    $finalPricePLNandProfitNetto = $this->price($rowProperties['oryginal_price']);

                    // $vatID = $this->vat();

                    // stwórz produkt
                    $productID = $productsDB->insertGetId(
                        [
                        'brand_id' => $brandID,
                        'tax_class_id' => 1,
                        'slug' =>  $this->makeSlug($rowProperties['Name']),
                        'sku' => $rowProperties['SKU'],
                        'price' => $this->price($rowProperties['oryginal_price']),
                        'selling_price' => $this->price($rowProperties['oryginal_price']),
                        'oryginal_price' => str_replace(',', '.', $rowProperties['oryginal_price']),
                        'ean' => $rowProperties['ean'],
                        'wee' => $rowProperties['weee'],
                        'weight' => str_replace(',', '.', str_replace(' kg', '', $rowProperties['weight'])),
                        'product_sheet_url' => $rowProperties['product_sheet'],
                        'safety_sheet_url' => $rowProperties['safety_sheet'],
                        'manual_url' => $rowProperties['manual'],
                        'chemical_info' => $rowProperties['chemical_info'],
                        'oryginal_url' => $rowProperties['oryginal_url'],
                        'manage_stock' => 0,
                        'is_active' => 1,
                        'updated_at' => Carbon::now()]
                    );

                    // przypisz produkt do kategorii (jednej, ostatniej)
                    // DB::connection('mysql-sklep')->table("product_categories")->insert(
                    //     ['category_id' => $categoryID,
                    //     'product_id' => $productID]
                    // );

                    // przypisz produkt do każdej kategorii w hierarchii
                    foreach ($categoryIDs as $catID) {
                        DB::connection('mysql-sklep')->table("product_categories")->insert([
                            'category_id' => $catID,
                            'product_id' => $productID
                        ]);
                    }

                    // dodaj opisy do tego produktu w tabeli product_translations 
                    $productTranslationsDB->insert(
                        [
                            'product_id' => $productID,
                            'locale' => 'pl',
                            'name' => $rowProperties['Name'],
                            'description' => $rowProperties['Description'],
                            'short_description' => $rowProperties['meta_description'],
                        ]
                    );

                    // dodaj meta data
                    $metaDataID = $metaDataDB->insertGetId(
                        [
                            'entity_type' => 'Modules\Product\Entities\Product',
                            'entity_id' => $productID,
                        ]
                    );
                    // meta data translations
                    $metaDataTranslationsDB->insert(
                        [
                            'meta_data_id' => $metaDataID,
                            'locale' => 'pl',
                            'meta_title' => $rowProperties['Name'],
                            'meta_description' => $rowProperties['meta_description'],
                        ]
                    );
                }
             });

            // usun plik tymczasowy z ktorego importuje dane
            unlink($uploadedImportFile);

            return redirect()->back()->with('success', 'Zaimportowano pomyślnie.');
        }
        catch(\Exception $error){
            return $error->getMessage();
        }
    }


    public function brand($brandName){
        $brandsDB = DB::connection('mysql-sklep')->table("brands");
        $brandTranslationsDB = DB::connection('mysql-sklep')->table("brand_translations");

        $brandNameExistAlready = $brandTranslationsDB->where('name','=', $brandName)->first();
        if($brandNameExistAlready){
            // jesli brand istnieje, to zwróć jego ID
            return $brandNameExistAlready->brand_id;
        }else{
            // jesli brand nie istnieje to go dodaj w tabeli brands i zwróć jego ID
            $insertedBrandID = $brandsDB->insertGetId(
                ['slug' => $this->makeSlug($brandName),
                'is_active' => 1]
            );
            // teraz dodaj w tabeli brand_translations
            $brandTranslationsDB->insert(
                ['brand_id' => $insertedBrandID,
                'locale' => 'pl',
                'name' => $brandName,
                ]
            );
            return $insertedBrandID;
        }
    }

    public function category(string $categoryPath): array
    {
        $categoriesDB = DB::connection('mysql-sklep')->table("categories");

        $categoryParts = array_map('trim', explode('>', $categoryPath));

        $parentId = null;
        $categoryIds = [];

        foreach ($categoryParts as $categoryName) {

            // Sprawdź czy istnieje kategoria o tej nazwie i rodzicu
            $existingCategory = DB::connection('mysql-sklep')->table('category_translations')
                ->join('categories', 'category_translations.category_id', '=', 'categories.id')
                ->where('category_translations.name', $categoryName)
                ->where(function ($q) use ($parentId) {
                    if ($parentId === null) {
                        $q->whereNull('categories.parent_id');
                    } else {
                        $q->where('categories.parent_id', $parentId);
                    }
                })
                ->select('categories.id')
                ->first();

            if ($existingCategory) {
                $categoryId = $existingCategory->id;
            } else {
                // Tworzymy nową kategorię
                $slug = $this->makeSlug($categoryName);

                // Zapobiegamy duplikacji slugów
                $originalSlug = $slug;
                $counter = 2;
                while (
                    DB::connection('mysql-sklep')
                        ->table('categories')
                        ->where('slug', $slug)
                        ->exists()
                ) {
                    $slug = $originalSlug . '-' . $counter;
                    $counter++;
                }

                // Wstawiamy kategorię
                $categoryId = $categoriesDB->insertGetId([
                    'slug' => $slug,
                    'is_active' => 1,
                    'is_searchable' => 1,
                    'parent_id' => $parentId,
                ]);

                DB::connection('mysql-sklep')->table('category_translations')->insert([
                    'category_id' => $categoryId,
                    'locale' => 'pl',
                    'name' => $categoryName,
                ]);
            }

            // Zapamiętujemy ID kategorii
            $categoryIds[] = $categoryId;

            // Ustawiamy nowego rodzica
            $parentId = $categoryId;
        }

        // Zwracamy wszystkie ID kategorii z hierarchii
        return $categoryIds;
    }




    public function price($priceToConvert)
    {
        // usuń zbędne znaki, przecinki itp.
        $priceToConvert = str_replace(',', '.', preg_replace('/[^\d,\.]/', '', $priceToConvert));

        $euro = 4.3;
        $profit = 50; // procent

        $pricePLN = (float)$priceToConvert * $euro;
        $finalPrice = $pricePLN * (1 + $profit / 100);

        return number_format($finalPrice, 2, '.', '');
    }

    // public function vat(){
    //     $vatDB = DB::connection('mysql-sklep')->table("ec_taxes");
    //     // szukamy vatu 23% i bierzemy jego ID
    //     $vatExistAlready = $vatDB->where('percentage','=','23.000000')->first();
    //     // jesli nie ma takiego vatu, to go tworzymy i zwracamy ID
    //     if($vatExistAlready){
    //         return $vatExistAlready->id;
    //     }else{
    //         $insertedVatID = $vatDB->insertGetId(
    //             ['title' => 'VAT 23%',
    //             'percentage' => '23.000000',
    //             'status' => 'published',
    //             'priority' => '1']
    //         );
    //     }
    //     return $insertedVatID;
    // }

    // public function addSlug($makeKey, $reference_id, $prefix){
    //     $slugify = new Slugify(['rulesets' => ['default', 'polish']]);
    //     // key to slug w bazie danych
    //     $key = $slugify->slugify($makeKey, '-');
    //     $slugDB = DB::connection('mysql-sklep')->table("slugs");
    //     if($prefix == 'produkt'){
    //         $reference_type = 'Botble\Ecommerce\Models\Product';
    //     } elseif($prefix == 'kategoria'){
    //         $reference_type = 'Botble\Ecommerce\Models\ProductCategory';
    //     } elseif($prefix == 'producent'){
    //         $reference_type = 'Botble\Ecommerce\Models\Brand';
    //     }
    //     $insertedSlugID = $slugDB->insertGetId(
    //         ['key' => $key,
    //         'reference_id' => $reference_id,
    //         'reference_type' => $reference_type,
    //         'prefix' => $prefix]
    //     );
    //     return $insertedSlugID;
    // }

    public function makeSlug($string){
        $slugify = new Slugify(['rulesets' => ['default', 'polish']]);
        $createdSlug = $slugify->slugify($string, '-');
        return $createdSlug;
    }

    // public function deleteAllImportedRecords(){
    //     DB::connection('mysql-sklep')->table("brands")->truncate();
    //     DB::connection('mysql-sklep')->table("products")->truncate();
    //     DB::connection('mysql-sklep')->table("ec_product_categories")->truncate();
    //     DB::connection('mysql-sklep')->table("ec_product_category_product")->truncate();
    //     DB::connection('mysql-sklep')->table("slugs")->truncate();

    //     return redirect()->back()->with('success', 'Wyczyszczono bazę: producenci, produkty, kategorie, połączenie produktu z kategoriami, slugi.');
    // }
}

<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\SimpleExcel\SimpleExcelReader;
use Cocur\Slugify\Slugify;
use Carbon\Carbon;
use Illuminate\Support\Str;


class ProductsController extends Controller
{
    public function show(){
        return view('products.import');
    }

    public function import(Request $request){
        
        $request->file('file')->move(public_path('import_temp'),$request->file('file')->getClientOriginalName());
        $uploadedImportFile = public_path('import_temp') . '/' . $request->file('file')->getClientOriginalName();
        try{

            // automatyczne wykrywanie separatora czy , czy ;
            $firstLine = fgets(fopen($uploadedImportFile, 'r'));
            $delimiter = str_contains($firstLine, ';') ? ';' : (str_contains($firstLine, ',') ? ',' : "\t");

            // usuń ewentualny BOM
            $csvContent = file_get_contents($uploadedImportFile);
            $csvContent = preg_replace('/^\xEF\xBB\xBF/', '', $csvContent);
            file_put_contents($uploadedImportFile, $csvContent);

            SimpleExcelReader::create($uploadedImportFile)
            ->useDelimiter($delimiter)
            ->getRows()
            ->each(function (array $rowProperties) {

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
                        'ean' => trim($rowProperties['ean'] ?? '') === '' ? null : $rowProperties['ean'],
                        'wee' => trim($rowProperties['weee'] ?? '') === '' ? null : $rowProperties['weee'],
                        'weight' => trim($rowProperties['weight']) === '' ? null : str_replace(',', '.', str_replace(' kg', '', $rowProperties['weight'])),
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


    public function makeSlug($string){
        $slugify = new Slugify(['rulesets' => ['default', 'polish']]);
        $createdSlug = $slugify->slugify($string, '-');
        return $createdSlug;
    }

    public $currentParentProductId;
    public $currentVariantPosition = 0;
    public $currentVariationId;
    
    public function wariantsImport(Request $request) {
        $request->file('file')->move(public_path('import_temp'),$request->file('file')->getClientOriginalName());
        $uploadedImportFile = public_path('import_temp') . '/' . $request->file('file')->getClientOriginalName();
        try {

            // automatyczne wykrywanie separatora czy , czy ;
            $firstLine = fgets(fopen($uploadedImportFile, 'r'));
            $delimiter = str_contains($firstLine, ';') ? ';' : (str_contains($firstLine, ',') ? ',' : "\t");

            // usuń ewentualny BOM
            $csvContent = file_get_contents($uploadedImportFile);
            $csvContent = preg_replace('/^\xEF\xBB\xBF/', '', $csvContent);
            file_put_contents($uploadedImportFile, $csvContent);

            SimpleExcelReader::create($uploadedImportFile)
            ->useDelimiter($delimiter)
            ->getRows()
            ->each(function (array $rowProperties) use (&$currentParentProductId) {
                $productsDB = DB::connection('mysql-sklep')->table("products");
                $productVariantsDB = DB::connection('mysql-sklep')->table("product_variants");
                $productTranslationsDB = DB::connection('mysql-sklep')->table("product_translations");
                $variationTranslationsDB = DB::connection('mysql-sklep')->table("variation_translations");
                $productVariationsDB = DB::connection('mysql-sklep')->table("product_variations");
                $variationsDB = DB::connection('mysql-sklep')->table("variations");
                $variationValuesDB = DB::connection('mysql-sklep')->table("variation_values");
                $variationValueTranslationsDB = DB::connection('mysql-sklep')->table("variation_value_translations");


                // Sprawdz czy istnieje juz variation translation 'Wariant produktu'
                // $existing = $variationTranslationsDB->where('name', 'Wariant produktu')->first();
                // if ($existing) {
                //     // jeśli istnieje → zwróć ID
                //     $this->variationTranslationId = $existing->id;
                // } else {
                //     // jeśli nie istnieje → utwórz i zwróć ID
                //     $this->variationTranslationId = $variationTranslationsDB->insertGetId([
                //         'name'       => 'Wariant produktu',
                //         'created_at' => now(),
                //         'updated_at' => now(),
                //     ]);
                // }

                $productVariantExistAlready = $productVariantsDB->where('sku','=', $rowProperties['SKU'])->first();
                if($productVariantExistAlready){
                // jesli wariant produktu juz istnieje, to nie rób nic
                } else {
                // jesli wariant produktu nie istnieje to:

                    // jesli rząd w pliku csv dotyczy produktu glownego (parent - ktory juz jest w bazie)
                    if ($rowProperties['product_type'] === 'parent_has_variants') {

                            // Ustaw licznik variant position na 0
                            $this->currentVariantPosition = 0;

                            // Ustaw "globalne" ID rodzica dla kolejnych wierszy
                            $this->currentParentProductId = $productsDB->where('sku','=', $rowProperties['SKU'])->first()->id;

                            // Aktualizuje produkt ktory jest rodzicem. Zmieniam slug, nazwe
                            // zostawiam SKU, i inne dane bo w przyszlosci bede mogl szukac po SKU i parent_has_variants
                            $productsDB->where('id','=', $this->currentParentProductId)->update([
                                'slug'          => $this->makeSlug($rowProperties['Name']),
                            ]);
                            $productTranslationsDB->where('product_id','=', $this->currentParentProductId)->update([
                                'name'          => $rowProperties['Name']
                            ]);

                            $productsDB->where('id','=', $this->currentParentProductId)->update([
                                'product_type'          => 'parent_has_variants'
                            ]);

                
                            // Stworz nowy typ wariantu "Wariant produktu". Niestety w bazie bedzie duzo takich wariantow - nie umiem tego obejsc, patrz powyzej do $existing
                            // variations
                            $this->currentVariationId = $variationsDB->insertGetId([
                                'uid'       => $this->shortLowerId(12),
                                'type'  => 'text',
                                'is_global' => 0,
                                'position' => 1,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                            // variation_translations
                            $variationTranslationsDB->insert([
                                'variation_id' => $this->currentVariationId,
                                'locale' => 'pl',
                                'name' => 'Wariant produktu',
                            ]);

                        
                            // Stworz powiazanie product variations i produktu - musi byc tylko raz, a zatem przy dodawaniu parenta
                            // product_variations
                            $productVariationsDB->insert([
                                'product_id' => $this->currentParentProductId,
                                'variation_id' => $this->currentVariationId,
                            ]);

                    } elseif ($rowProperties['product_type'] === 'is_variant') {

                       $uids = $this->shortLowerId(12);

                        // Zwieksz licznik variant position 
                        $this->currentVariantPosition++;

                        // stwórz wariant
                        $productVariantID = $productVariantsDB->insertGetId(
                            [
                            'uid' => $this->makeSlug($rowProperties['Name']),
                            'uids' => $uids,
                            'product_id' => $this->currentParentProductId,
                            'sku' => $rowProperties['SKU'],
                            'name' => $rowProperties['Name'],
                            'price' => $this->price($rowProperties['oryginal_price']),
                            'selling_price' => $this->price($rowProperties['oryginal_price']),
                            'oryginal_price' => str_replace(',', '.', $rowProperties['oryginal_price']),
                            'ean' => trim($rowProperties['ean'] ?? '') === '' ? null : $rowProperties['ean'],
                            'wee' => trim($rowProperties['weee'] ?? '') === '' ? null : $rowProperties['weee'],
                            'weight' => trim($rowProperties['weight']) === '' ? null : str_replace(',', '.', str_replace(' kg', '', $rowProperties['weight'])),
                            'oryginal_url' => $rowProperties['oryginal_url'],
                            'manage_stock' => 0,
                            'is_default' => ($this->currentVariantPosition == 1) ? 1 : 0,
                            'is_active' => 1,
                            'in_stock' => 1,
                            'position' => $this->currentVariantPosition,
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now()
                            ]
                        );

                        // variation_values
                        $variationValuesID = $variationValuesDB->insertGetId([
                            'uid' => $uids,
                            'variation_id' => $this->currentVariationId,
                            'value' => '',
                            'position' => $this->currentVariantPosition,
                        ]);

                        // variation_value_translations
                        $variationValueTranslationsDB->insert([
                            'variation_value_id' => $variationValuesID,
                            'locale' => 'pl',
                            'label' => $rowProperties['Name'],
                        ]);

                    }

                }

            });

            // usun plik tymczasowy z ktorego importuje dane
            unlink($uploadedImportFile);

            return redirect()->back()->with('success', 'Zaimportowano warianty pomyślnie.');
        }
        catch(\Exception $error){
            return $error->getMessage();
        }
    }


    public function newPricesImport(Request $request) {
        $request->file('file')->move(public_path('import_temp'),$request->file('file')->getClientOriginalName());
        $uploadedImportFile = public_path('import_temp') . '/' . $request->file('file')->getClientOriginalName();
        try {

            // automatyczne wykrywanie separatora czy , czy ;
            $firstLine = fgets(fopen($uploadedImportFile, 'r'));
            $delimiter = str_contains($firstLine, ';') ? ';' : (str_contains($firstLine, ',') ? ',' : "\t");

            // usuń ewentualny BOM
            $csvContent = file_get_contents($uploadedImportFile);
            $csvContent = preg_replace('/^\xEF\xBB\xBF/', '', $csvContent);
            file_put_contents($uploadedImportFile, $csvContent);

            SimpleExcelReader::create($uploadedImportFile)
            ->useDelimiter($delimiter)
            ->getRows()
            ->each(function (array $rowProperties) use (&$currentParentProductId) {
                $productsDB = DB::connection('mysql-sklep')->table("products");
                $productVariantsDB = DB::connection('mysql-sklep')->table("product_variants");

                $isNoPrice = (mb_strtolower($rowProperties['new_oryginal_price']) === 'brak ceny');

                if ($isNoPrice) {
                    // AKTUALIZACJA PO SKU
                    // 'brak ceny' -> in_stock = 0, ceny nie aktualizujemy bo jej nie ma - tzn produktu nie ma
                    $productsDB->where('sku', $rowProperties['sku'])->update([
                        'in_stock' => 0,
                        'updated_at' => now(), // jeśli tabela ma timestamps
                    ]);

                    $productVariantsDB->where('sku', $rowProperties['sku'])->update([
                        'in_stock' => 0,
                        'updated_at' => now(), // jeśli tabela ma timestamps
                    ]);

                    // AKTUALIZACJA PO ID produktu (np gdy chcemy aktualizowac SKU to musimy odnosic sie po ID bo sku moze byc nie aktualne)
                    // $productVariantsDB->where('id', $rowProperties['id'])->update([
                    //     'in_stock' => 0,
                    //     'updated_at' => now(), // jeśli tabela ma timestamps
                    // ]);

                    return;
                }

                // cena jest -> ustaw oryginal_price i in_stock=1
                $productsDB->where('sku', $rowProperties['sku'])->update([
                    'oryginal_price' => $rowProperties['new_oryginal_price'],
                    // 'oryginal_url' => $rowProperties['new_oryginal_url'], // gdy chcemy aktualizowac tez url do oryginalnego produktu
                    // 'ean' => $rowProperties['new_ean'], // gdy chcemy aktualizowac tez EAN do oryginalnego produktu
                    // 'sku' => $rowProperties['new_sku'], // gdy chcemy aktualizowac tez SKU
                    'in_stock' => 1,
                    'updated_at' => now(),
                ]);

                $productVariantsDB->where('sku', $rowProperties['sku'])->update([
                    'oryginal_price' => $rowProperties['new_oryginal_price'],
                    // 'oryginal_url' => $rowProperties['new_oryginal_url'], // gdy chcemy aktualizowac tez url do oryginalnego produktu
                    // 'ean' => $rowProperties['new_ean'], // gdy chcemy aktualizowac tez EAN do oryginalnego produktu
                    // 'sku' => $rowProperties['new_sku'], // gdy chcemy aktualizowac tez SKU
                    'in_stock' => 1,
                    'updated_at' => now(),
                ]);

            });

            // usun plik tymczasowy z ktorego importuje dane
            unlink($uploadedImportFile);

            return redirect()->back()->with('success', 'Zaimportowano i zaktualizowano oryginalne ceny.');
        }
        catch(\Exception $error){
            return $error->getMessage();
        }
    }

    public function productFaqImport(Request $request)
    {
        $file = $request->file('file');
        $filename = $file->getClientOriginalName();
        $file->move(public_path('import_temp'), $filename);

        $uploadedImportFile = public_path('import_temp') . '/' . $filename;

        try {
            // wykryj delimiter na podstawie pierwszej linii (z tolerancją BOM)
            $fh = fopen($uploadedImportFile, 'rb');
            $firstLine = $fh ? fgets($fh) : '';
            if ($fh) fclose($fh);

            $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', (string)$firstLine);
            $delimiter = str_contains($firstLine, ';') ? ';' : (str_contains($firstLine, ',') ? ',' : "\t");

            SimpleExcelReader::create($uploadedImportFile)
                ->useDelimiter($delimiter)
                ->getRows()
                ->each(function (array $row) {

                    // toleruj BOM w nazwach kolumn
                    $productId = $row['product_id'] ?? $row["\xEF\xBB\xBFproduct_id"] ?? null;
                    $faq = $row['faq'] ?? $row["\xEF\xBB\xBFfaq"] ?? null;

                    $productId = $this->toUtf8($productId);
                    $faq = $this->toUtf8($faq);

                    if (!$productId) {
                        return;
                    }

                    DB::connection('mysql-sklep')
                        ->table("product_translations")
                        ->where('product_id', $productId)
                        ->update([
                            'faq' => $faq ?? '',
                        ]);
                });

            unlink($uploadedImportFile);

            return redirect()->back()->with('success', 'Zaimportowano i zaktualizowano opisy FAQ do produktów.');
        } catch (\Exception $error) {
            return $error->getMessage();
        }
    }

    private function toUtf8($value)
    {
        if (!is_string($value)) return $value;

        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        foreach (['Windows-1250', 'ISO-8859-2', 'Windows-1252'] as $enc) {
            $converted = @mb_convert_encoding($value, 'UTF-8', $enc);
            if ($converted && mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }

        return $value;
    }


    public function shortLowerId($length = 12)
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $id = '';

        for ($i = 0; $i < $length; $i++) {
            $id .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $id;
    }

    // Funkcja działa tak dla upsell products: ze bierzemy losowo produkty z tej samej kategorii (najwyzej w drzewie hierarchii ).

    public function generateRelatedProducts(Request $request)
    {
        // Upsell - bierzemy produkty z tej kategorii co dany produkt (ale nie najnizszej, tylko wyzszej) przez co mamy wiekszy katalog produktow
        // Related - bierzemy produkty z tej samej kategorii ALE Z NAJNIZSZEJ HIERARCHII przez co mamy najbardziej podobne i pokrewne produkty do tego w ktorym jestesmy
        // Skrypt nie pozwala dodawac tych samych produktow do upsell co do related. Nie ma dubli.
        
        // Wyczyść upsell (produktu powiązane 'Możesz również polubić' - wyswietlane w lewym sidebarze)
        DB::connection('mysql-sklep')->table('up_sell_products')->truncate();

        // Wyczyść related (produkty pokrewne - podobne wyswietlane pod opisem produktu )
        DB::connection('mysql-sklep')->table('related_products')->truncate();

        $productsWithCategories = DB::connection('mysql-sklep')->table('product_categories')
            ->select('product_id', 'category_id')
            ->get()
            ->groupBy('product_id');

        $productsByCategory = DB::connection('mysql-sklep')->table('product_categories')
            ->select('product_id', 'category_id')
            ->get()
            ->groupBy('category_id');

        $allCategories = DB::connection('mysql-sklep')->table('categories')
            ->select('id', 'parent_id')
            ->get();

        $parentById = $allCategories->pluck('parent_id', 'id')->toArray();

        // nie-liście (kategorie będące rodzicem)
        $nonLeafCategoryIds = $allCategories
            ->pluck('parent_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $nonLeafSet = array_flip($nonLeafCategoryIds);

        // depth cache
        $depthCache = [];
        $getDepth = function($catId) use (&$getDepth, &$depthCache, $parentById) {
            if (!$catId) return 0;
            if (isset($depthCache[$catId])) return $depthCache[$catId];

            $parent = $parentById[$catId] ?? null;
            $depthCache[$catId] = $parent ? 1 + $getDepth($parent) : 1;

            return $depthCache[$catId];
        };

        $upsellData  = [];
        $relatedData = [];

        foreach ($productsWithCategories as $productId => $cats) {
            $assigned = $cats->pluck('category_id')->unique()->values();
            if ($assigned->isEmpty()) continue;

            // --- RELATED: najniższy liść (najgłębszy) ---
            $leafAssigned = $assigned->filter(fn($cid) => !isset($nonLeafSet[$cid]))->values();
            $relatedCandidateSet = $leafAssigned->isNotEmpty() ? $leafAssigned : $assigned;

            $relatedCategoryId = $relatedCandidateSet
                ->sortByDesc(fn($cid) => $getDepth($cid))
                ->first();

            // --- UPSELL: wyższa kategoria (najpłytsza z przypisanych) ---
            $upsellCategoryId = $assigned
                ->sortBy(fn($cid) => $getDepth($cid))
                ->first();

            // Pula upsell
            $upsellPool = collect();
            if ($upsellCategoryId && isset($productsByCategory[$upsellCategoryId])) {
                $upsellPool = $productsByCategory[$upsellCategoryId]
                    ->pluck('product_id')
                    ->filter(fn($pid) => (int)$pid !== (int)$productId)
                    ->unique()
                    ->values();
            }

            // Pula related
            $relatedPool = collect();
            if ($relatedCategoryId && isset($productsByCategory[$relatedCategoryId])) {
                $relatedPool = $productsByCategory[$relatedCategoryId]
                    ->pluck('product_id')
                    ->filter(fn($pid) => (int)$pid !== (int)$productId)
                    ->unique()
                    ->values();
            }

            // --- UPSSELL: max 10 ---
            $upsellIds = collect();
            if ($upsellPool->isNotEmpty()) {
                $upsellCount = min(10, $upsellPool->count());
                $upsellIds = $upsellPool->shuffle()->take($upsellCount)->values();

                foreach ($upsellIds as $upsellId) {
                    $upsellData[] = [
                        'product_id' => $productId,
                        'up_sell_product_id' => $upsellId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // --- RELATED: max 10, bez upsell (dla tego produktu) ---
            if ($relatedPool->isNotEmpty()) {
                $filteredRelatedPool = $relatedPool->diff($upsellIds)->values();

                if ($filteredRelatedPool->isNotEmpty()) {
                    $relatedCount = min(10, $filteredRelatedPool->count());
                    $relatedIds = $filteredRelatedPool->shuffle()->take($relatedCount)->values();

                    foreach ($relatedIds as $relId) {
                        $relatedData[] = [
                            'product_id' => $productId,
                            'related_product_id' => $relId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }

            // batche
            if (count($upsellData) >= 1000) {
                DB::connection('mysql-sklep')->table('up_sell_products')->insert($upsellData);
                $upsellData = [];
            }
            if (count($relatedData) >= 1000) {
                DB::connection('mysql-sklep')->table('related_products')->insert($relatedData);
                $relatedData = [];
            }
        }

        if (!empty($upsellData)) {
            DB::connection('mysql-sklep')->table('up_sell_products')->insert($upsellData);
        }
        if (!empty($relatedData)) {
            DB::connection('mysql-sklep')->table('related_products')->insert($relatedData);
        }

        return redirect()->back()->with('success', 'Upsell (wyższa kategoria) i related (liść) zostały wygenerowane.');
    }



}

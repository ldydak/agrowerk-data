<?php

namespace App\Http\Controllers;

use Validator;
use Illuminate\Http\Request;
use App\Models\Prices;
use App\Models\Products;
use App\Models\Variants;
use Illuminate\Support\Facades\DB;


class PricesController extends Controller
{
    function show(Prices $data){
        $data = Prices::first();
        return view('settings.prices', ['data' => $data])->with('success',"Zaktualizowano pomyślnie.");
    }

    function update(Request $request, Prices $data){
        $data = Prices::find(1);
        if(empty($data)){
            $data = new Prices;
        }
        $data->exchangeRate = $request->exchangeRate;
        $data->profit_to_50euro = $request->profit_to_50euro;
        $data->profit_to_100euro = $request->profit_to_100euro;
        $data->profit_to_200euro = $request->profit_to_200euro;
        $data->profit_to_500euro = $request->profit_to_500euro;
        $data->profit_above_500euro = $request->profit_above_500euro;
        $data->save();
        return redirect()->back()->with('success', 'Ustawienia cen i marży zapisane.');
    }

    function countAndUpdatePrices(){
        $products = Products::get();
        foreach ($products as $product) {
            $oryginalPriceEuro = $product->oryginal_price;
            $newPricePln = $this->price($oryginalPriceEuro);
            $product->price = $newPricePln;
            $product->selling_price = $newPricePln;
            // jesli produkt ma cene wyprzedazaowa (sale_price) to tez wygeneruj nowe ceny promocyjne sale_price
            // if(isset($product->sale_price)){
            //     // funkcja generateSalePrice() w PromotionsControllerze
            //     $newSalePrice = $promotionsController->generateSalePrice($newPrice, Promotions::find(1)->discountFrom, Promotions::find(1)->discountTo);
            //     $product->sale_price = $newSalePrice;
            // }
            $product->update();
        }

        $variants = Variants::get();
        foreach ($variants as $variant) {
            $oryginalPriceEuro = $variant->oryginal_price;
            $newPricePln = $this->price($oryginalPriceEuro);
            $variant->price = $newPricePln;
            $variant->selling_price = $newPricePln;

            $variant->update();
        }

        return redirect()->back()->with('success', 'Ceny przeliczone i zaktualizowane w sklepie.');
    }

    public function price($priceToConvert){
        // W agrowerk ceny  serwisu FAIE mamy w netto wiec musimy dodawac vat niemiecki  
        // Tworzymy cenę netto!!! System sklepowy dodaje VAT 23% do ceny w koszyku. Na sklepie wyświetlamy cene netto!!
        // Łącze się z bazą DATA a nie Sklep
        $pricesDB = DB::connection('mysql-data')->table("prices")->first();
        $euro = $pricesDB->exchangeRate;
        $germanVatPercent = (float) config('prices.german_vat_percent', 20);

        // wybor progu marzy na podstawie ceny zakupu w EUR po doliczeniu niemieckiego VAT
        $priceToConvert = (float) $priceToConvert; // dla pewnosci aby byl to number podczas sprawdzania
        $priceEuroWithGermanVat = $priceToConvert * (1 + ($germanVatPercent / 100));

        if ($priceEuroWithGermanVat <= 50) {
            $profit = $pricesDB->profit_to_50euro;
        } elseif ($priceEuroWithGermanVat <= 100) {
            $profit = $pricesDB->profit_to_100euro;
        } elseif ($priceEuroWithGermanVat <= 200) {
            $profit = $pricesDB->profit_to_200euro;
        } elseif ($priceEuroWithGermanVat <= 500) {
            $profit = $pricesDB->profit_to_500euro;
        } else {
            $profit = $pricesDB->profit_above_500euro;
        }

        $pricePLN = $priceEuroWithGermanVat * $euro;
        $finalPricePLNandProfitNetto = $pricePLN + ($pricePLN * ($profit / 100));
        return bcadd(0, $finalPricePLNandProfitNetto, 2);
    }
}

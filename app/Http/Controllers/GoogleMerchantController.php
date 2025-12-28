<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Products; // schomann sklep products
use Vitalybaev\GoogleMerchant\Feed;
use Vitalybaev\GoogleMerchant\Product; // google feed 1 product
use Vitalybaev\GoogleMerchant\Product\Shipping;
use Vitalybaev\GoogleMerchant\Product\Availability\Availability;
use Illuminate\Support\Facades\DB;

class GoogleMerchantController extends Controller
{

    public function generate(Request $request){

        $feed = new Feed("Schomann.pl", "https://schomann.pl", "Czystość i zaopatrzenie bez kompromisów. Profesjonalne zaopatrzenie B2B
Dostarczamy wysokiej jakości chemię i produkty użytkowe dla firm z Europy, głównie z Niemiec.");

        Products::chunk(100, function ($products) use ($feed) {
            foreach ($products as $product) {    

                // BRAK WARIANTÓW TYLKO PRODUKTU GŁÓWNE

                // dd($product);

                // For testing
                // if ($product->id < 401) {
                //     continue;
                // }
                
                // Pomijany produkt Konfekcja
                // if ($product->id == 15361) {
                //     continue;
                // }

                $item = new Product();

                // Set common product properties
                $item->setId($product->id);
                $item->setTitle(DB::connection('mysql-sklep')->table('product_translations')->where('product_id', $product->id)->first()->name);
                $item->setDescription(DB::connection('mysql-sklep')->table('product_translations')->where('product_id', $product->id)->first()->short_description);
                $item->setLink('https://schomann.pl/produkt/' . $product->slug);

                // Images
                $entityFiles = DB::connection('mysql-sklep')->table('entity_files')->where('entity_type', 'Modules\Product\Entities\Product')->where('entity_id', $product->id)->get();
                $fileIds = $entityFiles->pluck('file_id')->filter()->unique()->values()->all();
                $images = DB::connection('mysql-sklep')
                    ->table('files')
                    ->whereIn('id', $fileIds)
                    ->pluck('path')
                    ->map(fn ($path) => 'https://media.schomann.pl/' . ltrim($path, '/'))
                    ->values()
                    ->all();
                // fallback
                if (empty($images)) {
                    $images = ['https://schomann.pl/build/assets/image-placeholder.png'];
                }
                // główne zdjęcie
                $item->setImage($images[0]);
                // dodatkowe zdjęcia (wszystko poza pierwszym)
                $additionalImages = array_slice($images, 1);

                foreach ($additionalImages as $url) {
                    $item->addAdditionalImage($url); // jeśli taka metoda istnieje
                }


                if ($product->in_stock == 1) {
                    $item->setAvailability(Availability::IN_STOCK);
                } else {
                    $item->setAvailability(Availability::OUT_OF_STOCK);
                }
                $priceFormatted = number_format((float) $product->price, 2, '.', '') . ' PLN';
                $item->setPrice($priceFormatted);

                $item->setGtin($product->ean);
                $brand = DB::connection('mysql-sklep')->table('brand_translations')->where('brand_id', $product->brand_id)->first();
                $item->setBrand($brand->name ?? 'Nieznana marka');
                $item->setCondition('new');
            
                // Shipping info
                $shipping = new Shipping();
                $shipping->setCountry('PL');
                $shipping->setService('InPost Kurier');
                $shipping->setPrice('18 PLN');
                $item->setShipping($shipping);
            
                // Add this product to the feed
                $feed->addProduct($item);

                // dd($item);
                
            }
        });
        
        $feedXml = $feed->build();

        // Ścieżka do pliku w folderze public
        $filePath = public_path('google-merchant_feed.xml');

        // Zapis do pliku
        file_put_contents($filePath, $feedXml);

        // Zwrot odpowiedzi z informacją o lokalizacji pliku
        return response()->json(['message' => 'XML file generated', 'file' => url('google-merchant_feed.xml')]);
    }
    
}

<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if(Auth::check()){
        return redirect()->route('home');
    }else{
        return view('auth.login');
    }
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'show'])->name('home');

Route::group(['prefix' => 'products', 'middleware' => ['auth']], function(){
    Route::get('import', [\App\Http\Controllers\ProductsController::class, 'show'])->name('products.import.show');
    Route::post('import',[\App\Http\Controllers\ProductsController::class, 'import'])->name('products.import.import');
    Route::post('wariantsImport',[\App\Http\Controllers\ProductsController::class, 'wariantsImport'])->name('products.import.wariantsImport');
    Route::post('newPricesImport',[\App\Http\Controllers\ProductsController::class, 'newPricesImport'])->name('products.import.newPricesImport');
    Route::post('productFaqImport',[\App\Http\Controllers\ProductsController::class, 'productFaqImport'])->name('products.import.productFaqImport');
    Route::post('generateRelatedProducts',[\App\Http\Controllers\ProductsController::class, 'generateRelatedProducts'])->name('products.import.generateRelatedProducts');

});

Route::group(['prefix' => 'images', 'middleware' => ['auth']], function(){
    Route::get('import', [\App\Http\Controllers\ImagesController::class, 'show'])->name('images.import.show');
    Route::post('import',[\App\Http\Controllers\ImagesController::class, 'import'])->name('images.import.import');
    Route::post('wariantsImageImport',[\App\Http\Controllers\ImagesController::class, 'wariantsImageImport'])->name('images.import.wariantsImageImport');
    Route::post('brandsImageImport',[\App\Http\Controllers\ImagesController::class, 'brandsImageImport'])->name('images.import.brandsImageImport');

});

Route::group(['prefix' => 'settings', 'middleware' => ['auth']], function(){
    Route::get('prices', [\App\Http\Controllers\PricesController::class, 'show'])->name('settings.prices.show');
    Route::post('prices',[\App\Http\Controllers\PricesController::class, 'update'])->name('settings.prices.update');
    Route::post('update-prices',[\App\Http\Controllers\PricesController::class, 'countAndUpdatePrices'])->name('settings.prices.countAndUpdatePrices');
    Route::get('generate-google-merchant-feed', [\App\Http\Controllers\GoogleMerchantController::class, 'generate'])->name('settings.generate-google-merchant-feed');
    Route::get('indexnow', [IndexNowSitemapController::class, 'show'])
        ->name('settings.indexnow.show');
    Route::get('indexnow/submit-sitemap', [IndexNowSitemapController::class, 'submitFromSitemapWeb'])
        ->name('settings.submit-indexnow');
});




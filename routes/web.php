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

});

Route::group(['prefix' => 'images', 'middleware' => ['auth']], function(){
    Route::get('import', [\App\Http\Controllers\ImagesController::class, 'show'])->name('images.import.show');
    Route::post('import',[\App\Http\Controllers\ImagesController::class, 'import'])->name('images.import.import');

});
<?php
use App\Http\Controllers\IndexNowSitemapController;
use Illuminate\Support\Facades\Route;

Route::post('/indexnow/submit-sitemap', [IndexNowSitemapController::class, 'submitFromSitemap']);
Route::post('/notify', [\App\Http\Controllers\NotificationController::class, 'sendNotification']);


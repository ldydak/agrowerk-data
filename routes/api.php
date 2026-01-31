<?php
use Illuminate\Support\Facades\Route;

Route::post('/indexnow/submit-sitemap', [\App\Http\Controllers\IndexNowSitemapController::class, 'submitFromSitemapApi']);
Route::post('/notify', [\App\Http\Controllers\NotificationController::class, 'sendNotification']);


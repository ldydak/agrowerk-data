<?php
use Illuminate\Support\Facades\Route;

Route::post('/notify', [\App\Http\Controllers\NotificationController::class, 'sendNotification']);
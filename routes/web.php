<?php

use App\Http\Controllers\AirplaneController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PointController;
use App\Http\Controllers\RouteController;
use Illuminate\Support\Facades\Route;

Route::match(['GET', 'HEAD'], '/', [HomeController::class, 'index'])->name('home');
Route::view('/dashboard', 'dashboard')->name('dashboard');

Route::resource('points', PointController::class);
Route::resource('routes', RouteController::class);

Route::get('/airplanes/groq-status', [AirplaneController::class, 'groqStatus'])->name('airplanes.groq-status');
Route::post('/airplanes/ai-avoid-collision', [AirplaneController::class, 'aiAvoidCollision'])->name('airplanes.ai-avoid-collision');
Route::post('/airplanes/check-proximity', [AirplaneController::class, 'checkProximity'])->name('airplanes.check-proximity');
Route::post('/airplanes/bearing-to-pulkovo', [AirplaneController::class, 'bearingToPulkovo'])->name('airplanes.bearing-to-pulkovo');
Route::post('/airplanes/predict-route', [AirplaneController::class, 'predictRoute'])->name('airplanes.predict-route');
Route::post('/airplanes/generate-random', [AirplaneController::class, 'generateRandom'])->name('airplanes.generate-random');
Route::post('/airplanes/{airplane}/update-position', [AirplaneController::class, 'updatePosition'])->name('airplanes.update-position');
Route::resource('airplanes', AirplaneController::class);

Route::post('/settings/{name}', [AirplaneController::class, 'updateSetting'])->name('settings.update');

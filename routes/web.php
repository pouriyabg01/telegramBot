<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelBotController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/',[TelBotController::class , 'setWebhook']);
Route::post('/bot' , [TelBotController::class , 'index']);
Route::get('/set-webhook', [TelBotController::class , 'setWebhook']);
Route::get('/handle-message', [TelBotController::class, 'handle'])->name('telegram.handle');

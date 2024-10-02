<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\VerificationController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('home');
});

Route::get('/verification/{verification}/{voter}', [VerificationController::class, 'verify'])->name('verification.verify');
Route::get('/verification/{verification}/{voter}/email', [VerificationController::class, 'verifySingleEmail'])->name('verification.verify.single.email');
Route::get('/verification/{verification}/{voter}/phone', [VerificationController::class, 'verifySinglePhone'])->name('verification.verify.single.phone');

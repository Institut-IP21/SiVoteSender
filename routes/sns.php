<?php

use App\Http\Controllers\AmazonController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AWS SNS Routes
|--------------------------------------------------------------------------
|
| The SNS bounce/complaint webhook. Loaded by RouteServiceProvider under
| ['throttle:api', 'sns.verify'] — authentication is the AWS SNS message
| signature (App\Http\Middleware\VerifySnsMessage), NOT the internal app
| token group, since AWS cannot send the app's Authorization/Owner headers.
|
*/

Route::prefix('sns')->group(function () {
    Route::post('/webhook', [AmazonController::class, 'post'])->name('email.sns.notifications');
});

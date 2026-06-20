<?php

use App\Http\Controllers\BallotPreviewController;
use App\Http\Controllers\OwnerController;
use App\Http\Controllers\SentMessageController;
use App\Http\Controllers\VerificationApiController;
use App\Http\Controllers\VoterController;
use App\Http\Controllers\VoterListApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::fallback(function () {
    return response()->json(['message' => 'Not Found.'], 404);
})->name('api.fallback.404');

// NOTE: the AWS SNS webhook is registered separately in bootstrap/app.php
// (routes/sns.php) so it is authenticated by SNS signature instead of auth.api.

Route::middleware('api')->prefix('owner')->group(function () {
    Route::post('/personalization', [OwnerController::class, 'updatePersonalization'])->name('owner.personalization');
});

Route::middleware('api')->prefix('ballot')->group(function () {
    // Renders the invite mailable to HTML for an exact app-side preview (no send).
    Route::post('/invite-preview', [BallotPreviewController::class, 'invite'])->name('ballot.invite.preview');
});

Route::middleware('api')->prefix('messages')->group(function () {
    Route::get('/batch/{batchId}/stats', [SentMessageController::class, 'batchStats'])->name('sentMessage.batch.stats');
    Route::get('/batch/{batchId}/undeliverable', [SentMessageController::class, 'batchUndeliverable'])->name('sentMessage.batch.undeliverable');
    Route::get('/{sentMessage}', [SentMessageController::class, 'show'])->name('sentMessage.show')->middleware('can:view,sentMessage');
});

Route::middleware('api')->prefix('verification')->group(function () {
    Route::get('/{verification}', [VerificationApiController::class, 'show'])->name('verification.show')->middleware('can:view,verification');
    Route::get('/', [VerificationApiController::class, 'list'])->name('verification.list')->middleware('can:viewAny,App\Models\Verification');

    Route::delete('/{verification}', [VerificationApiController::class, 'delete'])->name('verification.delete')->middleware('can:delete,verification');
    Route::post('/', [VerificationApiController::class, 'create'])->name('verification.create')->middleware('can:create,App\Models\Verification');
    Route::post('/{verification}', [VerificationApiController::class, 'update'])->name('verification.update')->middleware('can:update,verification');
    Route::get('/{verification}/start', [VerificationApiController::class, 'start'])->name('verification.start')->middleware('can:update,verification');
    Route::get('/single/{voter}/start', [VerificationApiController::class, 'startSingle'])->name('verification.start.single')->middleware('can:update,voter');
    Route::post('/{verification}/start/test', [VerificationApiController::class, 'startTest'])->name('verification.start.test')->middleware('can:update,verification');
});

Route::middleware('api')->prefix('voter')->group(function () {
    Route::get('/{voter}', [VoterController::class, 'show'])->name('voter.show')->middleware('can:view,voter');
    Route::delete('/{voter}', [VoterController::class, 'delete'])->name('voter.remove')->middleware('can:delete,voter');
});

Route::middleware('api')->prefix('voterlist')->group(function () {
    Route::get('/{voterlist}', [VoterListApiController::class, 'show'])->name('voterlist.show')->middleware('can:view,voterlist');
    Route::get('/{voterlist}/basic', [VoterListApiController::class, 'showBasic'])->name('voterlist.showBasic')->middleware('can:view,voterlist');
    Route::get('/', [VoterListApiController::class, 'list'])->name('voterlist.list')->middleware('can:viewAny,App\Models\VoterList');
    Route::get('/{voterlist}/voters', [VoterListApiController::class, 'listVoters'])->name('voterlist.voters.list')->middleware('can:view,voterlist');
    Route::delete('/{voterlist}', [VoterListApiController::class, 'delete'])->name('voterlist.delete')->middleware('can:delete,voterlist');
    Route::post('/', [VoterListApiController::class, 'create'])->name('voterlist.create')->middleware('can:create,App\Models\VoterList');
    Route::post('/{voterlist}', [VoterListApiController::class, 'update'])->name('voterlist.update')->middleware('can:update,voterlist');
    Route::post('/{voterlist}/voters', [VoterListApiController::class, 'addVoters'])->name('voterlist.voters.add')->middleware('can:update,voterlist');
    Route::delete('/{voterlist}/voters', [VoterListApiController::class, 'removeVoters'])->name('voterlist.voters.remove')->middleware('can:update,voterlist');
    Route::post('/{voterlist}/send-invites', [VoterListApiController::class, 'sendInvites'])->name('voterlist.invite')->middleware('can:update,voterlist');
    Route::post('/{voterlist}/send-session-invites', [VoterListApiController::class, 'sendSessionInvites'])->name('voterlist.session.invite')->middleware('can:update,voterlist');
    Route::post('/{voterlist}/send-results', [VoterListApiController::class, 'sendResults'])->name('voterlist.results')->middleware('can:update,voterlist');
    Route::post('/send/test', [VoterListApiController::class, 'startTest'])->name('voterlist.start.test')->middleware('can:update,voterlist');
});

<?php

namespace App\Http\Controllers\Web;

use App\Models\SentMessage;
use App\Models\Verification;
use App\Models\Voter;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

/**
 * @Controller(prefix="verification")
 * @Middleware("web")
 */
class VerificationController extends Controller
{

    /**
     * @Get("/{verification}/{voter}/", as="verification.verify")
     */
    public function verify(Request $request, Verification $verification, Voter $voter)
    {
        if (!$request->hasValidSignature()) {
            abort(401);
        }

        $sentMessage = $voter->sentMessages()->where('verification_id', $verification->id)->first();

        switch ($sentMessage->type) {
            case SentMessage::TYPE_SMS:
                $voter->phone_verified = now();

            case SentMessage::TYPE_EMAIL:
                $voter->email_verified = now();
                break;

            default:
                throw \Exception('Unknown message type!');
                break;
        }

        $voter->save();

        if ($verification->redirect_url) {
            return redirect($verification->redirect_url);
        }

        return view('verification.success');
    }

    /**
     * @Get("/single/{voter}/email", as="verification.verify.single.email")
     */
    public function verifySingleEmail(Request $request, Voter $voter)
    {
        if (!$request->hasValidSignature()) {
            abort(401);
        }

        $voter->email_verified = now();
        $voter->save();


        return view('verification.success');
    }

    /**
     * @Get("/single/{voter}/phone", as="verification.verify.single.phone")
     */
    public function verifySinglePhone(Request $request, Voter $voter)
    {
        if (!$request->hasValidSignature()) {
            abort(401);
        }

        $voter->phone_verified = now();
        $voter->save();


        return view('verification.success');
    }
}

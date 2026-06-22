<?php

namespace App\Http\Controllers;

use App\Mail\BallotInvite;
use App\Mail\BallotResult;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BallotPreviewController extends Controller
{
    /**
     * Render the REAL invite mailable to HTML so the app can show an exact preview —
     * same Mailable, same Markdown template, same header/footer chrome and the
     * authenticated owner's personalization as a live send. We render with a sample
     * code so %%CODE%%/%%LINK%% substitution is shown exactly as recipients see it.
     *
     * Nothing is sent or persisted; this only renders.
     */
    public function invite(Request $request): Response
    {
        $validated = $request->validate([
            'template' => 'required|string',
            'subject' => 'required|string',
            'url' => 'required|string',
            'code' => 'nullable|string',
            'locale' => 'nullable|string',
        ]);

        $code = $validated['code'] ?? 'XXXX-XXXX-XXXX';

        // Identical construction to the real send path (App\Services\Ballot::sendInvites):
        // the mailable substitutes the code/link and resolves personalization from the
        // authenticated ApiUser (set by ApiAuth from the Owner header).
        $mailable = new BallotInvite(
            $code,
            $validated['url'],
            $validated['template'],
            $validated['subject'],
            $validated['locale'] ?? null,
        );

        return response($mailable->render(), 200, ['Content-Type' => 'text/html']);
    }

    /**
     * Render the REAL results mailable to HTML for an exact app-side preview — same
     * Mailable, Markdown template, chrome and owner personalization as a live send.
     * The CSV attachment isn't part of the HTML body, so a tiny sample stands in.
     *
     * Nothing is sent or persisted; this only renders.
     */
    public function result(Request $request): Response
    {
        $validated = $request->validate([
            'template' => 'required|string',
            'subject' => 'required|string',
            'resultLink' => 'required|string',
            'locale' => 'nullable|string',
        ]);

        $mailable = new BallotResult(
            $validated['template'],
            $validated['subject'],
            "option,votes\n",
            $validated['resultLink'],
            $validated['locale'] ?? null,
        );

        return response($mailable->render(), 200, ['Content-Type' => 'text/html']);
    }
}

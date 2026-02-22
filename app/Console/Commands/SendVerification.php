<?php

namespace App\Console\Commands;

use App\Models\Verification;
use App\Services\Verification as VerificationService;
use Illuminate\Console\Command;

class SendVerification extends Command
{
    protected $signature = 'evote:send:verification
                            {--verification= : The verification ID}';

    protected $description = 'Send verification emails to voters';

    public function handle(VerificationService $verificationService)
    {
        $verificationId = $this->option('verification');

        if (!$verificationId) {
            $verifications = Verification::whereNull('sent_at')->get();
            if ($verifications->isEmpty()) {
                $this->error('No unsent verifications found.');
                return 1;
            }
            $choices = $verifications->mapWithKeys(function ($v) {
                return [$v->id => "{$v->id} - {$v->subject}"];
            })->toArray();
            $selected = $this->choice('Select a verification', $choices);
            $verificationId = array_search($selected, $choices);
        }

        $verification = Verification::with('voterList')->find($verificationId);

        if (!$verification) {
            $this->error("Verification with ID {$verificationId} not found.");
            return 1;
        }

        if (!$this->confirm("Send verification emails to all voters in list '{$verification->voterList->title}'?")) {
            $this->info('Cancelled.');
            return 0;
        }

        $verificationService->sendInvites($verification);

        $this->info('Verification emails queued for sending.');

        return 0;
    }
}

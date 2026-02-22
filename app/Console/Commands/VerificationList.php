<?php

namespace App\Console\Commands;

use App\Models\Verification;
use Illuminate\Console\Command;

class VerificationList extends Command
{
    protected $signature = 'evote:list:verification
                            {--voterlist= : Filter by voter list ID}';

    protected $description = 'List verifications';

    public function handle()
    {
        $query = Verification::query();

        if ($voterListId = $this->option('voterlist')) {
            $query->where('voterlist_id', $voterListId);
        }

        $verifications = $query->get();

        if ($verifications->isEmpty()) {
            $this->info('No verifications found.');
            return 0;
        }

        $rows = $verifications->map(function ($verification) {
            return [
                $verification->id,
                $verification->voterlist_id,
                $verification->subject,
                $verification->sent_at,
            ];
        });

        $this->table(['ID', 'Voter List ID', 'Subject', 'Sent At'], $rows);

        return 0;
    }
}

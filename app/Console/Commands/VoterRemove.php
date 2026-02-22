<?php

namespace App\Console\Commands;

use App\Models\Voter;
use Illuminate\Console\Command;

class VoterRemove extends Command
{
    protected $signature = 'evote:remove:voter
                            {--I|voter= : The voter ID}';

    protected $description = 'Remove a voter';

    public function handle()
    {
        $voterId = $this->option('voter');

        if (!$voterId) {
            $this->error('Voter ID is required. Provide --voter.');
            return 1;
        }

        $voter = Voter::find($voterId);

        if (!$voter) {
            $this->error("Voter with ID {$voterId} not found.");
            return 1;
        }

        if (!$this->confirm("Are you sure you want to remove voter '{$voter->title}'?")) {
            $this->info('Cancelled.');
            return 0;
        }

        $title = $voter->title;
        $voter->delete();

        $this->info("Voter '{$title}' has been removed.");

        return 0;
    }
}

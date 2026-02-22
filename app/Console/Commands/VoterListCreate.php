<?php

namespace App\Console\Commands;

use App\Models\VoterList;
use Illuminate\Console\Command;

class VoterListCreate extends Command
{
    protected $signature = 'evote:make:voterlist
                            {--T|title= : The title of the voter list}
                            {--O|owner= : The owner UUID}';

    protected $description = 'Create a new voter list';

    public function handle()
    {
        $title = $this->option('title') ?? $this->ask('Enter voter list title');
        $owner = $this->option('owner') ?? config('app.cli.default_owner');

        if (!$owner) {
            $this->error('Owner UUID is required. Provide --owner or set CLI_DEFAULT_OWNER in .env');
            return 1;
        }

        $voterList = VoterList::create([
            'title' => $title,
            'owner' => $owner,
        ]);

        $this->info("Created voter list '{$title}' with ID {$voterList->id}");

        return 0;
    }
}

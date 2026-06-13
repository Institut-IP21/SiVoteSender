<?php

namespace App\Console\Commands;

use App\Models\VoterList;
use Illuminate\Console\Command;

class VoterListDelete extends Command
{
    protected $signature = 'evote:delete:voterlist
                            {--voterlist= : The voter list ID}';

    protected $description = 'Delete a voter list';

    public function handle(): int
    {
        $voterListId = $this->option('voterlist');

        if (!$voterListId) {
            $voterLists = VoterList::all();
            if ($voterLists->isEmpty()) {
                $this->error('No voter lists found.');
                return 1;
            }
            $choices = $voterLists->mapWithKeys(fn($vl) => [$vl->id => "{$vl->id} - {$vl->title}"])->toArray();
            $selected = $this->choice('Select a voter list', $choices);
            $voterListId = array_search($selected, $choices);
        }

        $voterList = VoterList::find($voterListId);

        if (!$voterList) {
            $this->error("Voter list with ID {$voterListId} not found.");
            return 1;
        }

        if (!$this->confirm("Are you sure you want to delete voter list '{$voterList->title}'?")) {
            $this->info('Cancelled.');
            return 0;
        }

        $title = $voterList->title;
        $voterList->delete();

        $this->info("Voter list '{$title}' has been deleted.");

        return 0;
    }
}

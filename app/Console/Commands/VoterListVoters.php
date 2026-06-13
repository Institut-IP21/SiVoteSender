<?php

namespace App\Console\Commands;

use App\Models\VoterList;
use Illuminate\Console\Command;

class VoterListVoters extends Command
{
    protected $signature = 'evote:list:voter
                            {--voterlist= : The voter list ID}';

    protected $description = 'List voters in a voter list';

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

        $voters = $voterList->voters;

        if ($voters->isEmpty()) {
            $this->info('No voters found in this list.');
            return 0;
        }

        $rows = $voters->map(fn($voter) => [
            $voter->id,
            $voter->title,
            $voter->email,
            $voter->phone,
            $voter->email_verified ? 'Yes' : 'No',
        ]);

        $this->table(['ID', 'Name', 'Email', 'Phone', 'Email Verified'], $rows);

        return 0;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\VoterList;
use Illuminate\Console\Command;

class VoterListShow extends Command
{
    protected $signature = 'evote:show:voterlist
                            {--voterlist= : The voter list ID}';

    protected $description = 'Show details of a voter list';

    public function handle()
    {
        $voterListId = $this->option('voterlist');

        if (!$voterListId) {
            $voterLists = VoterList::all();
            if ($voterLists->isEmpty()) {
                $this->error('No voter lists found.');
                return 1;
            }
            $choices = $voterLists->mapWithKeys(function ($vl) {
                return [$vl->id => "{$vl->id} - {$vl->title}"];
            })->toArray();
            $selected = $this->choice('Select a voter list', $choices);
            $voterListId = array_search($selected, $choices);
        }

        $voterList = VoterList::find($voterListId);

        if (!$voterList) {
            $this->error("Voter list with ID {$voterListId} not found.");
            return 1;
        }

        $verifiedCount = $voterList->voters()->whereNotNull('email_verified')->count();

        $this->table(['Field', 'Value'], [
            ['ID', $voterList->id],
            ['Title', $voterList->title],
            ['Owner', $voterList->owner],
            ['Voters', $voterList->voters()->count()],
            ['Verified Voters', $verifiedCount],
            ['Sent Messages', $voterList->sentMessages()->count()],
            ['Created At', $voterList->created_at],
        ]);

        return 0;
    }
}

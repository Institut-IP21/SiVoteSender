<?php

namespace App\Console\Commands;

use App\Models\VoterList;
use Illuminate\Console\Command;

class VoterListList extends Command
{
    protected $signature = 'evote:list:voterlist
                            {--O|owner= : Filter by owner UUID}';

    protected $description = 'List voter lists';

    public function handle(): int
    {
        $query = VoterList::query();

        if ($owner = $this->option('owner')) {
            $query->owner($owner);
        }

        $voterLists = $query->get();

        if ($voterLists->isEmpty()) {
            $this->info('No voter lists found.');
            return 0;
        }

        $rows = $voterLists->map(fn($voterList) => [
            $voterList->id,
            $voterList->title,
            $voterList->voters()->count(),
            $voterList->created_at,
        ]);

        $this->table(['ID', 'Title', 'Voters', 'Created At'], $rows);

        return 0;
    }
}

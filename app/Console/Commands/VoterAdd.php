<?php

namespace App\Console\Commands;

use App\Models\Voter;
use App\Models\VoterList;
use Illuminate\Console\Command;

class VoterAdd extends Command
{
    protected $signature = 'evote:add:voter
                            {--voterlist= : The voter list ID}
                            {--T|title= : Voter name}
                            {--E|email= : Voter email}
                            {--P|phone= : Voter phone (optional)}
                            {--C|csv= : Path to CSV file for bulk import}';

    protected $description = 'Add a voter to a voter list';

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

        if ($csvPath = $this->option('csv')) {
            return $this->importFromCsv($voterList, $csvPath);
        }

        return $this->addSingleVoter($voterList);
    }

    protected function importFromCsv(VoterList $voterList, string $csvPath): int
    {
        if (!file_exists($csvPath)) {
            $this->error("CSV file not found: {$csvPath}");
            return 1;
        }

        $handle = fopen($csvPath, 'r');
        if (!$handle) {
            $this->error("Could not open CSV file: {$csvPath}");
            return 1;
        }

        // Skip header row
        fgetcsv($handle);

        $count = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2) {
                continue;
            }

            $voter = Voter::create([
                'title' => $row[0],
                'email' => $row[1],
                'phone' => $row[2] ?? null,
            ]);

            $voterList->voters()->attach($voter->id);
            $count++;
        }

        fclose($handle);

        $this->info("Added {$count} voters to voter list '{$voterList->title}'");

        return 0;
    }

    protected function addSingleVoter(VoterList $voterList): int
    {
        $title = $this->option('title') ?? $this->ask('Enter voter name');
        $email = $this->option('email') ?? $this->ask('Enter voter email');
        $phone = $this->option('phone');

        $voter = Voter::create([
            'title' => $title,
            'email' => $email,
            'phone' => $phone,
        ]);

        $voterList->voters()->attach($voter->id);

        $this->info("Added voter '{$title}' to voter list '{$voterList->title}'");

        return 0;
    }
}

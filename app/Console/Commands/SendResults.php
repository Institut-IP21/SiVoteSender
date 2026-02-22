<?php

namespace App\Console\Commands;

use App\Models\VoterList;
use App\Services\Ballot;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SendResults extends Command
{
    protected $signature = 'evote:send:results
                            {--voterlist= : The voter list ID}
                            {--template= : Path to HTML template file}
                            {--subject= : Email subject}
                            {--csv= : Path to CSV results file}
                            {--result-link= : Link to online results}
                            {--batch= : Batch UUID (auto-generated if not provided)}';

    protected $description = 'Send result emails to voters';

    public function handle(Ballot $ballotService)
    {
        $voterListId = $this->option('voterlist');

        if (!$voterListId) {
            $this->error('Voter list ID is required. Provide --voterlist.');
            return 1;
        }

        $voterList = VoterList::find($voterListId);

        if (!$voterList) {
            $this->error("Voter list with ID {$voterListId} not found.");
            return 1;
        }

        $templatePath = $this->option('template');
        if (!$templatePath || !file_exists($templatePath)) {
            $this->error('Template HTML file is required and must exist. Provide --template.');
            return 1;
        }

        $template = file_get_contents($templatePath);

        $subject = $this->option('subject');
        if (!$subject) {
            $this->error('Email subject is required. Provide --subject.');
            return 1;
        }

        $csvPath = $this->option('csv');
        if (!$csvPath || !file_exists($csvPath)) {
            $this->error('CSV results file is required and must exist. Provide --csv.');
            return 1;
        }

        $csv = file_get_contents($csvPath);

        $resultLink = $this->option('result-link') ?? '';

        $batch = $this->option('batch') ?? (string) Str::uuid();

        $ballotService->sendResults($voterList, $batch, $template, $subject, $csv, $resultLink);

        $this->info("Result emails queued for sending (batch: {$batch})");

        return 0;
    }
}

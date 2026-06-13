<?php

namespace App\Console\Commands;

use App\Models\VoterList;
use App\Services\Ballot;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SendInvites extends Command
{
    protected $signature = 'evote:send:invites
                            {--voterlist= : The voter list ID}
                            {--codes= : Path to JSON file containing vote codes}
                            {--template= : Path to HTML template file}
                            {--subject= : Email subject}
                            {--url= : Voting URL (must contain %%CODE%%)}
                            {--batch= : Batch UUID (auto-generated if not provided)}';

    protected $description = 'Send ballot invite emails to voters';

    public function handle(Ballot $ballotService): int
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

        $codesPath = $this->option('codes');
        if (!$codesPath || !file_exists($codesPath)) {
            $this->error('Codes JSON file is required and must exist. Provide --codes.');
            return 1;
        }

        $codes = json_decode(file_get_contents($codesPath), true);
        if (!is_array($codes)) {
            $this->error('Codes file must contain a valid JSON array.');
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

        $url = $this->option('url');
        if (!$url) {
            $this->error('Voting URL is required. Provide --url.');
            return 1;
        }

        if (!str_contains($url, '%%CODE%%')) {
            $this->error('Voting URL must contain %%CODE%%.');
            return 1;
        }

        $voterCount = $voterList->voters()->count();
        if (count($codes) !== $voterCount) {
            $this->error("Number of codes (" . count($codes) . ") does not match number of voters ({$voterCount}).");
            return 1;
        }

        $batch = $this->option('batch') ?? (string) Str::uuid();

        $ballotService->sendInvites($voterList, $codes, $url, $batch, $template, $subject);

        $this->info("Ballot invites queued for sending (batch: {$batch})");

        return 0;
    }
}

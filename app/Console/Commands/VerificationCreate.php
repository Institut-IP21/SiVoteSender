<?php

namespace App\Console\Commands;

use App\Models\Verification;
use App\Models\VoterList;
use Illuminate\Console\Command;

class VerificationCreate extends Command
{
    protected $signature = 'evote:make:verification
                            {--voterlist= : The voter list ID}
                            {--T|template= : The email template (must contain %%LINK%%)}
                            {--S|subject= : The email subject}
                            {--R|redirect-url= : The redirect URL after verification}';

    protected $description = 'Create a new verification';

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

        $subject = $this->option('subject') ?? $this->ask('Enter email subject');
        $template = $this->option('template') ?? $this->ask('Enter email template (must contain %%LINK%%)');

        if (!str_contains((string) $template, '%%LINK%%')) {
            $this->error('Template must contain %%LINK%%');
            return 1;
        }

        $redirectUrl = $this->option('redirect-url');

        $verification = Verification::create([
            'voterlist_id' => $voterList->id,
            'template' => $template,
            'subject' => $subject,
            'redirect_url' => $redirectUrl,
        ]);

        $this->info("Created verification for voter list '{$voterList->title}' with ID {$verification->id}");

        return 0;
    }
}

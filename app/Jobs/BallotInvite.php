<?php

namespace App\Jobs;

use App\Mail\BallotInvite as MailBallotInvite;
use App\Services\Sender;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BallotInvite implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $sender;
    private $code;
    private $url;
    private $template;
    private $subject;
    private $voter;
    private $voterlist;
    private $batch;
    private $personalization;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($code, $url, $template, $subject, $voter, $voterlist, $batch, Sender $sender, $personalization)
    {
        $this->sender = $sender;
        $this->code = $code;
        $this->url = $url;
        $this->template = $template;
        $this->subject = $subject;
        $this->voter = $voter;
        $this->voterlist = $voterlist;
        $this->batch = $batch;
        $this->personalization = $personalization;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $email = new MailBallotInvite($this->code, $this->url, $this->template, $this->subject, $this->personalization);
        $this->sender->sendEmail($this->voter, $email, $this->voterlist, $this->batch);
        Log::debug('Sent invite', ['voter' => $this->voter->id, 'batch' => $this->batch]);
    }
}

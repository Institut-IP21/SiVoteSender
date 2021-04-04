<?php

namespace App\Console\Commands;

use App\Mail\TestMail;
use App\Services\Sender;
use Illuminate\Console\Command;

use Mail;

class TestEmailCommand extends Command
{
    // SNS Sandbox
    //
    // success@simulator.amazonses.com
    // bounce@simulator.amazonses.com
    // complaint@simulator.amazonses.com
    // suppressionlist@simulator.amazonses.com

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:email {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Sender $sender)
    {
        $this->sender = $sender;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $email = $this->argument('email');

        $this->sender->checkAndSend($email, new TestMail());

        $this->info('Email sent');
    }
}

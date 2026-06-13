<?php

namespace App\Providers;

use App\Models\Voter;
use App\Policies\VoterPolicy;
use App\Models\SentMessage;
use App\Policies\SentMessagePolicy;
use App\Models\VoterList;
use App\Policies\VoterListPolicy;
use App\Models\Verification;
use App\Policies\VerificationPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        Voter::class => VoterPolicy::class,
        SentMessage::class => SentMessagePolicy::class,
        VoterList::class => VoterListPolicy::class,
        Verification::class => VerificationPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerPolicies();

        //
    }
}

<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        \App\Models\Voter::class => \App\Policies\VoterPolicy::class,
        \App\Models\SentMessage::class => \App\Policies\SentMessagePolicy::class,
        \App\Models\VoterList::class => \App\Policies\VoterListPolicy::class,
        \App\Models\Verification::class => \App\Policies\VerificationPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        //
    }
}

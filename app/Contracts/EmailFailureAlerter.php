<?php

namespace App\Contracts;

use App\Support\EmailFailureDigest;

/**
 * Delivers an {@see EmailFailureDigest} to operators. Implementation is chosen
 * by config('mail-alerts.transport') and bound in AppServiceProvider.
 */
interface EmailFailureAlerter
{
    public function alert(EmailFailureDigest $digest): void;
}

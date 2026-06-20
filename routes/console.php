<?php

use App\Console\Commands\FlushEmailFailureAlerts;
use Illuminate\Support\Facades\Schedule;

// Alert on e-mail failures each minute. Requires `schedule:run` (cron) in prod.
Schedule::command(FlushEmailFailureAlerts::class)
    ->everyMinute()
    ->withoutOverlapping();

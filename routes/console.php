<?php

use App\Console\Commands\FlushEmailFailureAlerts;
use Illuminate\Support\Facades\Schedule;

Schedule::command(FlushEmailFailureAlerts::class)
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('queue:prune-failed', ['--hours' => 48])
    ->daily();

<?php

use App\Models\SyncLog;
use Illuminate\Support\Facades\Schedule;

// Daily profile sync at 23:00 Bangladesh time.
Schedule::command('guides:sync-all')
    ->dailyAt('23:00')
    ->timezone('Asia/Dhaka')
    ->withoutOverlapping()
    ->onOneServer();

// Weekly: prune sync logs older than 30 days.
Schedule::command('model:prune', ['--model' => [SyncLog::class]])
    ->weekly()
    ->timezone('Asia/Dhaka');

// Daily: delete source uploads 30 days after generation + abandoned temp uploads.
Schedule::command('frames:purge-sources')
    ->dailyAt('03:30')
    ->timezone('Asia/Dhaka')
    ->onOneServer();

Schedule::command('horizon:snapshot')->everyFiveMinutes();

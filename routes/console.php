<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
|
| On the store's Windows server, Task Scheduler runs "php artisan schedule:run"
| every minute; see docs/operations/backup-and-restore.md.
|
*/

// Daily encrypted backup of the database and uploaded files, after closing.
Schedule::command('backup:clean')->dailyAt('01:00')->withoutOverlapping();
Schedule::command('backup:run')->dailyAt('01:30')->withoutOverlapping();
Schedule::command('backup:monitor')->dailyAt('07:00');

// Every balance must equal the sum of its movements.
Schedule::command('stock:verify-ledger')->dailyAt('02:30');

// Mark expired batches and send 90/60/30-day expiry alerts before opening.
Schedule::command('stock:daily-expiry-check')->dailyAt('06:00');

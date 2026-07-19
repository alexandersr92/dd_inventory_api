<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Respaldos de base de datos (spatie/laravel-backup).
// Requiere que el scheduler corra en el servidor:
//   * * * * * cd /ruta && php artisan schedule:run >> /dev/null 2>&1
Schedule::command('backup:clean')->dailyAt('01:00');
Schedule::command('backup:run --only-db')->dailyAt('01:30');
Schedule::command('backup:monitor')->dailyAt('02:00');

// Avisos de vencimiento de licencia (5 y 1 día antes).
Schedule::command('licenses:notify-expiring')->dailyAt('08:00');

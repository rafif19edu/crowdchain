<?php

use Illuminate\Support\Facades\Schedule;

/*
| Run by Heroku Scheduler in production (D-46): `php artisan schedule:run`
| is not used there; each command is added to Heroku Scheduler directly.
| Locally you can run `php artisan schedule:work`.
*/

Schedule::command('registrations:check-expiry')->dailyAt('01:00');

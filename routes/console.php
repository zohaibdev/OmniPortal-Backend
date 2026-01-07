<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Expire trials and suspend stores that haven't subscribed
Schedule::command('stores:expire-trials')->daily();

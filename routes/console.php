<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('leave:process-yearly')->dailyAt('00:00');

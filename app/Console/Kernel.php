<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Plan;
use App\Advert;
use Illuminate\Support\Facades\Mail;

class Kernel extends ConsoleKernel
{
    //Constants to access the pagseguro API
    const EMAIL = '';
    const TOKEN = '';

    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('geocode:clean')->daily();
        $schedule->command('tokens:clean')->daily();
        $schedule->command('adverts:delete')->daily();

        //PagSeguro related CRONJOBS
        //Personal payment
        $schedule->command('payment:check_p')->everyMinute();
        $schedule->command('payment:check_all_p')->daily();
        $schedule->command('payment:deactivate_p')->daily();
        //Plan payment
        $schedule->command('payment:check_e')->everyMinute();
        $schedule->command('payment:next_e')->dailyAt('07:00');
        $schedule->command('payment:retry_e')->dailyAt('06:00');
        $schedule->command('payment:cancelled_e')->daily();
        $schedule->command('payment:boleto_e')->dailyAt('01:00');

        //Comunication with anothers servers
        $schedule->command('communication:alexandreazevedo')->dailyAt('01:00');
        $schedule->command('communication:telesul')->dailyAt('02:00');
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}

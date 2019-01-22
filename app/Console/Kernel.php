<?php

namespace App\Console;

use App\Jobs\CheckDeploymentJob;
use App\Jobs\CheckMysqlJob;
use App\Jobs\CheckWorkspaceJob;
use App\Jobs\CheckDatabaseJob;
use App\Jobs\CheckModelconfigJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
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
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')
        //          ->hourly();

        $schedule->job(new CheckMysqlJob())->everyMinute()->withoutOverlapping()->onOneServer();
        $schedule->job(new CheckDeploymentJob())->everyMinute()->withoutOverlapping()->onOneServer();
        $schedule->job(new CheckWorkspaceJob())->everyMinute()->withoutOverlapping()->onOneServer();
        $schedule->job(new CheckDatabaseJob())->everyMinute()->withoutOverlapping()->onOneServer();
        $schedule->job(new CheckModelconfigJob())->everyMinute()->withoutOverlapping()->onOneServer();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}

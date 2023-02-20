<?php
use Pkg6\Console\Scheduling\Schedule;
use Pkg6\Console\Scheduling\ScheduleConsole;
require 'vendor/autoload.php';

class ConsoleScheduling extends ScheduleConsole
{
    /**
     * 定义任务计划
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command("schedule:list")->everyMinute();
        $schedule->exec("sleep 5")->everyMinute();
        $schedule->call(function () {
            file_put_contents("console-scheduling.log", time() . PHP_EOL, FILE_APPEND);
        })->everyMinute();
    }

}

define("BASE_PATH", __DIR__);
(new ConsoleScheduling)->handle();
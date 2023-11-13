<?php

use Pkg6\Console\Scheduling\Schedule;
use Pkg6\Console\Scheduling\ScheduleConsole;

$autoloadFile = [
    __DIR__ . '/../../../autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . 'vendor/autoload.php',
];
foreach ($autoloadFile as $file) {
    if (file_exists($file)) {
        require $file;
        break;
    }
}

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
//使用schedule:work 必须设置你的入口文件，否则就根目录下consoles作为执行文件
define("CONSOLE_NAME", __DIR__ . "/scheduling.php");
(new ConsoleScheduling)->handle();
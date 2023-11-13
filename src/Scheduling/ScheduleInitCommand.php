<?php


namespace Pkg6\Console\Scheduling;

use Pkg6\Console\Command;

class ScheduleInitCommand extends Command
{

    /**
     * @var string
     */
    protected $name = 'schedule:init';

    /**
     * @var string
     */
    protected $description = 'schedule project init';

    /**
     * @return int
     */
    public function handle()
    {
        $arr                               = json_decode(file_get_contents('./composer.json'), true);
        $arr['autoload']['psr-4']['App\\'] = "app/";
        file_put_contents('./composer.json', json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->initfile();
        @exec('composer dump-autoload');
        $this->info('init success');
        return self::SUCCESS;
    }

    /**
     * @return void
     */
    protected function initfile()
    {
        $php = '<?php
namespace App;
use Pkg6\Console\Scheduling\Schedule;
use Pkg6\Console\Scheduling\ScheduleConsole;
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
        $schedule->call(function(){
            file_put_contents("console-scheduling.log",time().PHP_EOL,FILE_APPEND);
        })->everyMinute(); 
    }
    /**
     *  注册命令行
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__."/Commands");
    }
}';
        if (!is_dir('./app')) {
            mkdir('./app');
        }
        file_put_contents('./app/ConsoleScheduling.php', $php);


        $php = '#!/usr/bin/env php
<?php
require __DIR__."/vendor/autoload.php";
define("BASE_PATH", __DIR__);
define("CONSOLE_NAME","consoles");
date_default_timezone_set("Asia/Shanghai");
(new \App\ConsoleScheduling)->handle();
';
        file_put_contents('./consoles', $php);
    }
}

<?php


namespace Pkg6\Console\Scheduling;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Pkg6\Console\Application;


class ScheduleConsole
{

    /**
     * @var Schedule
     */
    public static $schedule;
    /**
     * @var Application
     */
    protected $appaction;
    /**
     * @var array
     */
    protected $commands = [
        ScheduleInitCommand::class,
        ScheduleListCommand::class,
        ScheduleRunCommand::class,
        ScheduleWorkCommand::class,
    ];

    /**
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {

    }

    /**
     * @return void
     */
    protected function commands()
    {

    }

    protected function load($path)
    {
        if (!is_dir($path)) {
            return;
        }
        $dirIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::KEY_AS_PATHNAME
                | FilesystemIterator::CURRENT_AS_FILEINFO
                | FilesystemIterator::SKIP_DOTS)
        );
        /* @var \SplFileInfo $splFileInfo */
        foreach ($dirIterator as $splFileInfo) {
            $command = $this->nameSpaceClass($splFileInfo->getPathname());
            if (is_subclass_of($command, \Symfony\Component\Console\Command\Command::class)) {
                $this->commands[] = $command;
            }
        }
    }

    /**
     * @param $file
     * @return mixed|string
     */
    protected function nameSpaceClass($file)
    {
        $namespace         = $class = '';
        $getting_namespace = $getting_class = false;
        foreach (token_get_all(file_get_contents($file)) as $token) {
            if (is_array($token) && $token[0] == T_NAMESPACE) {
                $getting_namespace = true;
            }
            if (is_array($token) && $token[0] == T_CLASS) {
                $getting_class = true;
            }
            if ($getting_namespace === true) {
                if (is_array($token) && in_array($token[0], [T_STRING, T_NS_SEPARATOR])) {
                    $namespace .= $token[1];
                } elseif ($token === ';') {
                    $getting_namespace = false;
                }
            }
            if ($getting_class === true) {
                if (is_array($token) && $token[0] == T_STRING) {
                    $class = $token[1];
                    break;
                }
            }
        }
        return $namespace ? $namespace . '\\' . $class : $class;
    }

    /**
     * @return Application
     */
    public function getAppaction()
    {
        $this->appaction = (new Application('scheduling-1.0'))
            ->resolveCommands($this->commands);
        return $this->appaction;
    }

    /**
     * @param       $command
     * @param array $parameters
     * @param       $outputBuffer
     * @return int
     * @throws \Exception
     */
    public function call($command, array $parameters = [], $outputBuffer = null)
    {
        $this->bootstrap();
        return $this->getAppaction()->call($command, $parameters, $outputBuffer);
    }

    /**
     * @return void
     */
    protected function bootstrap()
    {
        $this->defineSchedule();
        $this->commands();
    }

    /**
     * @return $this
     */
    protected function defineSchedule()
    {
        $schedule = new Schedule();
        $this->schedule($schedule);
        static::$schedule = $schedule;
        return $this;
    }

    /**
     * @param $input
     * @param $output
     * @return int
     * @throws \Exception
     */
    public function handle($input = null, $output = null)
    {
        $this->bootstrap();
        return $this->getAppaction()->run($input ?: new ArgvInput(), $output ?: new ConsoleOutput());
    }
}
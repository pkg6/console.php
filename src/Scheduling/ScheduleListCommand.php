<?php


namespace Pkg6\Console\Scheduling;


use Pkg6\Console\Command;

class ScheduleListCommand extends Command
{
    /**
     * @var string
     */
    protected $name = 'schedule:list';
    /**
     * @var string
     */
    protected $description = 'List the scheduled commands';

    /**
     * @return void
     * @throws \Exception
     */
    public function handle()
    {
        $schedule = ScheduleConsole::$schedule;
        foreach ($schedule->events as $event) {
            $rows[] = [
                $event->command,
                $event->expression,
                $event->description,
                $event->getNextRunDate()->format('Y-m-d H:i:s P'),

            ];
        }
        $this->table([
            'Command',
            'Interval',
            'Description',
            'NextDue',
        ], $rows ?? []);
    }
}
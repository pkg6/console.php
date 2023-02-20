<?php


namespace Pkg6\Console\Scheduling;



use DateTime;
use Pkg6\Console\Application;
use Pkg6\Console\Command;
use Pkg6\Console\ProcessUtils;

class ScheduleWorkCommand extends Command
{
    /**
     * @var string
     */
    protected $name = 'schedule:work';
    /**
     * @var string
     */
    protected $description = 'Start the schedule worker';


    /**
     * @return mixed
     * @throws \Exception
     */
    public function handle()
    {
        $this->info('Schedule worker started successfully.');
        [$lastExecutionStartedAt, $keyOfLastExecutionWithOutput, $executions] = [null, null, []];
        while (true) {
            usleep(100 * 1000);
            if ((new DateTime())->format('s') === '00' &&
                !((new DateTime())
                        ->setTime(date('H'), date('i'))
                        ->getTimestamp() == $lastExecutionStartedAt)) {
                $executions[] = $execution = ProcessUtils::newProcess(
                    Application::formatCommandString('schedule:run'),
                    (defined('BASE_PATH') ? BASE_PATH : getcwd())
                );
                $execution->run();
                $lastExecutionStartedAt = (new DateTime())
                    ->setTime(date('H'), date('i'))
                    ->getTimestamp();
            }

            foreach ($executions as $key => $execution) {
                $output = trim($execution->getIncrementalOutput()) .
                    trim($execution->getIncrementalErrorOutput());
                if (!empty($output)) {
                    if ($key !== $keyOfLastExecutionWithOutput) {
                        $this->info(PHP_EOL . '[' . date('c') . '] Execution #' . ($key + 1) . ' output:');

                        $keyOfLastExecutionWithOutput = $key;
                    }
                    $this->output->writeln($output);
                }
                if (!$execution->isRunning()) {
                    unset($executions[$key]);
                }
            }
        }
    }
}
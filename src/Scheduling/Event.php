<?php


namespace Pkg6\Console\Scheduling;

use Closure;
use Cron\CronExpression;
use Pkg6\Console\ProcessUtils;
use Symfony\Component\Process\Process as SymfonyProcess;

class Event
{
    use ManagesFrequencies;

    /**
     * @var string
     */
    public $user;

    /**
     * @var string
     */
    public $command;
    /**
     * @var string
     */
    public $description;
    /**
     * @var string
     */
    public $expression = '* * * * *';
    /**
     * @var string
     */
    public $output = '/dev/null';
    /**
     * @var bool
     */
    public $shouldAppendOutput = false;

    /**
     * @var bool
     */
    public $pool = false;
    /**
     * @var int
     */
    public $exitCode;

    /**
     * @var callable[]
     */
    public $beforeCallbacks = [];
    /**
     * @var callable[]
     */
    public $afterCallbacks = [];
    /**
     * @var callable[]
     */
    public $filters = [];
    /**
     * @var callable[]
     */
    public $rejects = [];
    /**
     * @var SymfonyProcess
     */
    public $process;

    /**
     * @param mixed|string $command
     */
    public function __construct($command)
    {
        $this->command = $command;
        $this->output  = $this->getDefaultOutput();
    }

    /**
     * @param $description
     * @return $this
     */
    public function description($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return string
     */
    public function mutexName()
    {
        return 'schedule-' . sha1($this->expression . $this->command);
    }

    /**
     * @return string
     */
    public function getSummaryForDisplay()
    {
        if (is_string($this->description)) {
            return $this->description;
        }
        return $this->buildCommand();
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description ?: $this->mutexName();
    }

    /**
     * @param $user
     * @return $this
     */
    public function user($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return string
     */
    protected function getDefaultOutput()
    {
        return (DIRECTORY_SEPARATOR === '\\') ? 'NUL' : '/dev/null';
    }

    /**
     * @param $location
     * @param $append
     * @return $this
     */
    public function sendOutputTo($location, $append = false)
    {
        $this->output             = $location;
        $this->shouldAppendOutput = $append;
        return $this;
    }

    /**
     * @param $location
     * @return $this
     */
    public function appendOutputTo($location)
    {
        return $this->sendOutputTo($location, true);
    }

    /**
     * @return $this
     */
    public function storeOutput()
    {
        if (is_null($this->output) || $this->output == $this->getDefaultOutput()) {
            $this->sendOutputTo(sprintf('./runtime/scheduling-%s.log', sha1($this->mutexName())));
        }
        return $this;
    }

    /**
     * @param $callback
     * @return $this
     */
    public function when($callback)
    {
        $this->filters[] = is_callable($callback) ? $callback : function () use ($callback) {
            return $callback;
        };
        return $this;
    }

    /**
     * @param $callback
     * @return $this
     */
    public function skip($callback)
    {
        $this->rejects[] = is_callable($callback) ? $callback : function () use ($callback) {
            return $callback;
        };
        return $this;
    }

    /**
     * @return bool
     */
    public function filtersPass()
    {
        foreach ($this->filters as $callback) {
            if (!$callback()) {
                return false;
            }
        }
        foreach ($this->rejects as $callback) {
            if ($callback()) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return void
     */
    public function run()
    {
        $this->start();
    }

    /**
     * @return $this
     */
    public function pool()
    {
        $this->pool = true;
        return $this;
    }


    /**
     * @return void
     */
    protected function start()
    {
        $this->callBeforeCallbacks();
        $this->pool ? $this->process()->start() : $this->process()->run();
    }

    /**
     * @param $exitCode
     * @return $this
     */
    public function exitCode($exitCode)
    {
        $this->exitCode = $exitCode;
        return $this;
    }

    /**
     * @param Closure $callback
     * @return $this
     */
    public function onSuccess(Closure $callback)
    {
        return $this->after(function () use ($callback) {
            if (0 === $this->exitCode) {
                $callback();
            }
        });
    }

    /**
     * @param Closure $callback
     * @return $this
     */
    public function onFailure(Closure $callback)
    {
        return $this->after(function () use ($callback) {
            if (0 !== $this->exitCode) {
                $callback();
            }
        });
    }


    /**
     * @param Closure $callback
     * @return $this
     */
    public function before(Closure $callback)
    {
        $this->beforeCallbacks[] = $callback;
        return $this;
    }

    /**
     * @return void
     */
    public function callBeforeCallbacks()
    {
        if ($beforeCallbacks = $this->beforeCallbacks) {
            foreach ($beforeCallbacks as $callback) {
                $callback();
            }
        }
    }

    /**
     * @param Closure $callback
     * @return $this
     */
    public function after(Closure $callback)
    {
        $this->afterCallbacks[] = $callback;
        return $this;
    }

    /**
     * @return void
     */
    public function callAfterCallbacks()
    {
        if ($afterCallbacks = $this->afterCallbacks) {
            foreach ($afterCallbacks as $callback) {
                $callback();
            }
        }
    }

    /**
     * @return bool
     */
    public function isDue()
    {
        return $this->cronExpression()->isDue();
    }

    /**
     * @return \DateTime
     * @throws \Exception
     */
    public function getNextRunDate()
    {
        return $this->cronExpression()->getNextRunDate();
    }

    /**
     * @return CronExpression
     */
    public function cronExpression()
    {
        return new CronExpression($this->expression);
    }


    /**
     * @return SymfonyProcess
     */
    public function process()
    {
        $this->process = ProcessUtils::newProcess($this->buildCommand(), (defined('BASE_PATH') ? BASE_PATH : getcwd()));
        return $this->process;
    }

    /**
     * @return string
     */
    public function buildCommand()
    {
        return (new CommandBuilder)->buildCommand($this);
    }
}
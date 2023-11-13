<?php


namespace Pkg6\Console;


use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Swoole\Coroutine;
use Swoole\Runtime;

abstract class Command extends SymfonyCommand
{
    use HasParameters, InteractsWithIO, CallsCommands;

    // see https://tldp.org/LDP/abs/html/exitcodes.html
    public const SUCCESS = 0;
    public const FAILURE = 1;
    public const INVALID = 2;
    /**
     * The console command name.
     * @var string
     */
    protected $name;

    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature;

    /**
     * The console command description.
     * @var string
     */
    protected $description;

    /**
     * The console command help text.
     * @var string
     */
    protected $help;

    /**
     * @var int
     */
    protected $hookFlags = -1;

    /**
     * @var int
     */
    protected $exitCode = 0;

    /**
     * @var bool
     */
    protected $coroutine = false;

    /**
     * Command constructor.
     * @return  void
     */
    public function __construct()
    {
        if ($this->hookFlags < 0 && function_exists('swoole_hook_flags')) {
            $this->hookFlags = swoole_hook_flags();
        }
        if (isset($this->signature)) {
            $this->configureUsingFluentDefinition();
        } else {
            parent::__construct($this->name);
        }
        $this->setDescription((string)$this->description);
        $this->setHelp((string)$this->help);
        $this->setHidden($this->isHidden());
        if (!isset($this->signature)) {
            $this->specifyParameters();
        }
    }

    /**
     * @return int
     */
    abstract protected function handle();

    /**
     * Configure the console command using a fluent definition.
     * @return void
     */
    protected function configureUsingFluentDefinition()
    {
        [$name, $arguments, $options] = CommandParser::parse($this->signature);
        parent::__construct($this->name = $name);
        $this->getDefinition()->addArguments($arguments);
        $this->getDefinition()->addOptions($options);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getSignature()
    {
        return $this->signature;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return $this->help;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     * @throws ExceptionInterface
     */
    public function run(InputInterface $input, OutputInterface $output): int
    {
        $this->output = new SymfonyStyle($input, $output);
        return parent::run($this->input = $input, $this->output);
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $callback = function () {
            try {
                $this->exitCode = $this->handle();
            } catch (\Throwable $exception) {
                $this->exitCode = $exception->getCode();
                throw $exception;
            }
            return $this->exitCode;
        };
        if ($this->coroutine) {
            $this->swooleRunTime($callback);
            return $this->exitCode;
        }
        return $callback();
    }

    /**
     * @param callable $callbacks
     * @param int $flags
     * @return bool
     */
    protected function swooleRunTime($callbacks, $flags = SWOOLE_HOOK_ALL)
    {
        Runtime::enableCoroutine($flags);
        $s = new Coroutine\Scheduler();
        $options = Coroutine::getOptions();
        if (!isset($options['hook_flags'])) {
            $s->set(['hook_flags' => SWOOLE_HOOK_ALL]);
        }
        $s->add($callbacks);
        $result = $s->start();
        Runtime::enableCoroutine(false);
        return $result;
    }

    /**
     * @param $command
     * @return mixed|SymfonyCommand
     */
    protected function resolveCommand($command)
    {
        if (!class_exists($command)) {
            return $this->getApplication()->find($command);
        }
        $command = new $command;
        if ($command instanceof SymfonyCommand) {
            $command->setApplication($this->getApplication());
        }
        return $command;
    }

}
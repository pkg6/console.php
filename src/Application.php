<?php


namespace Pkg6\Console;


use Closure;
use Exception;
use RuntimeException;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Console\Command\Command as SymfonyCommand;


class Application extends SymfonyApplication
{
    /**
     * @var array
     */
    protected static $bootstrappers = [];
    /**
     * The output from the previous command.
     * @var BufferedOutput
     */
    protected $lastOutput;

    /**
     * Application constructor.
     * @param string $version
     */
    public function __construct($version = '1.0')
    {
        parent::__construct('Console Cli', $version);
        $this->setAutoExit(false);
        $this->setCatchExceptions(false);
        $this->bootstrap();
    }

    /**
     * @param $command
     * @return SymfonyCommand|null
     */
    public function resolve($command)
    {
        return $this->add(new $command);
    }

    /**
     * Resolve an array of commands through the application.
     * @param array|mixed $commands
     * @return $this
     */
    public function resolveCommands($commands)
    {
        $commands = is_array($commands) ? $commands : func_get_args();
        foreach ($commands as $command) {
            $this->resolve($command);
        }
        return $this;
    }

    /**
     * @param InputInterface|null $input
     * @param OutputInterface|null $output
     * @return int
     * @throws Exception
     */
    public function run(InputInterface $input = null, OutputInterface $output = null): int
    {
        return parent::run($input, $output);
    }

    /**
     * Determine the proper PHP executable.
     * @return string
     */
    public static function phpBinary()
    {
        return ProcessUtils::escapeArgument((new PhpExecutableFinder)->find(false));
    }

    /**
     * Format the given command as a fully-qualified executable command.
     * @param string $string 自定义命令
     * @param string $consoleName 自定义命令入口文件
     * @return string
     */
    public static function formatCommandString(string $string, string $consoleName = '')
    {
        if ($consoleName == '') {
            $consoleName = defined('CONSOLE_NAME') ? CONSOLE_NAME : 'consoles';
        }
        return sprintf('%s %s %s',
            static::phpBinary(),
            ProcessUtils::escapeArgument($consoleName),
            $string
        );
    }


    /**
     * Parse the incoming Artisan command and its input.
     * @param string $command
     * @param array $parameters
     * @return array
     */
    protected function parseCommand(string $command, array $parameters)
    {
        if (is_subclass_of($command, SymfonyCommand::class)) {
            $callingClass = true;
            $command = (new $command)->getName();
        }
        if (!isset($callingClass) && empty($parameters)) {
            $command = $this->getCommandName($input = new StringInput($command));
        } else {
            array_unshift($parameters, $command);
            $input = new ArrayInput($parameters);
        }
        return [$command, $input];
    }

    /**
     * @param       $command
     * @param array $parameters
     * @param       $outputBuffer
     * @return int
     * @throws Exception
     */
    public function call($command, array $parameters = [], $outputBuffer = null)
    {
        [$command, $input] = $this->parseCommand($command, $parameters);
        if (!$this->has($command)) {
            throw new RuntimeException(sprintf('The command "%s" does not exist.', $command));
        }
        return $this->run(
            $input, $this->lastOutput = $outputBuffer ?: new BufferedOutput
        );
    }


    /**
     * Get the output for the last run command.
     * @return string
     */
    public function output()
    {
        return $this->lastOutput && method_exists($this->lastOutput, 'fetch')
            ? $this->lastOutput->fetch()
            : '';
    }

    public static function starting(Closure $callback)
    {
        static::$bootstrappers[] = $callback;
    }

    /**
     * @return void
     */
    protected function bootstrap()
    {
        foreach (static::$bootstrappers as $bootstrapper) {
            $bootstrapper($this);
        }
    }

    /**
     * @return void
     */
    public static function forgetBootstrappers()
    {
        static::$bootstrappers = [];
    }

    /**
     * @param SymfonyCommand $command
     * @return SymfonyCommand|null
     */
    public function add(SymfonyCommand $command)
    {
        return $this->addToParent($command);
    }

    /**
     * @param SymfonyCommand $command
     * @return SymfonyCommand|null
     */
    protected function addToParent(SymfonyCommand $command)
    {
        return parent::add($command);
    }

    /**
     * @return InputOption
     */
    protected function getEnvironmentOption()
    {
        $message = 'The environment the command should run under';
        return new InputOption('--env', null, InputOption::VALUE_OPTIONAL, $message);
    }
}

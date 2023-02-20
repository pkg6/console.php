<?php


namespace Pkg6\Console;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;


trait CallsCommands
{
    /**
     * @param $command
     * @return mixed
     */
    abstract protected function resolveCommand($command);

    /**
     * @param $command
     * @param array $arguments
     * @return int
     * @throws \Exception
     */
    public function call($command, array $arguments = [])
    {
        return $this->runCommand($command, $arguments, $this->output);
    }

    /**
     * @param $command
     * @param array $arguments
     * @return int
     * @throws \Exception
     */
    public function callSilent($command, array $arguments = [])
    {
        return $this->runCommand($command, $arguments, new NullOutput);
    }

    /**
     * @param       $command
     * @param array $arguments
     * @return int
     * @throws \Exception
     */
    public function callSilently($command, array $arguments = [])
    {
        return $this->callSilent($command, $arguments);
    }

    /**
     * @param $command
     * @param array $arguments
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     */
    protected function runCommand($command, array $arguments, OutputInterface $output)
    {
        $arguments['command'] = $command;
        return $this->resolveCommand($command)->run(
            $this->createInputFromArguments($arguments), $output
        );
    }

    /**
     * @param array $arguments
     * @return ArrayInput
     */
    protected function createInputFromArguments(array $arguments)
    {
        $input = new ArrayInput(array_merge($this->context(), $arguments));
        if ($input->getOption('--no-interaction')) {
            $input->setInteractive(false);
        }
        return $input;
    }

    /**
     * @return array
     */
    protected function context()
    {
        $context = [];
        if ($option = $this->option()) {
            foreach ($option as $k => $v) {
                if (in_array($k, ['ansi', 'no-ansi', 'no-interaction', 'quiet', 'verbose'])) {
                    $context["--{$k}"] = $v;
                }
            }
        }
        return $context;
    }
}
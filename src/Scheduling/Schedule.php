<?php


namespace Pkg6\Console\Scheduling;

use Pkg6\Console\Application;
use Pkg6\Console\Command;
use Pkg6\Console\ProcessUtils;

class Schedule
{
    /**
     * @var Event[]
     */
    public $events = [];

    /**
     * @param callable $callback
     * @param array    $parameters
     * @return CallbackEvent
     */
    public function call(callable $callback, array $parameters = [])
    {
        $this->events[] = $event = new CallbackEvent(
            $callback, $parameters
        );
        return $event;
    }

    /**
     * @param        $command
     * @param array  $parameters
     * @param string $consoleName
     * @return Event
     */
    public function command($command, array $parameters = [], string $consoleName = '')
    {
        if (class_exists($command)) {
            $command = new $command;
            /** @var Command $command */
            return $this->exec(
                Application::formatCommandString($command->getName(), $consoleName), $parameters
            )->description($command->getDescription());
        }
        return $this->exec(Application::formatCommandString($command, $consoleName), $parameters);
    }

    /**
     * @param       $command
     * @param array $parameters
     * @return Event
     */
    public function exec($command, array $parameters = [])
    {
        if (count($parameters)) {
            $command .= ' ' . $this->compileParameters($parameters);
        }
        $this->events[] = $cronEvent = new Event($command);
        return $cronEvent;
    }


    /**
     * @return Event[]
     */
    public function dueEvents()
    {
        return array_filter($this->events, function (Event $event) {
            return $event->isDue();
        });
    }

    /**
     * @return \Generator
     */
    public function dueEventsGenerator()
    {
        foreach ($this->dueEvents() as $event) {
            yield $event;
        }
    }

    /**
     * @param array $parameters
     * @return string
     */
    protected function compileParameters(array $parameters)
    {
        $commandArr = [];
        foreach ($parameters as $key => $value) {
            if (is_array($value)) {
                $value = $this->compileArrayInput($key, $value);
            } elseif (!is_numeric($value) && !preg_match('/^(-.$|--.*)/i', $value)) {
                $value = ProcessUtils::escapeArgument($value);
            }
            $commandArr[] = is_numeric($key) ? $value : (strpos($value, $key) !== false ? $value : "{$key}={$value}");
        }
        return implode(' ', $commandArr);
    }

    /**
     * @param       $key
     * @param array $value
     * @return string
     */
    protected function compileArrayInput($key, array $value)
    {
        array_walk($value, function (&$v) {
            $v = ProcessUtils::escapeArgument($v);
        });
        if ($this->startsWith($key, '--')) {
            array_walk($value, function (&$v) use ($key) {
                $v = "{$key}={$v}";
            });
        } elseif ($this->startsWith($key, '-')) {
            array_walk($value, function (&$v) use ($key) {
                $v = "{$key} {$v}";
            });
        }
        return implode(' ', $value);
    }

    /**
     * @param $haystack
     * @param $needles
     * @return bool
     */
    protected function startsWith($haystack, $needles)
    {
        foreach ((array)$needles as $needle) {
            if ($needle !== '' && substr($haystack, 0, strlen($needle)) === (string)$needle) {
                return true;
            }
        }
        return false;
    }
}
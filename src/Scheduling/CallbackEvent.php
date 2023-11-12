<?php


namespace Pkg6\Console\Scheduling;



use Pkg6\VarDumper\Tests\VarDumperTest;
use Pkg6\VarDumper\VarDumper;

class CallbackEvent extends Event
{
    /**
     * @var array
     */
    public $parameters;
    /**
     * @var callable
     */
    public $callback;
    /**
     * @var mixed|null
     */
    public $result;

    public function __construct(\Closure $callback, array $parameters = [])
    {
        $this->callback   = $callback;
        $this->parameters = $parameters;
        parent::__construct($this->getSummaryForDisplay());
    }


    /**
     * @return false|mixed|void|null
     */
    public function run()
    {
        parent::callBeforeCallbacks();
        $response       = ($this->callback)(...$this->parameters);
        $this->exitCode = $response === false ? 1 : 0;
        return $response;
    }

    /**
     * @return callable|string
     */
    public function getSummaryForDisplay()
    {
        if (is_string($this->description)) {
            return $this->description;
        }
        return VarDumper::create($this->callback)->asString();
    }
}
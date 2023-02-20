<?php

use Pkg6\Console\Application;
use Pkg6\Console\Command;

require 'vendor/autoload.php';


class Test extends Command
{
    /**
     * @var string
     */
    protected $name = 'test';
    /**
     * @var string
     */
    protected $description = 'test demo';

    /**
     * @return mixed|void
     */
    protected function handle()
    {
        $this->info('test');
    }
}

class TestStarting extends Command
{
    /**
     * @var string
     */
    protected $name = 'test-starting';
    /**
     * @var string
     */
    protected $description = 'test starting';

    /**
     * @return mixed|void
     */
    protected function handle()
    {
        $this->info('test starting');
    }
}

class TestSwolle extends Command
{
    /**
     * @var string
     */
    protected $name = 'test-swoole';
    /**
     * @var string
     */
    protected $description = 'test swoole';

    /**
     * @var bool
     */
    protected $coroutine = true;

    /**
     * @return mixed|void
     */
    protected function handle()
    {
        $this->info('test swoole');
    }
}

Application::starting(function (Application $app) {
    $app->resolveCommands([TestStarting::class]);
});

$app = new Application();

$app->resolveCommands([Test::class, TestSwolle::class]);

$app->run();


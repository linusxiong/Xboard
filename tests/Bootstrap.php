<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use PHPUnit\Event\TestRunner\ExecutionFinished;
use PHPUnit\Event\TestRunner\ExecutionFinishedSubscriber;
use PHPUnit\Event\TestRunner\ExecutionStarted;
use PHPUnit\Event\TestRunner\ExecutionStartedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

class Bootstrap implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $facade->registerSubscribers(
            new BootstrapExecutionStartedSubscriber(),
            new BootstrapExecutionFinishedSubscriber()
        );
    }
}

class BootstrapExecutionStartedSubscriber implements ExecutionStartedSubscriber
{
    use CreatesApplication;

    public function notify(ExecutionStarted $event): void
    {
        $console = $this->createApplication()->make(Kernel::class);

        $commands = [
            'config:cache',
            'event:cache',
        ];

        foreach ($commands as $command) {
            $console->call($command);
        }
    }
}

class BootstrapExecutionFinishedSubscriber implements ExecutionFinishedSubscriber
{
    public function notify(ExecutionFinished $event): void
    {
        foreach (glob('bootstrap/cache/*.phpunit.php') ?: [] as $file) {
            unlink($file);
        }
    }
}

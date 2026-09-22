<?php

namespace Laya\Laravel\Tests;

use Laravel\Ai\AiServiceProvider;
use Laya\Laravel\LayaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            AiServiceProvider::class,
            LayaServiceProvider::class,
        ];
    }
}

<?php

namespace Okrg\BugsinkLaravel\Tests;

use Okrg\BugsinkLaravel\BugsinkServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [BugsinkServiceProvider::class];
    }
}

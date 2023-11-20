<?php

namespace Tests;

use Bunce\PruneDatabase\PruneDatabaseServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [PruneDatabaseServiceProvider::class];
    }
}

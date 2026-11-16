<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $config = $app->make('config');

        // RefreshDatabase may run migrate:fresh. Fail before test traits run
        // if an inherited setting or cached config points outside the suite.
        if (! $app->environment('testing') || $app->configurationIsCached()
            || $config->get('database.default') !== 'sqlite'
            || $config->get('database.connections.sqlite.database') !== ':memory:'
            || $config->get('database.connections.sqlite.url')
            || $config->get('mail.default') !== 'array'
            || $config->get('queue.default') !== 'sync'
            || $config->get('session.driver') !== 'array'
            || $config->get('cache.default') !== 'array') {
            throw new LogicException('Run tests in a development copy with no config cache and the isolated phpunit.xml settings.');
        }

        $config->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        return $app;
    }
}

<?php

namespace OnrampLab\TranscriptionOnrampLabExtension\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use OnrampLab\TranscriptionOnrampLabExtension\TranscriptionOnrampLabExtensionServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'testing_sqlite');
        $app['config']->set('database.connections.testing_sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    /**
     * Define database migrations.
     *
     * @return void
     */
    protected function defineDatabaseMigrations()
    {
        $this->artisan('vendor:publish', ['--tag' => 'transcription-migrations']);
        $this->loadLaravelMigrations();
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            TranscriptionOnrampLabExtensionServiceProvider::class,
        ];
    }
}

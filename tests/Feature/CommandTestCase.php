<?php

namespace Yourivw\Sailor\Tests\Feature;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use Symfony\Component\Yaml\Yaml;
use Yourivw\Sailor\SailorManager;
use Yourivw\Sailor\SailorService;

abstract class CommandTestCase extends TestCase
{
    use WithWorkbench;

    protected static string $firstSailService;

    protected string $originalBasePath;

    protected string $testDir;

    protected function setUp(): void
    {
        $this->afterApplicationCreated(function () {
            $this->testDir = $this->app->basePath('/tests/tmp');

            if (File::exists($this->testDir)) {
                File::deleteDirectory($this->testDir);
            }

            File::makeDirectory($this->testDir);
            File::copy($this->app->basePath('.env.example'), $this->testDir.'/.env');

            $this->originalBasePath = $this->app->basePath();
            $this->app->setBasePath($this->testDir);
        });

        $this->beforeApplicationDestroyed(function () {
            $this->app->setBasePath($this->originalBasePath);

            if (File::exists($this->testDir)) {
                File::deleteDirectory($this->testDir);
            }
        });

        parent::setUp();
    }

    protected function createDockerComposeFile()
    {
        $compose = [
            'services' => [
                'laravel.test' => [
                    'build' => [
                        'context' => './vendor/laravel/sail/runtimes/8.3',
                        'dockerfile' => 'Dockerfile',
                        'args' => [
                            'WWWGROUP' => '${WWWGROUP}',
                        ],
                    ],
                    'image' => 'sail-8.3/app',
                    'extra_hosts' => [
                        0 => 'host.docker.internal:host-gateway',
                    ],
                    'ports' => [
                        0 => '${APP_PORT:-80}:80',
                        1 => '${VITE_PORT:-5173}:${VITE_PORT:-5173}',
                    ],
                    'environment' => [
                        'WWWUSER' => '${WWWUSER}',
                        'LARAVEL_SAIL' => 1,
                        'XDEBUG_MODE' => '${SAIL_XDEBUG_MODE:-off}',
                        'XDEBUG_CONFIG' => '${SAIL_XDEBUG_CONFIG:-client_host=host.docker.internal}',
                        'IGNITION_LOCAL_SITES_PATH' => '${PWD}',
                    ],
                    'volumes' => [
                        0 => '.:/var/www/html',
                    ],
                    'networks' => [
                        0 => 'sail',
                    ],
                    'depends_on' => [
                        0 => 'test2',
                    ],
                ],
                'test2' => [
                    'image' => 'testing:latest',
                    'ports' => [
                        0 => '0002:0002',
                    ],
                    'networks' => [
                        0 => 'sail',
                    ],
                ],
            ],
            'networks' => [
                'sail' => [
                    'driver' => 'bridge',
                ],
            ],
            'volumes' => [
                'sail-test2' => [
                    'driver' => 'local',
                ],
            ],
        ];

        file_put_contents($this->app->basePath('docker-compose.yml'), Yaml::dump($compose, Yaml::DUMP_OBJECT_AS_MAP));
    }

    protected function registerTestingServices(?Closure $test1AddCallback = null, ?Closure $test2PublishCallback = null)
    {
        $test1StubPath = $this->app->basePath('test1.stub');
        $test1Stub = ['test1' => [
            'image' => 'testing:latest',
            'ports' => ['0001:0001'],
            'networks' => ['sail'],
        ]];
        file_put_contents($test1StubPath, Yaml::dump($test1Stub, Yaml::DUMP_OBJECT_AS_MAP));

        $test2StubPath = $this->app->basePath('test2.stub');
        $test2Stub = ['test2' => [
            'image' => 'testing:latest',
            'ports' => ['0002:0002'],
            'networks' => ['sail'],
        ]];
        file_put_contents($test2StubPath, Yaml::dump($test2Stub, Yaml::DUMP_OBJECT_AS_MAP));

        SailorService::create('test1', $test1StubPath)
            ->useDefault()
            ->callAfterAdding($test1AddCallback ?? function (Command $command, array $compose) {
                //
            })
            ->register();

        SailorService::create('test2', $test2StubPath)
            ->withVolume()
            ->withPublishable([$test2StubPath => $this->app->basePath('published/test2.stub')])
            ->callAfterPublishing($test2PublishCallback ?? function (Command $command) {
                //
            })
            ->register();
    }

    protected function getFirstSailService()
    {
        if (isset(static::$firstSailService)) {
            return static::$firstSailService;
        }

        /** @var SailorManager $manager */
        $manager = app(SailorManager::class);

        return static::$firstSailService = Arr::first($manager->sailServices());
    }
}

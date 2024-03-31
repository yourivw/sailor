<?php

namespace Yourivw\Sailor\Tests\Feature;

use Symfony\Component\Yaml\Yaml;
use Yourivw\Sailor\SailorManager;

class RenameCommandTest extends CommandTestCase
{
    public function testSuccessfulRenamingUsingOption()
    {
        $this->createDockerComposeFile();

        $this->artisan('sailor:rename laravel.testing')
            ->assertSuccessful();

        $dockerComposePath = $this->app->basePath('docker-compose.yml');
        $this->assertFileExists($dockerComposePath);
        $compose = Yaml::parseFile($dockerComposePath);

        $this->assertArrayHasKey('laravel.testing', $compose['services']);
        $this->assertArrayNotHasKey('laravel.test', $compose['services']);

        $envPath = $this->app->basePath('.env');
        $this->assertStringContainsString('APP_SERVICE=laravel.testing', file_get_contents($envPath));
    }

    public function testSuccessfulRenamingUsingDefault()
    {
        $this->createDockerComposeFile();

        /** @var SailorManager $manager */
        $manager = app(SailorManager::class);
        $manager->setDefaultServiceName('laravel.testing');

        $this->artisan('sailor:rename --no-interaction')
            ->assertSuccessful();

        $dockerComposePath = $this->app->basePath('docker-compose.yml');
        $this->assertFileExists($dockerComposePath);
        $compose = Yaml::parseFile($dockerComposePath);

        $this->assertArrayHasKey('laravel.testing', $compose['services']);
        $this->assertArrayNotHasKey('laravel.test', $compose['services']);

        $envPath = $this->app->basePath('.env');
        $this->assertStringContainsString('APP_SERVICE=laravel.testing', file_get_contents($envPath));
    }

    public function testSuccessfulRenamingUsingInput()
    {
        $this->registerTestingServices();
        $this->createDockerComposeFile();

        $this->artisan('sailor:rename')
            ->assertSuccessful()
            ->expectsQuestion('What\'s the new name for the Laravel service?', 'laravel.testing');

        $dockerComposePath = $this->app->basePath('docker-compose.yml');
        $this->assertFileExists($dockerComposePath);
        $compose = Yaml::parseFile($dockerComposePath);

        $this->assertArrayHasKey('laravel.testing', $compose['services']);
        $this->assertArrayNotHasKey('laravel.test', $compose['services']);

        $envPath = $this->app->basePath('.env');
        $this->assertStringContainsString('APP_SERVICE=laravel.testing', file_get_contents($envPath));
    }

    public function testReplacesNameInEnvFile()
    {
        $this->createDockerComposeFile();

        $env = file_get_contents($this->app->basePath('.env'));
        file_put_contents($this->app->basePath('.env'), $env."\n\nAPP_SERVICE=laravel.test");

        $this->artisan('sailor:rename laravel.testing')
            ->assertSuccessful();

        $dockerComposePath = $this->app->basePath('docker-compose.yml');
        $this->assertFileExists($dockerComposePath);
        $compose = Yaml::parseFile($dockerComposePath);

        $this->assertArrayHasKey('laravel.testing', $compose['services']);
        $this->assertArrayNotHasKey('laravel.test', $compose['services']);

        $envPath = $this->app->basePath('.env');
        $this->assertStringContainsString('APP_SERVICE=laravel.testing', file_get_contents($envPath));
    }
}

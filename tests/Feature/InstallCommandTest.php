<?php

namespace Yourivw\Sailor\Tests\Feature;

use Closure;
use Mockery;
use Mockery\MockInterface;
use Symfony\Component\Yaml\Yaml;
use Yourivw\Sailor\Console\InstallCommand;
use Yourivw\Sailor\SailorManager;

class InstallCommandTest extends CommandTestCase
{
    public function testSuccessfulInstallationUsingOptions()
    {
        $callback = $this->partialMock(\stdClass::class, function (MockInterface $mock) {
            $mock->expects('__invoke')
                ->withArgs([Mockery::type(InstallCommand::class), Mockery::type('array')]);
        });

        $this->registerTestingServices(Closure::fromCallable([$callback, '__invoke']));

        $sailServiceName = $this->getFirstSailService();

        $this->artisan('sailor:install --name=laravel.testing --with=test1,test2,'.$sailServiceName)
            ->assertSuccessful();

        $dockerComposePath = $this->app->basePath('docker-compose.yml');
        $this->assertFileExists($dockerComposePath);
        $compose = Yaml::parseFile($dockerComposePath);

        $this->assertEquals(['laravel.testing', $sailServiceName, 'test1', 'test2'], array_keys($compose['services']));
        $this->assertEquals([$sailServiceName, 'test1', 'test2'], $compose['services']['laravel.testing']['depends_on']);
        $this->assertArrayHasKey('sailor-test2', $compose['volumes']);

        $envPath = $this->app->basePath('.env');
        $this->assertStringContainsString('APP_SERVICE=laravel.testing', file_get_contents($envPath));
    }

    public function testSuccessfulInstallationUsingDefaults()
    {
        $this->registerTestingServices();

        $sailServiceName = $this->getFirstSailService();

        /** @var SailorManager $manager */
        $manager = app(SailorManager::class);
        $manager->setDefaultServiceName('laravel.testing');
        $manager->setSailDefaultServices($sailServiceName);

        $this->artisan('sailor:install --no-interaction')
            ->assertSuccessful();

        $dockerComposePath = $this->app->basePath('docker-compose.yml');
        $this->assertFileExists($dockerComposePath);
        $compose = Yaml::parseFile($dockerComposePath);

        $this->assertEquals(['laravel.testing', $sailServiceName, 'test1'], array_keys($compose['services']));
        $this->assertEquals([$sailServiceName, 'test1'], $compose['services']['laravel.testing']['depends_on']);
        $this->assertArrayNotHasKey('sailor-test2', $compose['volumes']);

        $envPath = $this->app->basePath('.env');
        $this->assertStringContainsString('APP_SERVICE=laravel.testing', file_get_contents($envPath));
    }

    public function testSuccessfulInstallationUsingInput()
    {
        $this->registerTestingServices();

        $sailServiceName = $this->getFirstSailService();

        $this->artisan('sailor:install')
            ->assertSuccessful()
            ->expectsQuestion('What\'s the name for the Laravel service?', 'laravel.testing')
            ->expectsQuestion('Which services would you like to install?', [$sailServiceName, 'test2']);

        $dockerComposePath = $this->app->basePath('docker-compose.yml');
        $this->assertFileExists($dockerComposePath);
        $compose = Yaml::parseFile($dockerComposePath);

        $this->assertEquals(['laravel.testing', $sailServiceName, 'test2'], array_keys($compose['services']));
        $this->assertEquals([$sailServiceName, 'test2'], $compose['services']['laravel.testing']['depends_on']);
        $this->assertArrayHasKey('sailor-test2', $compose['volumes']);

        $envPath = $this->app->basePath('.env');
        $this->assertStringContainsString('APP_SERVICE=laravel.testing', file_get_contents($envPath));
    }
}

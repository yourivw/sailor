<?php

namespace Yourivw\Sailor\Tests\Feature;

use Symfony\Component\Yaml\Yaml;
use Yourivw\Sailor\SailorManager;

class AddCommandTest extends CommandTestCase
{
    public function testSuccessfulAddingUsingOptions()
    {
        $this->registerTestingServices();
        $this->createDockerComposeFile();

        $sailServiceName = $this->getFirstSailService();
        $this->artisan('sailor:add test1,'.$sailServiceName)
            ->assertSuccessful();

        $dockerComposePath = $this->app->basePath('docker-compose.yml');
        $this->assertFileExists($dockerComposePath);
        $compose = Yaml::parseFile($dockerComposePath);

        $this->assertEqualsCanonicalizing(['laravel.test', $sailServiceName, 'test1', 'test2'], array_keys($compose['services']));
        $this->assertEqualsCanonicalizing([$sailServiceName, 'test1', 'test2'], $compose['services']['laravel.test']['depends_on']);
        $this->assertArrayHasKey('sail-test2', $compose['volumes']);
    }

    public function testSuccessfulAddingUsingDefaults()
    {
        $this->registerTestingServices();
        $this->createDockerComposeFile();

        $sailServiceName = $this->getFirstSailService();

        /** @var SailorManager $manager */
        $manager = app(SailorManager::class);
        $manager->setSailDefaultServices($sailServiceName);

        $this->artisan('sailor:add --no-interaction')
            ->assertSuccessful();

        $dockerComposePath = $this->app->basePath('docker-compose.yml');
        $this->assertFileExists($dockerComposePath);
        $compose = Yaml::parseFile($dockerComposePath);

        $this->assertEqualsCanonicalizing(['laravel.test', $sailServiceName, 'test1', 'test2'], array_keys($compose['services']));
        $this->assertEqualsCanonicalizing([$sailServiceName, 'test1', 'test2'], $compose['services']['laravel.test']['depends_on']);
        $this->assertArrayHasKey('sail-test2', $compose['volumes']);
    }

    public function testSuccessfulAddingUsingInput()
    {
        $this->registerTestingServices();
        $this->createDockerComposeFile();

        $sailServiceName = $this->getFirstSailService();

        $this->artisan('sailor:add')
            ->assertSuccessful()
            ->expectsQuestion('Which services would you like to install?', [$sailServiceName, 'test1']);

        $dockerComposePath = $this->app->basePath('docker-compose.yml');
        $this->assertFileExists($dockerComposePath);
        $compose = Yaml::parseFile($dockerComposePath);

        $this->assertEqualsCanonicalizing(['laravel.test', $sailServiceName, 'test1', 'test2'], array_keys($compose['services']));
        $this->assertEqualsCanonicalizing([$sailServiceName, 'test1', 'test2'], $compose['services']['laravel.test']['depends_on']);
        $this->assertArrayHasKey('sail-test2', $compose['volumes']);
    }
}

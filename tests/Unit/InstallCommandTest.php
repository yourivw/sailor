<?php

namespace Yourivw\Sailor\Tests\Unit;

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\File;
use Mockery;
use Mockery\MockInterface;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Yaml\Yaml;
use Yourivw\Sailor\Console\InstallCommand;
use Yourivw\Sailor\SailorManager;

class InstallCommandTest extends TestCase
{
    use WithWorkbench;

    public function testSuccessfulInstallation()
    {
        [$command, $manager] = $this->getMocks();

        $manager->expects('validateServices')
            ->andReturnTrue();

        File::shouldReceive('exists')
            ->with(base_path('docker-compose.yml'))
            ->andReturnFalse();

        $command->shouldReceive('option')
            ->with('name')
            ->andReturn('laravel.testing');

        $command->shouldReceive('option')
            ->with('with')
            ->andReturn('mysql,test1');

        $command->shouldReceive('option')
            ->with('devcontainer')
            ->andReturnFalse();

        $manager->shouldReceive('allServices')
            ->andReturn(collect(['mysql', 'test1']));

        $manager->shouldReceive('filterSailServices')
            ->andReturn(collect(['mysql']));

        $manager->shouldReceive('filterSailorServices')
            ->andReturn($expectedSailorServices = collect(['test1']));

        $command->expects('runCommand')
            ->withSomeOfArgs('sail:install', [
                '--with' => 'mysql',
                '--devcontainer' => false,
            ])
            ->andReturn(0);

        $command->expects('buildSailorDockerCompose')
            ->with($expectedSailorServices);

        $command->expects('renameLaravelService')
            ->with('laravel.testing');

        $command->expects('info');

        $this->assertContains($command->handle(), [null, 0]);
    }

    public function testExistingDockerComposeReturnsError()
    {
        [$command, $manager] = $this->getMocks();

        $manager->shouldReceive('validateServices')
            ->andReturnTrue();

        File::expects('exists')
            ->with(base_path('docker-compose.yml'))
            ->andReturnTrue();

        $command->expects('error');

        $this->assertGreaterThanOrEqual(1, $command->handle());
    }

    public function testInvalidServiceReturnsError()
    {
        [$command, $manager] = $this->getMocks();

        $manager->shouldReceive('validateServices')
            ->andReturnTrue();

        File::shouldReceive('exists')
            ->with(base_path('docker-compose.yml'))
            ->andReturnFalse();

        $command->shouldReceive('option')
            ->with('name')
            ->andReturn('laravel.testing');

        $command->shouldReceive('option')
            ->with('with')
            ->andReturn('mysql,test99');

        $manager->shouldReceive('allServices')
            ->andReturn(collect(['mysql', 'test1']));

        $command->expects('error')
            ->with(Mockery::pattern('/test99/'));

        $this->assertGreaterThanOrEqual(1, $command->handle());
    }

    public function testSailInstallFailureReturnsError()
    {
        [$command, $manager] = $this->getMocks();

        $manager->shouldReceive('validateServices')
            ->andReturnTrue();

        File::shouldReceive('exists')
            ->with(base_path('docker-compose.yml'))
            ->andReturnFalse();

        $command->shouldReceive('option')
            ->with('name')
            ->andReturn('laravel.testing');

        $command->shouldReceive('option')
            ->with('with')
            ->andReturn('mysql');

        $command->shouldReceive('option')
            ->with('devcontainer')
            ->andReturnFalse();

        $manager->shouldReceive('allServices')
            ->andReturn(collect(['mysql']));

        $manager->shouldReceive('filterSailServices')
            ->andReturn(collect(['mysql']));

        $manager->shouldReceive('filterSailorServices')
            ->andReturn(collect([]));

        $command->expects('runCommand')
            ->withSomeOfArgs('sail:install', [
                '--with' => 'mysql',
                '--devcontainer' => false,
            ])
            ->andReturn(9);

        $command->expects('error');

        $this->assertEquals(9, $command->handle());
    }

    /**
     * @return (InstallCommand|SailorManager|MockInterface)[]
     */
    protected function getMocks()
    {
        /** @var InstallCommand|MockInterface $command */
        $command = $this->partialMock(InstallCommand::class);
        $command->shouldAllowMockingProtectedMethods();

        /** @var SailorManager|MockInterface $manager */
        $manager = $this->partialMock(SailorManager::class);

        /** @var Yaml|MockInterface $yamlParser */
        $yamlParser = $this->partialMock(Yaml::class);

        $command->__construct($manager, $yamlParser);

        $outputStyle = $this->app->make(OutputStyle::class, [
            'input' => new StringInput(''),
            'output' => new BufferedOutput(),
        ]);
        $command->setOutput($outputStyle);

        return [$command, $manager];
    }
}

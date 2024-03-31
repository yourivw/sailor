<?php

namespace Yourivw\Sailor\Tests\Unit;

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Yaml\Yaml;
use Yourivw\Sailor\Console\RenameCommand;
use Yourivw\Sailor\SailorManager;

class RenameCommandTest extends TestCase
{
    use WithWorkbench;

    public function testSuccessfulRenaming()
    {
        [$command, $manager] = $this->getMocks();

        $manager->expects('validateServices')
            ->andReturnTrue();

        File::shouldReceive('exists')
            ->with(base_path('docker-compose.yml'))
            ->andReturnTrue();

        $command->shouldReceive('argument')
            ->with('name')
            ->andReturn('laravel.testing');

        $command->expects('renameLaravelService')
            ->with('laravel.testing')
            ->andReturnTrue();

        $command->expects('info');

        $this->assertContains($command->handle(), [null, 0]);
    }

    public function testMissingDockerComposeReturnsError()
    {
        [$command, $manager] = $this->getMocks();

        $manager->shouldReceive('validateServices')
            ->andReturnTrue();

        File::expects('exists')
            ->with(base_path('docker-compose.yml'))
            ->andReturnFalse();

        $command->expects('error');

        $this->assertGreaterThanOrEqual(1, $command->handle());
    }

    public function testRenamingFailedReturnsError()
    {
        [$command, $manager] = $this->getMocks();

        $manager->shouldReceive('validateServices')
            ->andReturnTrue();

        File::shouldReceive('exists')
            ->with(base_path('docker-compose.yml'))
            ->andReturnTrue();

        $command->shouldReceive('argument')
            ->with('name')
            ->andReturn('laravel.testing');

        $command->expects('renameLaravelService')
            ->with('laravel.testing')
            ->andReturnFalse();

        $command->expects('error');

        $this->assertGreaterThanOrEqual(1, $command->handle());
    }

    /**
     * @return (RenameCommand|MockInterface)[]
     */
    protected function getMocks()
    {
        /** @var RenameCommand|MockInterface $command */
        $command = $this->partialMock(RenameCommand::class);
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

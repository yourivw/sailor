<?php

namespace Yourivw\Sailor\Tests\Unit;

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Yaml\Yaml;
use Yourivw\Sailor\Console\BaseCommand;
use Yourivw\Sailor\SailorManager;

class BaseCommandTest extends TestCase
{
    use WithWorkbench;

    public function testGatherServicesInteractivelyRevertsToChoice()
    {
        [$command, $manager] = $this->getMocks();

        $manager->shouldReceive('allServices')
            ->andReturn(collect([]));

        $command->shouldReceive('multiselectExists')
            ->andReturnFalse();

        $command->expects('choice')
            ->andReturn([1]);

        $this->assertEquals(collect([1]), $command->gatherServicesInteractively());
    }

    public function testBuildSailorDockerComposeMissingLaravelServiceShowsWarning()
    {
        [$command, $manager, $yamlParser] = $this->getMocks();

        $compose = [
            'services' => [
                'laravel.test' => [],
            ],
            'volumes' => [
                'sail-mysql' => [
                    'driver' => 'local',
                ],
            ],
        ];

        $yamlParser->shouldReceive('parseFile')
            ->andReturn($compose);

        $command->shouldReceive('findLaravelServiceName')
            ->andReturnNull();

        $command->expects('warn');

        $yamlParser->expects('dump')
            ->andReturn('test');

        File::expects('put')
            ->with(base_path('docker-compose.yml'), 'test')
            ->andReturnTrue();

        $command->buildSailorDockerCompose(collect([]));
    }

    public function testBuildSailorDockerComposeRemovesEmptyVolumes()
    {
        [$command, $manager, $yamlParser] = $this->getMocks();

        $compose = [
            'services' => [
                'laravel.test' => [
                    'depends_on' => [],
                ],
            ],
            'volumes' => [],
        ];

        $yamlParser->shouldReceive('parseFile')
            ->andReturn($compose);

        $command->shouldReceive('findLaravelServiceName')
            ->andReturn('laravel.test');

        unset($compose['volumes']);

        $yamlParser->expects('dump')
            ->withSomeOfArgs($compose)
            ->andReturn('test');

        File::expects('put')
            ->with(base_path('docker-compose.yml'), 'test')
            ->andReturnTrue();

        $command->buildSailorDockerCompose(collect([]));
    }

    public function testRenameLaravelServiceNotFoundShowsError()
    {
        [$command, $manager, $yamlParser] = $this->getMocks();

        $yamlParser->shouldReceive('parseFile')
            ->andReturn(['services' => []]);

        $command->expects('error');

        $this->assertFalse($command->renameLaravelService(''));
    }

    /**
     * @return (TestableBaseCommand|SailorManager|Yaml|MockInterface)[]
     */
    protected function getMocks()
    {
        /** @var TestableBaseCommand|MockInterface $command */
        $command = $this->partialMock(TestableBaseCommand::class);
        $command->shouldAllowMockingProtectedMethods();

        /** @var SailorManager|MockInterface $manager */
        $manager = $this->partialMock(SailorManager::class);

        /** @var Yaml|MockInterface $yamlParser */
        $yamlParser = $this->partialMock(Yaml::class);

        $command->__construct($manager, $yamlParser);
        $command->setLaravel(app());

        $outputStyle = $this->app->make(OutputStyle::class, [
            'input' => new StringInput(''),
            'output' => new BufferedOutput(),
        ]);
        $command->setOutput($outputStyle);

        return [$command, $manager, $yamlParser];
    }
}

class TestableBaseCommand extends BaseCommand
{
    public function gatherServicesInteractively(): Collection
    {
        return parent::gatherServicesInteractively();
    }

    public function buildSailorDockerCompose(Collection $services)
    {
        return parent::buildSailorDockerCompose($services);
    }

    public function renameLaravelService(string $newServiceName): bool
    {
        return parent::renameLaravelService($newServiceName);
    }
}

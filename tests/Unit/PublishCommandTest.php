<?php

namespace Yourivw\Sailor\Tests\Unit;

use Illuminate\Console\OutputStyle;
use Mockery\MockInterface;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Yourivw\Sailor\Console\PublishCommand;
use Yourivw\Sailor\SailorManager;
use Yourivw\Sailor\SailorService;

class PublishCommandTest extends TestCase
{
    use WithWorkbench;

    public function testSuccessfullyPublishingByName()
    {
        [$command, $manager, $service1, $service2] = $this->getMocks();

        $manager->expects('validateServices')
            ->andReturnTrue();

        $command->shouldReceive('option')
            ->with('services')
            ->andReturn('test1,test2');

        $manager->shouldReceive('services')
            ->andReturn(['test1' => $service1, 'test2' => $service2]);

        $service1->expects('afterPublishing');
        $service2->expects('afterPublishing');

        $command->expects('call')
            ->with('vendor:publish', ['--tag' => 'sailor-test1']);

        $command->expects('call')
            ->with('vendor:publish', ['--tag' => 'sailor-test2']);

        $command->expects('info')
            ->twice();

        $this->assertContains($command->handle(), [null, 0]);
    }

    public function testSuccessfullyPublishingAll()
    {
        [$command, $manager, $service1, $service2] = $this->getMocks();

        $manager->shouldReceive('validateServices')
            ->andReturnTrue();

        $command->shouldReceive('option')
            ->with('services')
            ->andReturnNull();

        $command->expects('call')
            ->with('vendor:publish', ['--tag' => 'sailor']);

        $manager->shouldReceive('services')
            ->andReturn(['test1' => $service1, 'test2' => $service2]);

        $service1->expects('afterPublishing');
        $service2->expects('afterPublishing');

        $command->expects('info');

        $this->assertContains($command->handle(), [null, 0]);
    }

    public function testUnknownServiceNameReturnsError()
    {
        [$command, $manager] = $this->getMocks();

        $manager->shouldReceive('validateServices')
            ->andReturnTrue();

        $command->shouldReceive('option')
            ->with('services')
            ->andReturn('test1');

        $manager->shouldReceive('services')
            ->andReturn([]);

        $command->shouldNotReceive('call')
            ->with('vendor:publish');

        $command->expects('error')
            ->twice();

        $this->assertGreaterThanOrEqual(1, $command->handle());
    }

    /**
     * @return (PublishCommand|SailorManager|SailorService|MockInterface)[]
     */
    protected function getMocks()
    {
        /** @var PublishCommand|MockInterface $command */
        $command = $this->partialMock(PublishCommand::class);
        $command->shouldAllowMockingProtectedMethods();

        /** @var SailorManager|MockInterface $manager */
        $manager = $this->partialMock(SailorManager::class);
        $command->__construct($manager);

        $outputStyle = $this->app->make(OutputStyle::class, [
            'input' => new StringInput(''),
            'output' => new BufferedOutput(),
        ]);
        $command->setOutput($outputStyle);

        /** @var SailorService|MockInterface $service1 */
        $service1 = $this->partialMock(SailorService::class);
        $service1->__construct('test1', 'test1.stub');

        /** @var SailorService|MockInterface $service2 */
        $service2 = $this->partialMock(SailorService::class);
        $service2->__construct('test2', 'test2.stub');

        return [$command, $manager, $service1, $service2];
    }
}

<?php

namespace Yourivw\Sailor\Tests\Unit;

use Illuminate\Console\OutputStyle;
use Mockery\MockInterface;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Yourivw\Sailor\Console\ListCommand;
use Yourivw\Sailor\SailorManager;
use Yourivw\Sailor\SailorService;

class ListCommandTest extends TestCase
{
    use WithWorkbench;

    public function testSuccessfullyPublishingByName()
    {
        [$command, $manager, $service1, $service2] = $this->getMocks();

        $manager->expects('validateServices')
            ->andReturnTrue();

        $manager->shouldReceive('services')
            ->andReturn(['test1' => $service1, 'test2' => $service2]);

        // Test all services get looped through, at least expecting name() call.
        $service1->expects('name');
        $service2->expects('name');

        $command->expects('table');

        $this->assertContains($command->handle(), [null, 0]);
    }

    /**
     * @return (ListCommand|SailorManager|SailorService|MockInterface)[]
     */
    protected function getMocks()
    {
        /** @var ListCommand|MockInterface $command */
        $command = $this->partialMock(ListCommand::class);
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

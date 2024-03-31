<?php

namespace Yourivw\Sailor\Tests\Unit;

use Exception;
use Mockery\MockInterface;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use Yourivw\Sailor\Console\InstallCommand;
use Yourivw\Sailor\Exceptions\SailorException;
use Yourivw\Sailor\SailorService;

class SailorServiceTest extends TestCase
{
    use WithWorkbench;

    public function testAfterAddingCallbackFailureThrowsException()
    {
        $service = SailorService::create('test1', __DIR__.'/test1.stub')
            ->callAfterAdding(function () {
                throw new Exception('test');
            });

        /** @var InstallCommand|MockInterface $command */
        $command = $this->mock(InstallCommand::class);
        $compose = [];

        $this->assertThrows(fn () => $service->afterAdding($command, $compose), SailorException::class, 'Failed to run callback');
    }

    public function testAfterPublishingCallbackFailureThrowsException()
    {
        $service = SailorService::create('test1', __DIR__.'/test1.stub')
            ->callAfterPublishing(function () {
                throw new Exception('test');
            });

        /** @var InstallCommand|MockInterface $command */
        $command = $this->mock(InstallCommand::class);
        $compose = [];

        $this->assertThrows(fn () => $service->afterPublishing($command, $compose), SailorException::class, 'Failed to run callback');
    }
}

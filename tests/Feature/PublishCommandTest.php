<?php

namespace Yourivw\Sailor\Tests\Feature;

use Closure;
use Mockery;
use Mockery\MockInterface;
use stdClass;
use Yourivw\Sailor\Console\PublishCommand;

class PublishCommandTest extends CommandTestCase
{
    public function testSuccessfullyPublishing()
    {
        $callback = $this->partialMock(stdClass::class, function (MockInterface $mock) {
            $mock->expects('__invoke')
                ->withArgs([Mockery::type(PublishCommand::class)]);
        });

        $this->registerTestingServices(null, Closure::fromCallable([$callback, '__invoke']));

        $this->artisan('sailor:publish')
            ->assertSuccessful();

        $this->assertFileExists($this->app->basePath('published/test2.stub'));
    }
}

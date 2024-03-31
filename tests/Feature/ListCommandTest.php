<?php

namespace Yourivw\Sailor\Tests\Feature;

class ListCommandTest extends CommandTestCase
{
    public function testSuccessfulListing()
    {
        $this->registerTestingServices();

        $expectedHeaders = ['Name', 'Stub file', 'Used default', 'Needs volume', 'Has publishable files'];
        $expectedRows = [
            ['test1', $this->app->basePath('test1.stub'), 'Yes', 'No', 'No'],
            ['test2', $this->app->basePath('test2.stub'), 'No', 'Yes', 'Yes'],
        ];

        $this->artisan('sailor:list')
            ->assertSuccessful()
            ->expectsTable($expectedHeaders, $expectedRows);
    }
}

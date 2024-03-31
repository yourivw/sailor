<?php

namespace Yourivw\Sailor\Tests\Unit;

use Closure;
use Exception;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use ReflectionProperty;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Yourivw\Sailor\Exceptions\SailorException;
use Yourivw\Sailor\SailorManager;
use Yourivw\Sailor\SailorService;

class SailorManagerTest extends TestCase
{
    use WithWorkbench;

    public function testServiceIsRegistered()
    {
        $service = SailorService::create('test', __DIR__.'/test.stub')
            ->withPublishable([__DIR__.'/test.stub' => __DIR__.'/test.stub']);

        $callback = $this->partialMock(\stdClass::class, function (MockInterface $mock) use ($service) {
            $mock->expects('__invoke')
                ->with($service->publishes(), ['sailor', 'sailor-'.$service->name()]);
        });

        $manager = new SailorManager(Closure::fromCallable([$callback, '__invoke']));
        $manager->register($service);

        $this->assertEquals([$service->name() => $service], $manager->services());
        $this->assertEquals([$service->name()], $manager->serviceNames());
    }

    public function testDefaultServiceNamesAreReturned()
    {
        $service1 = SailorService::create('test1', __DIR__.'/test1.stub');
        $service2 = SailorService::create('test2', __DIR__.'/test2.stub')
            ->useDefault();

        $manager = new SailorManager(fn () => null);
        $manager->register($service1);
        $manager->register($service2);

        $this->assertEquals([$service2->name()], $manager->defaultServices());
    }

    public function testAllServicesAreValidated()
    {
        $service1 = SailorService::create('test1', __DIR__.'/test1.stub');
        $service2 = SailorService::create('test2', __DIR__.'/test2.stub');

        /** @var SailorManager|MockInterface $manager */
        $manager = $this->partialMock(SailorManager::class, function (MockInterface $mock) use ($service1, $service2) {
            $mock->expects('validateService')
                ->with($service1);

            $mock->expects('validateService')
                ->with($service2);
        });

        $manager->register($service1);
        $manager->register($service2);

        $this->assertTrue($manager->validateServices());
    }

    public function testSuccessfulServiceValidation()
    {
        $service = SailorService::create('test1', __DIR__.'/test1.stub');
        $manager = new SailorManager(fn () => null);

        File::expects('exists')
            ->with(__DIR__.'/test1.stub')
            ->andReturnTrue();

        /** @var MockInterface $yaml */
        $yaml = $this->mock(Yaml::class, function (MockInterface $mock) {
            $mock->expects('parseFile')
                ->andReturn(['test1' => []]);
        });

        $this->app->bind(Yaml::class, fn () => $yaml);

        $manager->validateService($service);

        $this->app->offsetUnset(Yaml::class);
    }

    public function testInvalidStubFilePathThrowsException()
    {
        $service = SailorService::create('test1', __DIR__.'/test1.stub');
        $manager = new SailorManager(fn () => null);

        File::expects('exists')
            ->with(__DIR__.'/test1.stub')
            ->andReturnFalse();

        $this->assertThrows(fn () => $manager->validateService($service), SailorException::class, 'not found');
    }

    public function testInvalidStubFileThrowsException()
    {
        $service = SailorService::create('test1', __DIR__.'/test1.stub');
        $manager = new SailorManager(fn () => null);

        File::expects('exists')
            ->with(__DIR__.'/test1.stub')
            ->andReturnTrue();

        /** @var MockInterface $yaml */
        $yaml = $this->mock(Yaml::class, function (MockInterface $mock) {
            $mock->expects('parseFile')
                ->andThrow(new ParseException('Test'));
        });

        $this->app->bind(Yaml::class, fn () => $yaml);

        $this->assertThrows(fn () => $manager->validateService($service), SailorException::class, 'invalid');

        $this->app->offsetUnset(Yaml::class);
    }

    public function testStubFileMissingServiceNameThrowsException()
    {
        $service = SailorService::create('test1', __DIR__.'/test1.stub');
        $manager = new SailorManager(fn () => null);

        File::expects('exists')
            ->with(__DIR__.'/test1.stub')
            ->andReturnTrue();

        /** @var MockInterface $yaml */
        $yaml = $this->mock(Yaml::class, function (MockInterface $mock) {
            $mock->expects('parseFile')
                ->andReturn(['test' => []]);
        });

        $this->app->bind(Yaml::class, fn () => $yaml);

        $this->assertThrows(fn () => $manager->validateService($service), SailorException::class, 'missing service key');

        $this->app->offsetUnset(Yaml::class);
    }

    public function testSailDefaultServicesCanBeSet()
    {
        /** @var SailorManager|MockInterface $manager */
        $manager = $this->partialMock(SailorManager::class, function (MockInterface $mock) {
            $mock->shouldReceive('sailServices')
                ->andReturn(['test1', 'test2', 'test3']);
        });

        $manager->setSailDefaultServices(['test2', 'test3', 'test4', 5]);

        $this->assertEquals(['test2', 'test3'], $manager->sailDefaultServices());
    }

    public function testSailDefaultServicesCanBeCleared()
    {
        $manager = new SailorManager(fn () => null);
        $manager->clearSailDefaultServices();

        $this->assertEquals([], $manager->sailDefaultServices());
    }

    public function testSailServicesAreFoundUsingReflection()
    {
        $manager = new SailorManager(fn () => null);

        $property = new ReflectionProperty($manager, 'sailServices');
        $this->assertNull($property->getDefaultValue());

        $services = $manager->sailServices();
        $this->assertNotEmpty($services);
        $this->assertEquals($property->getValue($manager), $services);
    }

    public function testSailServicesReflectionFailureThrowsException()
    {
        $manager = new SailorManager(fn () => null);

        $property = $this->partialMock(ReflectionProperty::class, function (MockInterface $mock) {
            $mock->shouldReceive('getDefaultValue')
                ->andThrow(new Exception('test'));
        });

        $this->app->bind(ReflectionProperty::class, fn () => $property);

        $this->assertThrows(fn () => $manager->sailServices(), SailorException::class);

        $this->app->offsetUnset(ReflectionProperty::class);
    }

    public function testSailServicesAreFoundFromProperty()
    {
        $manager = new SailorManager(fn () => null);

        $property = new ReflectionProperty($manager, 'sailServices');
        $this->assertNull($property->getDefaultValue());

        $expectation = ['test1', 'test2'];
        $property->setValue($manager, $expectation);
        $this->assertEquals($expectation, $manager->sailServices());
    }

    public function testSailDefaultServicesAreFoundUsingReflection()
    {
        $manager = new SailorManager(fn () => null);

        $property = new ReflectionProperty($manager, 'sailDefaultServices');
        $this->assertNull($property->getDefaultValue());

        $services = $manager->sailDefaultServices();
        $this->assertNotEmpty($services);
        $this->assertEquals($property->getValue($manager), $services);
    }

    public function testSailDefaultServicesReflectionFailureThrowsException()
    {
        $manager = new SailorManager(fn () => null);

        $property = $this->partialMock(ReflectionProperty::class, function (MockInterface $mock) {
            $mock->shouldReceive('getDefaultValue')
                ->andThrow(new Exception('test'));
        });

        $this->app->bind(ReflectionProperty::class, fn () => $property);

        $this->assertThrows(fn () => $manager->sailDefaultServices(), SailorException::class);

        $this->app->offsetUnset(ReflectionProperty::class);
    }

    public function testSailDefaultServicesAreFoundFromProperty()
    {
        $manager = new SailorManager(fn () => null);

        $property = new ReflectionProperty($manager, 'sailDefaultServices');
        $this->assertNull($property->getDefaultValue());

        $expectation = ['test1', 'test2'];
        $property->setValue($manager, $expectation);
        $this->assertEquals($expectation, $manager->sailDefaultServices());
    }

    public function testAllServicesAreMerged()
    {
        /** @var SailorManager|MockInterface $manager */
        $manager = $this->partialMock(SailorManager::class, function (MockInterface $mock) {
            $mock->shouldReceive('sailServices')
                ->andReturn(['test1', 'test2', 'test3']);

            $mock->shouldReceive('serviceNames')
                ->andReturn(['test1', 'test4', 'test5']);
        });

        // Validate two lists are merged, and that 'test1' is overridden by Sailor and therefore ordered in
        // between the Sailor services.
        $this->assertEquals(['test2', 'test3', 'test1', 'test4', 'test5'], $manager->allServices()->toArray());
    }

    public function testAllDefaultServicesAreMerged()
    {
        /** @var SailorManager|MockInterface $manager */
        $manager = $this->partialMock(SailorManager::class, function (MockInterface $mock) {
            $mock->shouldReceive('sailDefaultServices')
                ->andReturn(['test1', 'test2', 'test3']);

            $mock->shouldReceive('defaultServices')
                ->andReturn(['test1', 'test4', 'test5']);
        });

        $this->assertEquals(['test1', 'test2', 'test3', 'test4', 'test5'], $manager->allDefaultServices()->toArray());
    }

    public function testSailServicesAreFilteredFromCollection()
    {
        /** @var SailorManager|MockInterface $manager */
        $manager = $this->partialMock(SailorManager::class, function (MockInterface $mock) {
            $mock->shouldReceive('sailServices')
                ->andReturn(['test1', 'test2', 'test3']);

            $mock->shouldReceive('serviceNames')
                ->andReturn(['test3', 'test4', 'test5']);
        });

        // Validate only Sail services are returned, and service 'test3' overwritten by Sailor is filtered out.
        $filtered = $manager->filterSailServices(collect(['test1', 'test2', 'test3', 'test4']))->toArray();
        $this->assertEquals(['test1', 'test2'], $filtered);
    }

    public function testSailorServicesAreFilteredFromCollection()
    {
        /** @var SailorManager|MockInterface $manager */
        $manager = $this->partialMock(SailorManager::class, function (MockInterface $mock) {
            $mock->shouldReceive('sailServices')
                ->andReturn(['test1', 'test2', 'test3']);

            $mock->shouldReceive('serviceNames')
                ->andReturn(['test3', 'test4', 'test5']);

            $mock->shouldReceive('services')
                ->andReturn([
                    'test3' => 'test3',
                    'test4' => 'test4',
                    'test5' => 'test5',
                ]);
        });

        $filtered = $manager->filterSailorServices(collect(['test1', 'test2', 'test3', 'test4']))->toArray();
        $this->assertEquals(['test3' => 'test3', 'test4' => 'test4'], $filtered);
    }

    public function testSettingAndGettingDefaultServiceName()
    {
        $serviceName = 'test.service';

        $manager = new SailorManager(fn () => null);
        $manager->setDefaultServiceName($serviceName);
        $this->assertEquals($serviceName, $manager->defaultServiceName());
    }
}

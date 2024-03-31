<?php

namespace Yourivw\Sailor;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Laravel\Sail\Console\Concerns\InteractsWithDockerComposeServices;
use ReflectionProperty;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Throwable;
use Yourivw\Sailor\Contracts\Serviceable;
use Yourivw\Sailor\Exceptions\SailorException;

class SailorManager
{
    /**
     * Provider's publish callback.
     */
    protected Closure $publishCallback;

    /**
     * All available Sail services.
     */
    protected ?array $sailServices = null;

    /**
     *  Sail services listed as default.
     */
    protected ?array $sailDefaultServices = null;

    /**
     * Registered Sailor services.
     *
     * @var array|Serviceable[]
     */
    protected array $services = [];

    /**
     * Default installation Laravel service name.
     */
    protected string $defaultServiceName = 'laravel.test';

    /**
     * Create a new service manager instance.
     */
    public function __construct(Closure $publishCallback)
    {
        $this->publishCallback = $publishCallback;
    }

    /**
     * Register a new Sailor service with the manager.
     *
     * @return void
     */
    public function register(Serviceable $service)
    {
        $this->services[$service->name()] = $service;

        if (! empty($publishes = $service->publishes())) {
            ($this->publishCallback)($publishes, ['sailor', 'sailor-'.$service->name()]);
        }
    }

    /**
     * Return the registered Sailor services.
     *
     * @return array|Serviceable[]
     */
    public function services(): array
    {
        return $this->services;
    }

    /**
     * Return the registered Sailor service names.
     */
    public function serviceNames(): array
    {
        return array_keys($this->services);
    }

    /**
     * Return the Sailor default services.
     */
    public function defaultServices(): array
    {
        return collect($this->services)
            ->filter(function (Serviceable $service) {
                return $service->isUsedDefault();
            })
            ->keys()
            ->toArray();
    }

    /**
     * Run validation on all the registered services.
     */
    public function validateServices(): bool
    {
        foreach ($this->services() as $service) {
            $this->validateService($service);
        }

        return true;
    }

    /**
     * Validate the service, by checking the existance and validity of the stub file.
     *
     * @return void
     *
     * @throws SailorException
     */
    public function validateService(Serviceable $servicable)
    {
        if (! File::exists($servicable->stubFilePath())) {
            throw new SailorException(sprintf('Stub file not found for \'%s\' service.', $servicable->name()));
        }

        try {
            $yamlFile = app(Yaml::class)->parseFile($servicable->stubFilePath());
        } catch (ParseException $exception) {
            throw new SailorException(
                sprintf('Stub file for \'%s\' service is invalid: '.$exception->getMessage(), $servicable->name()),
                0,
                $exception
            );
        }

        if (! array_key_exists($servicable->name(), $yamlFile)) {
            throw new SailorException(sprintf('Stub file for \'%s\' service is invalid, missing service key.', $servicable->name()));
        }
    }

    /**
     * Set the wanted default Sail service(s). Invalid service names are filtered out.
     *
     * @param  string|array  $defaults
     * @return void
     */
    public function setSailDefaultServices(...$defaults)
    {
        if (is_array($defaults[0])) {
            $defaults = $defaults[0];
        }

        $this->sailDefaultServices = array_filter($defaults, function ($default) {
            if (! is_string($default)) {
                return false;
            }

            return in_array($default, $this->sailServices());
        });
    }

    /**
     * Clear the list of Sail default services.
     *
     * @return void
     */
    public function clearSailDefaultServices()
    {
        $this->sailDefaultServices = [];
    }

    /**
     * Get all available Sail services.
     *
     * @throws SailorException
     */
    public function sailServices(): array
    {
        if (! is_null($this->sailServices)) {
            return $this->sailServices;
        }

        try {
            /** @var ReflectionProperty $property */
            $property = app()->make(ReflectionProperty::class, [
                'class' => InteractsWithDockerComposeServices::class,
                'property' => 'services']
            );

            return $this->sailServices = (array) $property->getDefaultValue();
        } catch (Throwable $exception) {
            throw new SailorException('Failed to retrieve Sail services: '.$exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Get the Sail services listed as default.
     *
     * @throws SailorException
     */
    public function sailDefaultServices(): array
    {
        if (! is_null($this->sailDefaultServices)) {
            return $this->sailDefaultServices;
        }

        try {
            /** @var ReflectionProperty $property */
            $property = app()->make(ReflectionProperty::class, [
                'class' => InteractsWithDockerComposeServices::class,
                'property' => 'defaultServices']
            );

            return $this->sailDefaultServices = (array) $property->getDefaultValue();
        } catch (Throwable $exception) {
            throw new SailorException('Failed to retrieve Sail default services: '.$exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Get the merged list of all services.
     */
    public function allServices(): Collection
    {
        $sailorServices = $this->serviceNames();

        return collect($this->sailServices())
            ->filter(function (string $service) use ($sailorServices) {
                return ! in_array($service, $sailorServices);
            })
            ->merge($sailorServices);
    }

    /**
     * Get the merged list of all default services.
     */
    public function allDefaultServices(): Collection
    {
        return collect($this->sailDefaultServices())
            ->merge($this->defaultServices())
            ->unique()
            ->values();
    }

    /**
     * Filter a list of services, which are handled by Sail. A service overridden through Sailor is skipped.
     */
    public function filterSailServices(Collection $services): Collection
    {
        return $services->filter(function (string $serviceName) {
            return in_array($serviceName, $this->sailServices()) && ! in_array($serviceName, $this->serviceNames());
        });
    }

    /**
     * Filter a list of services, which are handled by Sailor.
     */
    public function filterSailorServices(Collection $services): Collection
    {
        return $services->filter(function (string $serviceName) {
            return in_array($serviceName, $this->serviceNames());
        })->mapWithKeys(function (string $serviceName) {
            return [$serviceName => $this->services()[$serviceName]];
        });
    }

    /**
     * Get the default name for the Laravel service.
     */
    public function defaultServiceName(): string
    {
        return $this->defaultServiceName;
    }

    /**
     * Set the default name for the Laravel service.
     */
    public function setDefaultServiceName(string $serviceName)
    {
        $this->defaultServiceName = $serviceName;
    }
}

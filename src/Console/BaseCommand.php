<?php

namespace Yourivw\Sailor\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;
use Yourivw\Sailor\Contracts\Serviceable;
use Yourivw\Sailor\SailorManager;

abstract class BaseCommand extends Command
{
    /**
     * The service manager.
     */
    protected SailorManager $sailorManager;

    /**
     * Yaml parser.
     */
    protected Yaml $yamlParser;

    /**
     * Create a new console command instance.
     *
     * @return void
     */
    public function __construct(SailorManager $sailorManager, Yaml $yamlParser)
    {
        parent::__construct();

        $this->sailorManager = $sailorManager;
        $this->yamlParser = $yamlParser;
    }

    /**
     * Check wheter Laravel Prompts (multiselect) is installed.
     */
    protected function multiselectExists(): bool
    {
        return function_exists('\Laravel\Prompts\multiselect');
    }

    /**
     * Gather the desired Sail services using an interactive prompt.
     */
    protected function gatherServicesInteractively(): Collection
    {
        $availableServices = $this->sailorManager->allServices();

        if ($this->multiselectExists()) {
            return collect(\Laravel\Prompts\multiselect(
                label: 'Which services would you like to install?',
                options: $availableServices,
                default: $this->sailorManager->allDefaultServices()
            ));
        }

        return collect($this->choice('Which services would you like to install?', $availableServices->toArray(), 0, null, true));
    }

    /**
     * Build the Docker Compose file.
     *
     * @return void
     */
    protected function buildSailorDockerCompose(Collection $services)
    {
        $dockerComposePath = $this->laravel->basePath('docker-compose.yml');

        $compose = $this->yamlParser->parseFile($dockerComposePath);
        $serviceName = $this->findLaravelServiceName($compose);

        // Adds the new services as dependencies of the laravel service...
        if (is_null($serviceName) || ! array_key_exists($serviceName, $compose['services'])) {
            $this->warn('Couldn\'t find the laravel service. Make sure you add ['.$services->implode(', ').'] to the depends_on config.');
        } else {
            $compose['services'][$serviceName]['depends_on'] = collect($compose['services'][$serviceName]['depends_on'] ?? [])
                ->merge($services->keys())
                ->unique()
                ->values()
                ->all();
        }

        // Add the services to the docker-compose.yml...
        $addedServices = $services->filter(function (Serviceable $service) use ($compose) {
            return ! array_key_exists($service->name(), $compose['services'] ?? []);
        })->each(function (Serviceable $service) use (&$compose) {
            $serviceName = $service->name();
            $compose['services'][$serviceName] = $this->yamlParser->parseFile($service->stubFilePath())[$serviceName];
        });

        // Merge volumes...
        $services->filter(function (Serviceable $service) {
            return $service->needsVolume();
        })->filter(function (Serviceable $service) use ($compose) {
            return ! array_key_exists($service->name(), $compose['volumes'] ?? []);
        })->each(function (Serviceable $service) use (&$compose) {
            $compose['volumes']["sailor-{$service->name()}"] = ['driver' => 'local'];
        });

        // If the list of volumes is empty, we can remove it...
        if (empty($compose['volumes'])) {
            unset($compose['volumes']);
        }

        // Before saving, run the after callbacks for newly added services.
        $addedServices->each(function (Serviceable $service) use (&$compose) {
            $service->afterAdding($this, $compose);
        });

        File::put($this->laravel->basePath('docker-compose.yml'), $this->yamlParser->dump($compose, Yaml::DUMP_OBJECT_AS_MAP));
    }

    /**
     * Find the current Laravel service name in the docker-compose file.
     */
    protected function findLaravelServiceName(array $compose): ?string
    {
        return collect($compose['services'])
            ->filter(function ($service) {
                return Arr::get($service, 'environment.LARAVEL_SAIL') === 1;
            })
            ->keys()
            ->first();
    }

    /**
     * Rename the Laravel service in the docker-compose and .env files.
     */
    protected function renameLaravelService(string $newServiceName): bool
    {
        $dockerComposePath = $this->laravel->basePath('docker-compose.yml');
        $compose = $this->yamlParser->parseFile($dockerComposePath);
        $currentServiceName = $this->findLaravelServiceName($compose);
        if (is_null($currentServiceName)) {
            $this->error('Laravel service was not found, and therefore could not be (re)named.');

            return false;
        }

        $services = collect($compose['services'])
            ->mapWithKeys(function ($service, $name) use ($newServiceName, $currentServiceName, &$serviceFound) {
                return [
                    ($name === $currentServiceName ? $newServiceName : $name) => $service,
                ];
            });

        $compose['services'] = $services->toArray();
        File::put($dockerComposePath, $this->yamlParser->dump($compose, Yaml::DUMP_OBJECT_AS_MAP));

        $environment = File::get($this->laravel->basePath('.env'));

        if (Str::contains($environment, 'APP_SERVICE')) {
            $environment = preg_replace('/APP_SERVICE=.*/', 'APP_SERVICE='.$newServiceName, $environment);
        } else {
            $environment .= "\nAPP_SERVICE=".$newServiceName."\n";
        }

        File::put($this->laravel->basePath('.env'), $environment);

        return true;
    }
}

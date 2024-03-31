<?php

namespace Yourivw\Sailor\Console;

use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Output\BufferedOutput;

class InstallCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sailor:install
                {--name= : The name of the Laravel service}
                {--with= : The services that should be included in the installation}
                {--devcontainer : Create a .devcontainer configuration directory}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Laravel Sail\'s default Docker Compose file using Sailor';

    /**
     * Execute the console command.
     *
     * @return int|null
     */
    public function handle()
    {
        $this->sailorManager->validateServices();

        $composePath = base_path('docker-compose.yml');
        if (File::exists($composePath)) {
            $this->error('A docker-compose file already exists, an installation has run already.');

            return 1;
        }

        if ($this->option('name')) {
            $serviceName = $this->option('name');
        } elseif ($this->option('no-interaction')) {
            $serviceName = $this->sailorManager->defaultServiceName();
        } else {
            $serviceName = $this->ask('What\'s the name for the Laravel service?', $this->sailorManager->defaultServiceName());
        }

        if ($this->option('with')) {
            $services = collect($this->option('with') == 'none' ? [] : explode(',', $this->option('with')));
        } elseif ($this->option('no-interaction')) {
            $services = $this->sailorManager->allDefaultServices();
        } else {
            $services = $this->gatherServicesInteractively();
        }

        $invalidServices = $services->diff($this->sailorManager->allServices());
        if ($invalidServices->isNotEmpty()) {
            $this->error('Invalid services ['.$invalidServices->implode(', ').'].');

            return 1;
        }

        $sailServices = $this->sailorManager->filterSailServices($services);
        $sailorServices = $this->sailorManager->filterSailorServices($services);

        $buffer = new BufferedOutput(
            (int) $this->output->getVerbosity(),
            $this->output->isDecorated(),
            $this->output->getFormatter()
        );

        $sailResult = $this->runCommand('sail:install', [
            '--with' => $sailServices->join(','),
            '--devcontainer' => $this->option('devcontainer'),
        ], $buffer);

        if ($sailResult > 0) {
            $this->error('An error occurred while installing Sail.');
            $this->output->write($buffer->fetch());

            return $sailResult;
        }

        $this->output->write($buffer->fetch());

        if ($sailorServices->isNotEmpty()) {
            $this->buildSailorDockerCompose($sailorServices);
        }

        $this->renameLaravelService($serviceName);

        $this->output->writeln('');
        $this->info('Sailor services installed successfully.');
    }
}

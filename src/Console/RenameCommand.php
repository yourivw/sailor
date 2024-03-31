<?php

namespace Yourivw\Sailor\Console;

use Illuminate\Support\Facades\File;

class RenameCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sailor:rename
        {name? : The new name for the Laravel service}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rename the Laravel service on the existing Sail installation';

    /**
     * Execute the console command.
     *
     * @return int|null
     */
    public function handle()
    {
        $this->sailorManager->validateServices();

        $composePath = base_path('docker-compose.yml');
        if (! File::exists($composePath)) {
            $this->error('A docker-compose file does not exist yet, run sailor:install first.');

            return 1;
        }

        if ($this->argument('name')) {
            $serviceName = $this->argument('name');
        } elseif ($this->option('no-interaction')) {
            $serviceName = $this->sailorManager->defaultServiceName();
        } else {
            $serviceName = $this->ask('What\'s the new name for the Laravel service?', $this->sailorManager->defaultServiceName());
        }

        if (! $this->renameLaravelService($serviceName)) {
            $this->error('Failed to rename the Laravel service in the docker-compose file.');

            return 1;
        }

        $this->output->writeln('');
        $this->info('Laravel Sail service renamed successfully.');
    }
}

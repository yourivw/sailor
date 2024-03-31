<?php

namespace Yourivw\Sailor\Console;

use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Output\BufferedOutput;

class AddCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sailor:add
        {services? : The services that should be added}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add a service to an existing Sail installation using Sailor';

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

        if ($this->argument('services')) {
            $services = collect($this->argument('services') == 'none' ? [] : explode(',', $this->argument('services')));
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

        if ($sailServices->isNotEmpty()) {
            $buffer = new BufferedOutput(
                (int) $this->output->getVerbosity(),
                $this->output->isDecorated(),
                $this->output->getFormatter()
            );

            $sailResult = $this->runCommand('sail:add', [
                'services' => $sailServices->join(','),
            ], $buffer);

            if ($sailResult > 0) {
                $this->error('An error occurred while adding Sail services.');
                $this->output->write($buffer->fetch());

                return $sailResult;
            }
        }

        if (isset($buffer)) {
            $this->output->write($buffer->fetch());
        }

        if ($sailorServices->isNotEmpty()) {
            $this->buildSailorDockerCompose($sailorServices);
        }

        $this->output->writeln('');
        $this->info('Additional Sailor services installed successfully.');
    }
}

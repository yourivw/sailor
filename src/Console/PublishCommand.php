<?php

namespace Yourivw\Sailor\Console;

use Illuminate\Console\Command;
use Yourivw\Sailor\Contracts\Serviceable;
use Yourivw\Sailor\SailorManager;

class PublishCommand extends Command
{
    protected SailorManager $sailorManager;

    /**
     * Create a new console command instance.
     *
     * @return void
     */
    public function __construct(SailorManager $sailorManager)
    {
        parent::__construct();

        $this->sailorManager = $sailorManager;
    }

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sailor:publish
            {--services= : The name of the service(s) to publish files for}
        ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish the Sailor service files';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->sailorManager->validateServices();

        $success = false;

        if ($serviceNames = $this->option('services')) {
            $services = $this->sailorManager->services();

            foreach (explode(',', $serviceNames) as $serviceName) {
                $service = $services[$serviceName] ?? null;
                if (! $service instanceof Serviceable) {
                    $this->error(sprintf('Service \'%s\' does not exist, no files published.', $serviceName));

                    continue;
                }

                $this->publishService($service);
                $success = true;
            }
        } else {
            $this->publishAll();
            $success = true;
        }

        if (! $success) {
            $this->error('No files could be published.');

            return 1;
        }
    }

    /**
     * Publish for all services.
     *
     * @return void
     */
    protected function publishAll()
    {
        $this->call('vendor:publish', ['--tag' => 'sailor']);

        /** @var Serviceable $service */
        foreach ($this->sailorManager->services() as $service) {
            $service->afterPublishing($this);
        }

        $this->info('Files published for all services.');
    }

    /**
     * Publish for a specified service.
     *
     * @return void
     */
    protected function publishService(Serviceable $service)
    {
        $this->call('vendor:publish', ['--tag' => 'sailor-'.$service->name()]);

        $service->afterPublishing($this);

        $this->info(sprintf('Files published for service \'%s\'.', $service->name()));
    }
}

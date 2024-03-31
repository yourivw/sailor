<?php

namespace Yourivw\Sailor\Console;

use Illuminate\Console\Command;
use Yourivw\Sailor\Contracts\Serviceable;
use Yourivw\Sailor\SailorManager;

class ListCommand extends Command
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
    protected $signature = 'sailor:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List the registered Sailor services';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->sailorManager->validateServices();

        $rows = collect($this->sailorManager->services())
            ->map(function (Serviceable $service) {
                return [
                    $service->name(),
                    realpath($service->stubFilePath()),
                    $service->isUsedDefault() ? 'Yes' : 'No',
                    $service->needsVolume() ? 'Yes' : 'No',
                    ! empty($service->publishes()) ? 'Yes' : 'No',
                ];
            });

        $this->table(['Name', 'Stub file', 'Used default', 'Needs volume', 'Has publishable files'], $rows);
    }
}

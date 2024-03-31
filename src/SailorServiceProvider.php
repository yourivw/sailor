<?php

namespace Yourivw\Sailor;

use Closure;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Yourivw\Sailor\Console\AddCommand;
use Yourivw\Sailor\Console\InstallCommand;
use Yourivw\Sailor\Console\ListCommand;
use Yourivw\Sailor\Console\PublishCommand;
use Yourivw\Sailor\Console\RenameCommand;

class SailorServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(SailorManager::class, function () {
            return new SailorManager(Closure::fromCallable([$this, 'publishes']));
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerCommands();
    }

    /**
     * Register the console commands for the package.
     *
     * @return void
     */
    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AddCommand::class,
                InstallCommand::class,
                ListCommand::class,
                PublishCommand::class,
                RenameCommand::class,
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            SailorManager::class,
            AddCommand::class,
            InstallCommand::class,
            ListCommand::class,
            PublishCommand::class,
            RenameCommand::class,
        ];
    }
}

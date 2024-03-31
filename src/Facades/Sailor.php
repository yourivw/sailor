<?php

namespace Yourivw\Sailor\Facades;

use Illuminate\Support\Facades\Facade;
use Yourivw\Sailor\SailorManager;

/**
 * @method static void register(\Yourivw\Sailor\Contracts\Serviceable $service) Register a new Sailor service with the manager.
 * @method static array|\Yourivw\Sailor\Contracts\Serviceable[] services() Return the registered Sailor services.
 * @method static array serviceNames() Return the registered Sailor service names.
 * @method static array defaultServices() Return the Sailor default services.
 * @method static bool validateServices() Run validation on all the registered services.
 * @method static void validateService(\Yourivw\Sailor\Contracts\Serviceable $servicable) Validate the service, by checking the existance and validity of the stub file.
 * @method static void setSailDefaultServices(...$defaults) Set the wanted default Sail service(s). Invalid service names are filtered out.
 * @method static void clearSailDefaultServices() Clear the list of Sail default services.
 * @method static array sailServices() Get all available Sail services.
 * @method static array sailDefaultServices() Get the Sail services listed as default.
 * @method static \Illuminate\Support\Collection allServices() Get the merged list of all services.
 * @method static \Illuminate\Support\Collection allDefaultServices() Get the merged list of all default services.
 * @method static \Illuminate\Support\Collection filterSailServices(\Illuminate\Support\Collection $services) Filter a list of services, which are handled by Sail. A service overridden through Sailor is skipped.
 * @method static \Illuminate\Support\Collection filterSailorServices(\Illuminate\Support\Collection $services) Filter a list of services, which are handled by Sailor.
 * @method static string defaultServiceName() Get the default name for the Laravel service.
 * @method static void setDefaultServiceName(string $serviceName) Set the default name for the Laravel service.
 *
 * @see \Yourivw\Sailor\SailorManager
 */
class Sailor extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return SailorManager::class;
    }
}

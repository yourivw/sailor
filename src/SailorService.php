<?php

namespace Yourivw\Sailor;

use Closure;
use Illuminate\Console\Command;
use Throwable;
use Yourivw\Sailor\Contracts\Serviceable;
use Yourivw\Sailor\Exceptions\SailorException;

class SailorService implements Serviceable
{
    /**
     * Service name.
     */
    protected string $name;

    /**
     * Service stub file path.
     */
    protected string $stubFilePath;

    /**
     * Whether the service needs a Docker volume.
     */
    protected bool $needsVolume = false;

    /**
     * Whether the service should be installed by default.
     */
    protected bool $isUsedDefault = false;

    /**
     * Publishable files.
     */
    protected array $publishes = [];

    /**
     * Callback ran after this service was added.
     */
    protected ?Closure $afterAddingCallback = null;

    /**
     * Callback ran after this service files were published.
     */
    protected ?Closure $afterPublishingCallback = null;

    /**
     * Create a new service instance.
     */
    public function __construct(string $name, string $stubFilePath)
    {
        $this->name = $name;
        $this->stubFilePath = $stubFilePath;
    }

    /**
     * Get the service name.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Get the service stub file path.
     */
    public function stubFilePath(): string
    {
        return $this->stubFilePath;
    }

    /**
     * Include a Docker volume for the service.
     */
    public function withVolume(bool $needsVolume = true): static
    {
        $this->needsVolume = $needsVolume;

        return $this;
    }

    /**
     * Get whether the service needs a Docker volume.
     */
    public function needsVolume(): bool
    {
        return $this->needsVolume;
    }

    /**
     * Get whether the service should be installed by default.
     */
    public function useDefault(bool $isUsedDefault = true): static
    {
        $this->isUsedDefault = $isUsedDefault;

        return $this;
    }

    /**
     * Set the service as default for installation.
     */
    public function isUsedDefault(): bool
    {
        return $this->isUsedDefault;
    }

    /**
     * Set the publishable files.
     */
    public function withPublishable(array $publishes): static
    {
        $this->publishes = $publishes;

        return $this;
    }

    /**
     * Get the publishable files.
     */
    public function publishes(): array
    {
        return $this->publishes;
    }

    /**
     * Set a callback to be ran after this service was added. Only runs when the service did not yet exist in the Dockerfile.
     * The callback receives two argument, the executing Artisan command, and the array containing the content of the Dockerfile
     * formatted by Symfony\Component\Yaml\Yaml. The contents of this array can be modified and will be saved.
     *
     * @return $this
     */
    public function callAfterAdding(Closure $afterAddingCallback): static
    {
        $this->afterAddingCallback = $afterAddingCallback;

        return $this;
    }

    /**
     * Callback ran after this service was added. Only runs when the service did not yet exist in the Dockerfile.
     *
     * @return void
     *
     * @throws SailorException
     */
    public function afterAdding(Command $command, array &$compose)
    {
        if (is_callable($this->afterAddingCallback)) {
            try {
                ($this->afterAddingCallback)($command, $compose);
            } catch (Throwable $exception) {
                throw new SailorException(
                    'Failed to run callback after adding service: '.$exception->getMessage(),
                    0,
                    $exception
                );
            }
        }
    }

    /**
     * Set a callback to be ran after this service files were published. The callback receives one argument, the executing
     * Artisan command.
     *
     * @return $this
     */
    public function callAfterPublishing(Closure $afterPublishingCallback): static
    {
        $this->afterPublishingCallback = $afterPublishingCallback;

        return $this;
    }

    /**
     * Callback ran after this service files were published.
     *
     * @return void
     *
     * @throws SailorException
     */
    public function afterPublishing(Command $command)
    {
        if (is_callable($this->afterPublishingCallback)) {
            try {
                ($this->afterPublishingCallback)($command);
            } catch (Throwable $exception) {
                throw new SailorException(
                    'Failed to run callback for publishing service files: '.$exception->getMessage(),
                    0,
                    $exception
                );
            }
        }
    }

    /**
     * Internally register the current service with the manager.
     *
     * @return void
     */
    public function register()
    {
        app(SailorManager::class)->register($this);
    }

    /**
     * Fluently create a new service instance.
     */
    public static function create(string $name, string $stubFilePath): self
    {
        return new self($name, $stubFilePath);
    }
}

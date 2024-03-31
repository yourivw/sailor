<?php

namespace Yourivw\Sailor\Contracts;

use Illuminate\Console\Command;

interface Serviceable
{
    /**
     * Get the service name.
     */
    public function name(): string;

    /**
     * Get the service stub file path.
     */
    public function stubFilePath(): string;

    /**
     * Get whether the service needs a Docker volume.
     */
    public function needsVolume(): bool;

    /**
     * Set the service as default for installation.
     */
    public function isUsedDefault(): bool;

    /**
     * Get the publishable files.
     */
    public function publishes(): ?array;

    /**
     * Callback ran after this service was added. Only runs when the service did not yet exist in the Dockerfile.
     *
     * @return void
     */
    public function afterAdding(Command $command, array &$compose);

    /**
     * Callback ran after this service files were published.
     *
     * @return void
     */
    public function afterPublishing(Command $command);
}

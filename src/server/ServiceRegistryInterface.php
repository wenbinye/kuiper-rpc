<?php

declare(strict_types=1);

namespace kuiper\rpc\server;

interface ServiceRegistryInterface
{
    /**
     * @param Service $service
     */
    public function register(Service $service): void;

    /**
     * @param Service $service
     */
    public function deregister(Service $service): void;
}

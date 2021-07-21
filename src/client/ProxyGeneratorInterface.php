<?php

declare(strict_types=1);

namespace kuiper\rpc\client;

interface ProxyGeneratorInterface
{
    /**
     * @return string
     *
     * @throws \ReflectionException
     */
    public function generate(string $interfaceName, array $context = []): GeneratedClass;
}

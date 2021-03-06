<?php

declare(strict_types=1);

namespace kuiper\rpc\client;

use kuiper\reflection\ReflectionDocBlockFactoryInterface;
use kuiper\reflection\ReflectionType;
use kuiper\reflection\ReflectionTypeInterface;
use kuiper\reflection\type\VoidType;
use kuiper\rpc\RpcMethodInterface;
use kuiper\serializer\NormalizerInterface;
use Webmozart\Assert\Assert;

class RpcResponseNormalizer
{
    /**
     * @var NormalizerInterface
     */
    private $normalizer;

    /**
     * @var ReflectionDocBlockFactoryInterface
     */
    private $reflectionDocBlockFactory;

    /**
     * @var array
     */
    private $cachedTypes;

    /**
     * JsonRpcSerializerResponseFactory constructor.
     */
    public function __construct(NormalizerInterface $normalizer, ReflectionDocBlockFactoryInterface $reflectionDocBlockFactory)
    {
        $this->normalizer = $normalizer;
        $this->reflectionDocBlockFactory = $reflectionDocBlockFactory;
    }

    /**
     * @param RpcMethodInterface $method
     * @param array              $result
     *
     * @return array
     */
    public function normalize(RpcMethodInterface $method, array $result): array
    {
        [$returnType, $outParamTypes] = $this->getMethodReturnTypes($method);
        if (empty($outParamTypes)) {
            if (isset($returnType)) {
                return [$this->normalizer->denormalize($result[0], $returnType)];
            }

            return [null];
        }
        $ret = [];
        Assert::count($result, count($outParamTypes) + 1);
        if (isset($result[''])) {
            if (null !== $returnType) {
                $ret[] = $this->normalizer->denormalize($result[''], $returnType);
            } else {
                $ret[] = null;
            }
            foreach ($outParamTypes as $paramName => $type) {
                $ret[] = $this->normalizer->denormalize($result[$paramName] ?? null, $type);
            }
        } else {
            if (null !== $returnType) {
                $ret[] = $this->normalizer->denormalize($result[0], $returnType);
            } else {
                $ret[] = null;
            }
            foreach (array_values($outParamTypes) as $i => $type) {
                if (isset($result[$i + 1])) {
                    $ret[] = $this->normalizer->denormalize($result[$i + 1], $type);
                } else {
                    $ret[] = null;
                }
            }
        }

        return $ret;
    }

    private function getMethodReturnTypes(RpcMethodInterface $method): array
    {
        $key = (string) $method;
        if (isset($this->cachedTypes[$key])) {
            return $this->cachedTypes[$key];
        }
        $reflectionMethod = new \ReflectionMethod($method->getTarget(), $method->getMethodName());
        $docBlock = $this->reflectionDocBlockFactory->createMethodDocBlock($reflectionMethod);
        $returnType = $this->createType($reflectionMethod->getReturnType(), $docBlock->getReturnType());
        $docParamTypes = $docBlock->getParameterTypes();
        $types = [];
        foreach ($reflectionMethod->getParameters() as $parameter) {
            if ($parameter->isPassedByReference()) {
                $types[$parameter->getName()] = $this->createType($parameter->getType(), $docParamTypes[$parameter->getName()]);
            }
        }

        return $this->cachedTypes[$key] = [$returnType, $types];
    }

    private function createType(?\ReflectionType $phpType, ReflectionTypeInterface $docType): ?ReflectionTypeInterface
    {
        if (null === $phpType) {
            $type = $docType;
        } else {
            $type = ReflectionType::fromPhpType($phpType);
            if ($type->isUnknown()) {
                $type = $docType;
            }
        }

        return $type instanceof VoidType ? null : $type;
    }
}

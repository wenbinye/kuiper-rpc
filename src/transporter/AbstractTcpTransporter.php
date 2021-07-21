<?php

declare(strict_types=1);

namespace kuiper\rpc\transporter;

use kuiper\rpc\exception\CommunicationException;
use kuiper\rpc\exception\ConnectFailedException;
use kuiper\rpc\exception\ConnectionClosedException;
use kuiper\rpc\exception\ConnectionException;
use kuiper\rpc\exception\ErrorCode;
use kuiper\rpc\exception\TimedOutException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

abstract class AbstractTcpTransporter implements TransporterInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected const TAG = '['.__CLASS__.'] ';

    private const ERROR_EXCEPTIONS = [
        ErrorCode::SOCKET_CLOSED => ConnectionClosedException::class,
        ErrorCode::SOCKET_TIMEOUT => TimedOutException::class,
        ErrorCode::SOCKET_CONNECT_FAILED => ConnectFailedException::class,
        ErrorCode::SOCKET_RECEIVE_FAILED => ConnectFailedException::class,
    ];

    /**
     * @var ResponseFactoryInterface
     */
    private $responseFactory;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var mixed
     */
    private $resource;

    /**
     * @var Endpoint
     */
    private $endpoint;

    /**
     * AbstractTcpTransporter constructor.
     */
    public function __construct(ResponseFactoryInterface $responseFactory, array $options)
    {
        $this->responseFactory = $responseFactory;
        $this->setOptions($options);
    }

    public function getEndpoint(): Endpoint
    {
        return $this->endpoint;
    }

    public function setOptions(array $options): void
    {
        $this->options = array_merge($this->options, $options);
    }

    /**
     * Disconnects from the server and destroys the underlying resource when
     * PHP's garbage collector kicks in.
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    public function isConnected(): bool
    {
        return isset($this->resource);
    }

    /**
     * @throws CommunicationException
     */
    public function connect(Endpoint $endpoint): void
    {
        if (null !== $this->endpoint && !$this->endpoint->equals($endpoint)) {
            $this->disconnect();
        }
        $this->endpoint = $endpoint;
        if (!$this->isConnected()) {
            $this->resource = $this->createResource();
        }
    }

    public function disconnect(): void
    {
        if (isset($this->resource)) {
            $this->destroyResource();
            $this->resource = null;
        }
    }

    /**
     * @return mixed
     */
    protected function getResource()
    {
        return $this->resource;
    }

    /**
     * {@inheritDoc}
     */
    public function send(RequestInterface $request): ResponseInterface
    {
        $endpoint = Endpoint::fromUri($request->getUri());
        $this->connect($endpoint);
        $this->beforeSend();
        try {
            return $this->doSend((string) $request->getBody());
        } finally {
            $this->afterSend();
        }
    }

    protected function createResponse(string $body): ResponseInterface
    {
        $response = $this->responseFactory->createResponse();
        $response->getBody()->write($body);

        return $response;
    }

    /**
     * Helper method to handle connection errors.
     *
     * @throws ConnectionException
     */
    protected function onConnectionError(ErrorCode $errorCode, string $message = null): void
    {
        $exception = $this->createException($errorCode, $message);
        $this->disconnect();

        throw $exception;
    }

    protected function createException(ErrorCode $errorCode, ?string $message): ConnectionException
    {
        $message = ($message ?? $errorCode->message).'(address='.$this->endpoint.')';
        if (array_key_exists($errorCode->value(), self::ERROR_EXCEPTIONS)) {
            $class = self::ERROR_EXCEPTIONS[$errorCode->value()];
            $exception = new $class($this, $message, $errorCode->value());
        } else {
            $exception = new ConnectionException($this, $message, $errorCode->value());
        }

        return $exception;
    }

    /**
     * Creates the underlying resource used to communicate with server.
     *
     * @return mixed
     *
     * @throws CommunicationException
     */
    abstract protected function createResource();

    /**
     * Destroy the underlying resource.
     */
    abstract protected function destroyResource(): void;

    /**
     * @throws CommunicationException
     */
    abstract protected function doSend(string $data): ResponseInterface;

    /**
     * callback before send data.
     */
    protected function beforeSend(): void
    {
    }

    /**
     * callback after send data.
     */
    protected function afterSend(): void
    {
    }
}

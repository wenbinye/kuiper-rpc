<?php

declare(strict_types=1);

namespace kuiper\rpc;

interface RequestInterface extends \Psr\Http\Message\RequestInterface
{
    public function getInvokingMethod(): InvokingMethod;

    /**
     * Retrieve attributes derived from the request.
     *
     * The request "attributes" may be used to allow injection of any
     * parameters derived from the request: e.g., the results of path
     * match operations; the results of decrypting cookies; the results of
     * deserializing non-form-encoded message bodies; etc. Attributes
     * will be application and request specific, and CAN be mutable.
     *
     * @return array attributes derived from the request
     */
    public function getAttributes(): array;

    /**
     * Retrieve a single derived request attribute.
     *
     * Retrieves a single derived request attribute as described in
     * getAttributes(). If the attribute has not been previously set, returns
     * the default value as provided.
     *
     * This method obviates the need for a hasAttribute() method, as it allows
     * specifying a default value to return if the attribute is not found.
     *
     * @see getAttributes()
     *
     * @param string $name    the attribute name
     * @param mixed  $default default value to return if the attribute does not exist
     *
     * @return mixed
     */
    public function getAttribute(string $name, $default = null);

    /**
     * Return an instance with the specified derived request attribute.
     *
     * This method allows setting a single derived request attribute as
     * described in getAttributes().
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * updated attribute.
     *
     * @see getAttributes()
     *
     * @param string $name  the attribute name
     * @param mixed  $value the value of the attribute
     *
     * @return static
     */
    public function withAttribute(string $name, $value);
}

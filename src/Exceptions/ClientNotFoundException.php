<?php

namespace Liqrgv\ShopSync\Exceptions;

use Exception;

/**
 * Exception thrown when a client is not found during product fetcher creation
 *
 * This exception is specifically designed for client validation failures
 * in the ProductFetcherFactory when operating in 'wtm' (Watch the Market) mode.
 * It provides clear indication that the requested client ID does not exist
 * in the system, which is an unrecoverable error requiring immediate attention.
 *
 * @package Liqrgv\ShopSync\Exceptions
 */
class ClientNotFoundException extends Exception
{
    /**
     * The client ID that was not found
     *
     * @var string|null
     */
    protected $clientId;

    /**
     * Create a new ClientNotFoundException instance
     *
     * @param string $message The exception message
     * @param string|null $clientId The client ID that was not found
     * @param int $code The exception code (default: 0)
     * @param Exception|null $previous The previous exception used for exception chaining
     */
    public function __construct($message = '', $clientId = null, $code = 0, Exception $previous = null)
    {
        $this->clientId = $clientId;

        // If no message provided, create a default one
        if (empty($message) && !empty($clientId)) {
            $message = "Client not found: {$clientId}.";
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the client ID that was not found
     *
     * @return string|null
     */
    public function getClientId()
    {
        return $this->clientId;
    }

    /**
     * Set the client ID that was not found
     *
     * @param string|null $clientId
     * @return self
     */
    public function setClientId($clientId)
    {
        $this->clientId = $clientId;
        return $this;
    }

    /**
     * Get a string representation of the exception with client context
     *
     * @return string
     */
    public function __toString()
    {
        $result = parent::__toString();

        if ($this->clientId !== null) {
            $result .= " [Client ID: {$this->clientId}]";
        }

        return $result;
    }

    /**
     * Create a ClientNotFoundException with a specific client ID
     *
     * @param string $clientId The client ID that was not found
     * @param string|null $customMessage Optional custom message
     * @return static
     */
    public static function forClientId($clientId, $customMessage = null)
    {
        $message = $customMessage ?: "Client not found: {$clientId}.";
        return new static($message, $clientId);
    }

    /**
     * Get context information for logging purposes
     *
     * @return array
     */
    public function getContext()
    {
        return [
            'exception' => get_class($this),
            'message' => $this->getMessage(),
            'client_id' => $this->clientId,
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine()
        ];
    }
}
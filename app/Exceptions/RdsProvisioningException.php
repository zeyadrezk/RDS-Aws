<?php

namespace App\Exceptions;

use Exception;

class RdsProvisioningException extends Exception
{
    protected $clientId;
    protected $databaseId;
    protected $serviceId;
    protected $context;

    /**
     * Create a new RDS provisioning exception.
     *
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     * @param int|null $clientId
     * @param int|null $databaseId
     * @param int|null $serviceId
     * @param array $context
     */
    public function __construct(
        string $message,
        int $code = 0,
        \Throwable $previous = null,
        ?int $clientId = null,
        ?int $databaseId = null,
        ?int $serviceId = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->clientId = $clientId;
        $this->databaseId = $databaseId;
        $this->serviceId = $serviceId;
        $this->context = $context;
    }

    /**
     * Get the client ID.
     *
     * @return int|null
     */
    public function getClientId(): ?int
    {
        return $this->clientId;
    }

    /**
     * Get the database ID.
     *
     * @return int|null
     */
    public function getDatabaseId(): ?int
    {
        return $this->databaseId;
    }

    /**
     * Get the service ID.
     *
     * @return int|null
     */
    public function getServiceId(): ?int
    {
        return $this->serviceId;
    }

    /**
     * Get additional context data.
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }
}

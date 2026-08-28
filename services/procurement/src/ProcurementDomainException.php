<?php

final class ProcurementDomainException extends RuntimeException
{
    public function __construct(
        private readonly string $domainCode,
        string $message,
        private readonly int $httpStatus = 422
    ) {
        parent::__construct($message);
    }

    public function getDomainCode(): string
    {
        return $this->domainCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}

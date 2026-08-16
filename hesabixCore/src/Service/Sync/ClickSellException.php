<?php

namespace App\Service\Sync;

class ClickSellException extends \RuntimeException
{
    public string $errorCode;
    public int $httpStatus;
    public array $extra;

    public function __construct(string $code, string $message, int $status = 400, array $extra = [])
    {
        parent::__construct($message);
        $this->errorCode = $code;
        $this->httpStatus = $status;
        $this->extra = $extra;
    }

    public function toArray(): array
    {
        return array_merge([
            'success' => false,
            'code' => $this->errorCode,
            'error' => $this->getMessage(),
        ], $this->extra);
    }
}

<?php

namespace App\Exceptions;

use RuntimeException;

class GbpayPaymentException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $responseCode = '99',
        public readonly int $httpStatus = 400
    ) {
        parent::__construct($message);
    }
}

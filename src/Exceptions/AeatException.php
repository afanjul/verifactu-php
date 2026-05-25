<?php
namespace josemmo\Verifactu\Exceptions;

use RuntimeException;

/**
 * Exception thrown by the AEAT client
 */
class AeatException extends RuntimeException {
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly ?string $requestXml = null,
        public readonly ?string $responseXml = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}

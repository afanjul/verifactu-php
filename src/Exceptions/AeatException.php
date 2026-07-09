<?php
namespace josemmo\Verifactu\Exceptions;

use RuntimeException;

/**
 * Exception thrown by the AEAT client
 */
class AeatException extends RuntimeException {
    /**
     * Regex matching AEAT's "Codigo[XXXX].message" SOAP fault string format.
     */
    private const AEAT_FAULT_CODE_PATTERN = '/^Codigo\[(\d+)\]/';

    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly ?string $requestXml = null,
        public readonly ?string $responseXml = null,
        public readonly ?int $aeatErrorCode = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Build an AeatException from a SOAP fault string, extracting the structured
     * AEAT business error code when present (format: "Codigo[4104].mensaje").
     */
    public static function fromFaultString(
        string $faultString,
        ?string $requestXml = null,
        ?string $responseXml = null,
    ): self {
        $aeatErrorCode = null;
        if (preg_match(self::AEAT_FAULT_CODE_PATTERN, $faultString, $matches) === 1) {
            $aeatErrorCode = (int) $matches[1];
        }

        return new self(
            $faultString,
            requestXml: $requestXml,
            responseXml: $responseXml,
            aeatErrorCode: $aeatErrorCode,
        );
    }
}

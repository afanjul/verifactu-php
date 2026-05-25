<?php
namespace josemmo\Verifactu\Models\Responses;

/**
 * SOAP request sent to AEAT
 */
final readonly class AeatRequest {
    public function __construct(
        public string $xml,
    ) {
    }
}

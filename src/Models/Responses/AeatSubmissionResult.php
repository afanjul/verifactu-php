<?php
namespace josemmo\Verifactu\Models\Responses;

/**
 * Result of an AEAT invoice-record submission
 */
final readonly class AeatSubmissionResult {
    public function __construct(
        public AeatRequest $request,
        public AeatResponse $response,
    ) {
    }
}

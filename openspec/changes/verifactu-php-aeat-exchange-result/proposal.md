## Why

`verifactu-php` currently hides the SOAP XML request it sends and returns only parsed AEAT response objects, so consumers cannot persist the exact exchange they need for audit and recovery. The library should own XML construction, transport, parsing, and raw payload exposure instead of forcing applications to reconstruct or intercept SOAP messages.

## What Changes

- **BREAKING**: Change `AeatClient::send(array $records)` to return `PromiseInterface<AeatSubmissionResult>` instead of `PromiseInterface<AeatResponse>`.
- Add `AeatRequest` to represent the exact SOAP request XML sent to AEAT.
- Add `AeatSubmissionResult` to pair the sent `AeatRequest` with the received parsed `AeatResponse`.
- Add `AeatResponse::fromXml(string $xml)` as the public factory and ensure every `AeatResponse` preserves the received XML.
- Enrich AEAT exceptions with available request and response XML.
- Fix `ResponseItem` duplicate-record parsing from `RegistroDuplicado`.

## Capabilities

### New Capabilities
- `aeat-exchange-result`: Exposes AEAT SOAP request/response XML and parsed response data as a first-class library result.

### Modified Capabilities

## Impact

- Implementation target repository: `/Users/aleksdj/apps/verifactu-php`.
- Affected library classes: `AeatClient`, `AeatResponse`, `ResponseItem`, `AeatException`, and new response/request value objects.
- Affected tests: library tests for AEAT send, response parsing, SOAP fault handling, and duplicate-record parsing.
- Downstream impact: FacturaCheck and any other consumer must update `send()` call sites to consume `AeatSubmissionResult`.

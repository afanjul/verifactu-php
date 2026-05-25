## 1. Exchange Result Model

- [x] 1.1 Add `AeatRequest` value object with readonly `xml`.
- [x] 1.2 Add `AeatSubmissionResult` value object containing `AeatRequest $request` and `AeatResponse $response`.
- [x] 1.3 Update namespaces/autoloading/tests so the new value objects are discoverable.

## 2. Response Parsing Contract

- [x] 2.1 Add `AeatResponse::fromXml(string $xml)` as the public response factory.
- [x] 2.2 Move the existing UXML parse logic into a private helper used by `fromXml()`.
- [x] 2.3 Add `xml` property to `AeatResponse` and ensure it is always populated by public construction.
- [x] 2.4 Remove or privatize the public `from(UXML)` path.
- [x] 2.5 Add tests proving valid envelopes preserve XML and invalid envelopes/faults throw `AeatException`.

## 3. Client Send Contract

- [x] 3.1 Change `AeatClient::send(array $records)` return type documentation/implementation to `PromiseInterface<AeatSubmissionResult>`.
- [x] 3.2 Capture `$requestXml = $xml->asXML()` before sending.
- [x] 3.3 Use exactly `$requestXml` as the HTTP request body.
- [x] 3.4 Capture `$responseXml` from the response body before parsing.
- [x] 3.5 Return `new AeatSubmissionResult(new AeatRequest($requestXml), AeatResponse::fromXml($responseXml))`.
- [x] 3.6 Update library tests for the new send result contract.

## 4. Exception Payload Capture

- [x] 4.1 Extend `AeatException` with nullable `requestXml` and `responseXml`.
- [x] 4.2 In `AeatClient::send()`, rethrow SOAP/parse exceptions enriched with request and response XML when available.
- [x] 4.3 In `AeatClient::send()`, wrap or enrich transport failures after request construction with request XML.
- [x] 4.4 Add tests for SOAP fault, invalid response XML, and transport failure payload availability.

## 5. Duplicate Response Parsing

- [x] 5.1 Add nullable duplicate fields to `ResponseItem`: request id, status, error code, and error description.
- [x] 5.2 Parse duplicate fields from `RespuestaLinea/RegistroDuplicado/*`.
- [x] 5.3 Keep regular `errorCode` and `errorDescription` as nullable singular strings.
- [x] 5.4 Add tests for duplicate response XML with all duplicate fields populated.

## 6. Verification

- [x] 6.1 Run the library unit test suite.
- [x] 6.2 Run static analysis if configured for the library.
- [x] 6.3 Update README/docs or fork notes to document the breaking `send()` result contract.
- [ ] 6.4 Record the commit/revision that FacturaCheck should consume.

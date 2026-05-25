## Context

The `verifactu-php` fork builds AEAT VERI*FACTU SOAP requests internally, sends them through Guzzle, parses SOAP responses into `AeatResponse`, and discards both raw XML strings. FacturaCheck needs the exact XML request and response, but that data belongs at the library boundary because the library owns XML generation and SOAP transport.

This plan is authored from the FacturaCheck workspace for coordination, but its implementation target is the separate `verifactu-php` repository.

## Goals / Non-Goals

**Goals:**
- Expose the exact SOAP XML sent to AEAT.
- Preserve the exact SOAP XML received from AEAT on `AeatResponse`.
- Return request, response, and parsed data from one `send()` call.
- Enrich exceptions with XML payloads when available.
- Fix duplicate-record parsing under `RegistroDuplicado`.
- Keep the API simple and intentional, with no compatibility shim.

**Non-Goals:**
- Add FacturaCheck-specific concepts to the library.
- Add persistence, database fields, Yii models, or recovery policy.
- Create a public request-builder/send-two-step API.
- Store or expose both XML and JSON representations of the same exchange.
- Preserve the old `send(): AeatResponse` contract.

## Decisions

1. **Return an exchange result from `send()`.**
   - Decision: `AeatClient::send(array $records)` returns `PromiseInterface<AeatSubmissionResult>`.
   - Rationale: consumers need the sent request XML, received response XML, and parsed response together.
   - Alternative rejected: adding `sendWithCapture()`; the fork can make breaking improvements.

2. **Represent request XML with `AeatRequest`.**
   - Decision: add a small immutable value object with `public readonly string $xml`.
   - Rationale: the request is semantically different from the response even if both expose XML.
   - Alternative rejected: returning loose `requestXml` and `responseXml` strings directly from the result.

3. **Make `AeatResponse::fromXml(string)` the public factory.**
   - Decision: `fromXml()` accepts full SOAP envelopes and sets `$response->xml`; any `UXML` parsing helper is private.
   - Rationale: a public `from(UXML)` path can produce responses without raw XML, violating the new invariant.
   - Alternative rejected: keeping both factories public.

4. **Enrich exceptions at the client boundary.**
   - Decision: `AeatClient::send()` catches parse/SOAP/transport failures and rethrows exceptions carrying `requestXml` and `responseXml` when available.
   - Rationale: only `AeatClient::send()` has both request and response strings in scope.
   - Alternative rejected: trying to attach request XML inside `AeatResponse`.

5. **Parse duplicate details from the correct node.**
   - Decision: read duplicate information from `RespuestaLinea/RegistroDuplicado/*`.
   - Rationale: the current direct `EstadoRegistroDuplicado` lookup misses the documented nested structure.

## Risks / Trade-offs

- [Risk] Breaking `send()` requires downstream consumers to update immediately. → Mitigation: document the new contract and update FacturaCheck in a dependent change.
- [Risk] Exception enrichment can be inconsistent across failure phases. → Mitigation: define payload availability clearly: request XML after request construction, response XML only after a body is read.
- [Risk] `fromXml()` must reject invalid envelopes clearly. → Mitigation: keep fail-fast `AeatException` behavior for SOAP faults, missing response root, and invalid XML.
- [Risk] Duplicate parsing adds fields not every consumer needs. → Mitigation: nullable properties keep the object simple and backward-readable.

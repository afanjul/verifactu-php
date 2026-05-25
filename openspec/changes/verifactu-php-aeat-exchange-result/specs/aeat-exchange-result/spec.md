## ADDED Requirements

### Requirement: AeatClient send returns an AEAT submission result
`AeatClient::send(array $records)` SHALL return `PromiseInterface<AeatSubmissionResult>`. The result SHALL contain the exact SOAP request XML sent to AEAT and the parsed AEAT response preserving the exact SOAP response XML received from AEAT.

#### Scenario: Successful send exposes request and response
- **WHEN** `AeatClient::send($records)->wait()` completes successfully
- **THEN** the returned result contains `request->xml`
- **AND** it contains `response->xml`
- **AND** `response` exposes parsed fields such as `status`, `csv`, `waitSeconds`, and `items`

#### Scenario: HTTP body uses request XML exactly
- **WHEN** `AeatClient::send($records)` sends the HTTP request
- **THEN** the Guzzle request body is exactly the same string exposed as `result->request->xml`

### Requirement: AeatRequest represents the sent SOAP payload
The library SHALL provide an `AeatRequest` value object representing the sent AEAT SOAP payload. It SHALL expose the payload through a readonly `xml` property.

#### Scenario: AeatRequest contains XML only
- **WHEN** an `AeatRequest` is created during `send()`
- **THEN** it contains the full SOAP request envelope in `xml`
- **AND** it does not expose FacturaCheck-specific metadata or persistence concepts

### Requirement: AeatResponse is created publicly from XML text
`AeatResponse` SHALL expose `fromXml(string $xml): self` as the public factory for parsing AEAT remission responses. Every `AeatResponse` created through the public API SHALL preserve the original XML string.

#### Scenario: fromXml preserves response XML
- **WHEN** `AeatResponse::fromXml($xml)` parses a valid AEAT SOAP response envelope
- **THEN** the resulting response exposes the original `$xml` through `response->xml`
- **AND** parsed properties are populated from the XML

#### Scenario: Invalid XML throws AEAT exception
- **WHEN** `AeatResponse::fromXml($xml)` receives invalid XML or an envelope without the expected response root
- **THEN** it throws `AeatException`

#### Scenario: SOAP fault throws AEAT exception
- **WHEN** `AeatResponse::fromXml($xml)` receives a SOAP fault envelope
- **THEN** it throws `AeatException`
- **AND** the exception message reflects the SOAP fault string

### Requirement: AEAT exceptions carry available XML payloads
AEAT-related exceptions thrown from `AeatClient::send()` SHALL expose nullable request and response XML fields. `requestXml` SHALL be set when the request XML was built. `responseXml` SHALL be set when a response body was received.

#### Scenario: Parse failure exposes both payloads
- **WHEN** AEAT returns a response body that cannot be parsed into a valid AEAT response
- **THEN** the thrown exception exposes `requestXml`
- **AND** it exposes `responseXml`

#### Scenario: Transport failure before response exposes request only
- **WHEN** the request XML was built and the HTTP transport fails before a response body is received
- **THEN** the thrown exception exposes `requestXml`
- **AND** `responseXml` is NULL

### Requirement: ResponseItem parses duplicate-record details
`ResponseItem` SHALL expose duplicate-record details returned by AEAT inside `RespuestaLinea/RegistroDuplicado`.

#### Scenario: Duplicate details are parsed from RegistroDuplicado
- **WHEN** a `RespuestaLinea` contains `RegistroDuplicado`
- **THEN** the parsed `ResponseItem` exposes duplicate request id when present
- **AND** duplicate status when present
- **AND** duplicate error code and duplicate error description when present

### Requirement: Response item error fields remain singular
`ResponseItem` SHALL model `CodigoErrorRegistro` and `DescripcionErrorRegistro` as nullable singular fields because the AEAT response schema defines them as optional single elements.

#### Scenario: Single error detail is parsed as scalar
- **WHEN** a `RespuestaLinea` contains `CodigoErrorRegistro` and `DescripcionErrorRegistro`
- **THEN** the parsed `ResponseItem` exposes `errorCode` as a nullable string
- **AND** it exposes `errorDescription` as a nullable string
- **AND** it does not expose these values as arrays

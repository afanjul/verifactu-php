# AGENTS.md

## 1. Project Overview & Scope

**Verifactu-PHP** (`josemmo/verifactu-php`) is a PHP library that implements the Spanish **VERI\*FACTU** invoice record system as defined by Royal Decree 1007/2023. It enables Sistema Informático de Facturación (SIF) applications to generate compliant invoice records with cryptographic integrity guarantees and transmit them to Spain's Agencia Estatal de Administración Tributaria (AEAT) via SOAP over mutual TLS. The library is mainly focused in invoice voluntary remission (SI VERIFACTU modality).

**Disclaimer:** The library is **not** a Billing Computer System (SIF) itself and is provided without a responsible declaration. It is a middleware toolkit for building SIFs. Users must audit their integration for regulatory compliance. 
* **Scope:** Strictly implements the VERI\*FACTU specification. No vendor-specific or niche functionality is accepted.

---

## 2. Technology Stack

| Layer | Technology / Dependency |
| :--- | :--- |
| **Language** | PHP ≥ 8.2 (Utilizes modern type system, readonly properties, enums) |
| **System Extension** | libXML (for XML parsing and generation) |
| **HTTP Client** | `guzzlehttp/guzzle ^7.10` (Async support) |
| **XML Manipulation** | `josemmo/uxml ^0.2.0` |
| **Validation** | `symfony/validator ^6.4|^7.4|^8.0` |
| **Linting** | `laravel/pint ^1.26` |
| **Static Analysis** | `phpstan/phpstan ^2.1` + `phpstan/phpstan-strict-rules ^2.0` |
| **Unit Testing** | `phpunit/phpunit ^11.5|^12.4` |

---

## 3. Repository Structure

The project follows PSR-4 autoloading mapped to `josemmo\Verifactu\` (`src/`) and `josemmo\Verifactu\Tests\` (`tests/`).

```text
josemmo/Verifactu-PHP/
├── .github/                    # GitHub Actions CI/CD workflows
├── src/                        
│   ├── Exceptions/             # Custom exception classes (AeatException, ImportException, InvalidModelException)
│   ├── Models/                 # Domain models
│   │   ├── ComputerSystem.php  # SIF identity metadata
│   │   ├── Records/            # Invoicing record models (Record, RegistrationRecord, CancellationRecord, etc.)
│   │   └── Responses/          # AEAT response models (AeatResponse, ResponseItem, etc.)
│   └── Services/               # External communication
│       ├── AeatClient.php      # SOAP/HTTP client for AEAT endpoint
│       └── QrGenerator.php     # QR code URL builder for invoices
├── tests/                      # Unit tests (mirrors src/ structure exactly)
├── CONTRIBUTING.md             # Contribution rules
├── README.md                   # Installation and usage
└── composer.json               # Project manifest

```

---

## 4. Core Architecture & Design Patterns

- Principles: The project tries to follow the 3 main dev principles know as KISS + SOLID + DRY.
- Backward compatibility: The project doesn't need any backward compatibility or fallbacks because its not production ready and it's brand new. 
- We focus on effective, simple but complete coding style. No over-engineering. No magic. No hidden logic.

### 4.1 Domain Models & Validation

Every domain object extends the abstract `Model` base class, which provides a `validate()` method using Symfony Validator constraints (both attribute-based like `#[Assert\NotBlank]` and callback-based `#[Assert\Callback]`).

* Calling `Model::validate()` is a **mandatory step** before passing any object to the service layer.
* Failures throw an `InvalidModelException`.

### 4.2 Record Inheritance & Hash Chaining

* `Record` (Abstract): Defines the invoice chain (previous invoice ID + previous hash) and forces subclasses to implement XML element naming, hashing, and XML import/export.
* **Hash Chaining:** VERI*FACTU requires blockchain-like hashing. SHA-256 is computed over a deterministic, **non-URL-encoded** query-string payload. The result is a 64-character uppercase hex string.
* `RegistrationRecord`: Represents an invoice registration (alta).
* `CancellationRecord`: Represents an invoice annulment (anulación).

### 4.3 XML Serialization Contract

* Models that can be serialized implement `export(UXML $xml): void`.
* Models that can be deserialized implement `static fromXml(UXML $xml): self`. The `Record::fromXml()` factory auto-detects the concrete subtype.

### 4.4 Communication Layer

The `AeatClient` builds the SOAP envelope, attaches TLS client certificates (PEM or PKCS#12/PFX), sends data asynchronously via Guzzle, and parses the XML into an `AeatResponse`. It supports both production and pre-production environments.

---

## 5. Installation & Basic Usage

**Installation:**

```bash
composer require josemmo/verifactu-php
```

**Basic Usage Pattern:**

```php
// 1. Generate a billing record
$record = new RegistrationRecord();
$record->invoiceId = new InvoiceIdentifier();
$record->invoiceId->issuerId = 'A00000000';
$record->invoiceId->invoiceNumber = 'TICKET-2025-06-001';
$record->invoiceId->issueDate = new DateTimeImmutable('2025-06-10');
// ... populate remaining invoice data, breakdowns, and taxes ...

$record->previousInvoiceId = null; // null if first in chain
$record->previousHash = null;      
$record->hashedAt = new DateTimeImmutable();
$record->hash = $record->calculateHash();
$record->validate(); // Mandatory validation

// 2. Define SIF data
$system = new ComputerSystem();
// ... populate vendor and system details ...
$system->validate();

// 3. Initialize AEAT Client and send
$taxpayer = new FiscalIdentifier('Perico de los Palotes, S.A.', 'A00000000');
$client = new AeatClient($system, $taxpayer);
$client->setCertificate(__DIR__ . '/certificado.pfx', 'password');
$client->setProduction(false); // Use pre-production for testing

// 4. Handle Response
$aeatResponse = $client->send([$record])->wait();
if ($aeatResponse->status === ResponseStatus::Correct) {
    echo "Record accepted. CSV: " . $aeatResponse->csv;
} else {
    echo "Error: " . $aeatResponse->items[0]->errorDescription;
}
```

---

## 6. Developer Workflows & Quality Assurance

All contributions must pass three automated quality gates locally before committing. GitHub Actions enforces these on every push/PR via matrix testing (PHP 8.2-8.6).

| Command | Purpose | Hard Requirement |
| --- | --- | --- |
| `composer lint` | Code styling via Laravel Pint (`pint.json`) | Must pass with 0 errors |
| `composer stan` | Strict static analysis via PHPStan (`phpstan.neon`) | Must pass with 0 errors |
| `composer test` | Unit testing via PHPUnit | Must cover new/changed code |

### Testing Methodology

* **Location:** `tests/` mirrors `src/`.
* **Patterns:** Includes validation tests (mutating fields to trigger exceptions), hash tests (verifying exact 64-char SHA-256 output), XML round-trip tests (Import ↔ Export symmetry using fixtures), and HTTP mock tests (Guzzle `MockHandler`).

---

## 7. Contribution Rules & Code Conventions

* **Language Policy:** Source code (variables, functions, classes, comments, commits) **must be in English**. Documentation, PR titles, and PR descriptions **must be in Spanish**.
* **Priorities:** Bug fixes have absolute priority.
* **Security:** Certificate passwords use `#[SensitiveParameter]`. Never log or expose them.

---

## 8. Reference Tables

### Key Endpoints

| Environment | SOAP Endpoint | QR Validation | Entity-Seal |
| --- | --- | --- | --- |
| **Production** | `www1.agenciatributaria.gob.es/.../VerifactuSOAP` | `www2.agenciatributaria.gob.es/.../ValidarQR` | `www10.agenciatributaria.gob.es` |
| **Staging** | `prewww1.aeat.es/.../VerifactuSOAP` | `prewww2.aeat.es/.../ValidarQR` | `prewww10.aeat.es` |

### Enums Quick Reference (`josemmo\Verifactu\Models\`)

* **InvoiceType:** `Factura(F1)`, `Simplificada(F2)`, `Sustitutiva(F3)`, `R1`–`R5`
* **CorrectiveType:** `Substitution(S)`, `Differences(I)`
* **OperationType:** `Subject(S1)`, `PassiveSubject(S2)`, `NonSubject(N1)`, `NonSubjectByLocation(N2)`, `Exempt... (E1-E6)`
* **RecordType:** `Registration(Alta)`, `Cancellation(Anulacion)`

---

## 9. Directives for AI Agents

1. **Scope strictly:** Do not add features outside the VERI*FACTU specification.
2. **Exception Handling:** Do not silently swallow exceptions. Let `AeatException`, `ImportException`, and `InvalidModelException` propagate.
3. **Hashing compliance:** Do not URL-encode hash payload values.
4. **Verification:** Always run `composer lint && composer stan && composer test` before concluding a task. Write tests for every modified code path.
5. **XML Integrity:** When modifying any `fromXml()` or `export()` method, update the corresponding XML fixture and ensure the round-trip test passes.
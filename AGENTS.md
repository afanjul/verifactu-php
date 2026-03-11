# AGENTS.md

This file is for contributors and AI agents working inside this repository.
For downstream library consumers, the canonical integration sources are `README.md`, `docs/`, and `llms.txt`.

## 1. Project Overview & Scope
**Verifactu-PHP** (`josemmo/verifactu-php`): PHP middleware library implementing the Spanish **VERI*FACTU** system (RD 1007/2023) for SIFs (Sistemas Informáticos de Facturación). Handles XML generation, hashing, and SOAP/TLS communication with AEAT.
* **Scope limit:** Strictly limits to VERI*FACTU specs. No custom vendor logic.
* **Disclaimer:** Not a ready-to-use SIF. Provided without a responsible declaration. Users must audit their integration.
* **Fork Notice:** This is a fork maintained by `afanjul`. Retains the original package name for drop-in replacement. Example installation in `composer.json`:
  ```json
  "repositories": [{"type": "vcs", "url": "https://github.com/afanjul/verifactu-php"}],
  "require": {"josemmo/verifactu-php": "dev-develop"}
  ```

## 2. Tech Stack & Quality Gates
* **PHP ≥ 8.2**. Dependencies: `guzzlehttp/guzzle`, `josemmo/uxml`, `symfony/validator`. Built-in libXML required.
* **MANDATORY checks before commit:** 
  1. `composer lint` (Laravel Pint)
  2. `composer stan` (PHPStan Strict)
  3. `composer test` (PHPUnit - must cover new/mutated code)

## 2.5. External Consumer Documentation Sources
* `README.md`: primary entry point for integrators.
* `docs/`: task-oriented and reference documentation for library consumers.
* `llms.txt`: AI-oriented summary of the public API and workflows.
* `AGENTS.md`: repository-specific contributor guidance, not the main external API guide.

## 3. Architecture & Repository Structure
Follows KISS, SOLID, DRY. PSR-4 under `josemmo\Verifactu\` (`src/`) and `josemmo\Verifactu\Tests\` (`tests/`). No backward compatibility needed (brand new).
* `Exceptions\`: Custom exceptions (`AeatException`, `ImportException`, `InvalidModelException`).
* `Models\Records\`: Invoicing models (`RegistrationRecord`, `CancellationRecord`).
   - `RegistrationRecord` (Alta) Operative flags: `bool $isCorrection`, `PreviousRejectionType $isPriorRejection`.
   - `CancellationRecord` (Anulación) Operative flags: `bool $withoutPriorRecord`, `bool $isPriorRejection`.
* `Models\Events\`: Event logging (`EventRecord`, `EventType`) for SIF lifecycle tracking.
* `Models\Queries\`: AEAT query objects (`QueryFilter`).
* `Models\Responses\`: AEAT parsed responses (`AeatResponse`, `ResponseItem`).
* `Models\ComputerSystem.php`: SIF identity metadata.
* `Services\`: `AeatClient` (SOAP/HTTP communication) and `QrGenerator`.

## 4. Core Concepts & Contracts
* **Validation:** All models extend `Model`. You **MUST** call `$model->validate()` before submission. Validation uses Symfony attributes (`#[Assert\...]`). Failures throw `InvalidModelException`.
* **Hash Chaining:** SHA-256 over a deterministic, **non-URL-encoded** payload. Handled via `$record->calculateHash()`. Result is a 64-char uppercase hex.
* **XML Contract:** Serializable models implement `export(UXML $xml)`. Deserializable models implement `static fromXml(UXML $xml)`.
* **Security:** Certificate passwords use `#[SensitiveParameter]`. Never log or expose them.

## 5. Basic Usage Pattern
```php
// 1. Generate billing record
$record = new RegistrationRecord();
$record->invoiceId = new InvoiceIdentifier('A00000000', 'FAC-01', new DateTimeImmutable());
$record->invoiceType = InvoiceType::Simplificada;
/* ... assign breakdown, taxes, totalAmount, etc. ... */
$record->previousInvoiceId = null; // null if first in chain
$record->previousHash = null;
$record->hashedAt = new DateTimeImmutable();
$record->hash = $record->calculateHash();
$record->validate(); // Mandatory

// 2. Define SIF data
$system = new ComputerSystem();
/* ... populate vendor and system details ... */
$system->validate();

// 3. Initialize AEAT Client and send
$taxpayer = new FiscalIdentifier('Empresa, S.A.', 'A00000000');
$client = new AeatClient($system, $taxpayer);
$client->setCertificate('cert.pfx', 'password');
$client->setProduction(false);

// 4. Handle Response
$aeatResponse = $client->send([$record])->wait(); // Also available: $client->query($filter)->wait()
if ($aeatResponse->status === ResponseStatus::Correct) {
    echo "Accepted. CSV: " . $aeatResponse->csv;
} else {
    echo "Error: " . $aeatResponse->items[0]->errorDescription;
}
```

## 6. Testing Methodology
* **Location:** `tests/` mirrors `src/`.
* **Coverage:** Writing tests is mandatory for every modified code path.
* **Patterns:** 
  - **Validation tests:** Mutate fields to trigger exceptions.
  - **Hash tests:** Verify exact 64-char SHA-256 output.
  - **XML round-trip tests:** Ensure `fromXml()` <-> `export()` symmetry using XML fixtures. Update fixtures when modifying properties.
  - **HTTP Mock tests:** Uses Guzzle `MockHandler` for API responses.

## 7. Reference Tables

### Key Endpoints
| Environment | SOAP Endpoint | QR Validation | Entity-Seal |
| --- | --- | --- | --- |
| **Production** | `www1.../VerifactuSOAP` | `www2.../ValidarQR` | `www10...` |
| **Staging** | `prewww1.../VerifactuSOAP` | `prewww2.../ValidarQR` | `prewww10...` |

### Enums Quick Reference (`Models\`)
* **InvoiceType:** `Factura(F1)`, `Simplificada(F2)`, `Sustitutiva(F3)`, `R1`–`R5`
* **CorrectiveType:** `Substitution(S)`, `Differences(I)`
* **OperationType:** `Subject(S1)`, `PassiveSubject(S2)`, `NonSubject(N1)`, `NonSubjectByLocation(N2)`, `Exempt... (E1-E6)`
* **RecordType:** `Registration(Alta)`, `Cancellation(Anulacion)`

## 8. Directives for AI Agents
1. **Never swallow exceptions**. Let `AeatException`, `ImportException`, and `InvalidModelException` propagate naturally.
2. **Language Policy**: Source code (variables, functions, classes, comments, commits) **must be in English**. Documentation, PR titles, and PR descriptions **must be in Spanish**.
3. **Bug fixes have absolute priority** over new features.
4. **Always verify**: Run `composer lint && composer stan && composer test` before claiming success.
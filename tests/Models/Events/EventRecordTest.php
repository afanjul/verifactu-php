<?php
namespace josemmo\Verifactu\Tests\Models\Events;

use DateTimeImmutable;
use josemmo\Verifactu\Exceptions\InvalidModelException;
use josemmo\Verifactu\Models\ComputerSystem;
use josemmo\Verifactu\Models\Events\AnomalyType;
use josemmo\Verifactu\Models\Events\EventRecord;
use josemmo\Verifactu\Models\Events\EventType;
use josemmo\Verifactu\Models\Records\FiscalIdentifier;
use josemmo\Verifactu\Tests\TestUtils;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UXML\UXML;

final class EventRecordTest extends TestCase {
    /**
     * Build a minimal valid ComputerSystem for testing
     */
    private static function buildSystem(): ComputerSystem {
        $system = new ComputerSystem();
        $system->vendorName = 'Perico de los Palotes, S.A.';
        $system->vendorNif = 'A00000000';
        $system->name = 'Test SIF';
        $system->id = 'TS';
        $system->version = '0.0.1';
        $system->installationNumber = '01234';
        $system->onlySupportsVerifactu = true;
        $system->supportsMultipleTaxpayers = false;
        $system->hasMultipleTaxpayers = false;
        return $system;
    }

    /**
     * Build a minimal valid EventRecord for testing
     */
    private static function buildRecord(ComputerSystem $system, EventType $type = EventType::Summary): EventRecord {
        $record = new EventRecord();
        $record->system = $system;
        $record->issuer = new FiscalIdentifier('Empresa Ejemplo SL', 'B00000001');
        $record->eventType = $type;
        $record->generatedAt = new DateTimeImmutable('2024-01-01T19:20:30+01:00');
        return $record;
    }

    /**
     * @return array<string,string[]> PHPUnit provider
     */
    public static function xmlPathsProvider(): array {
        return [
            'summary-chained' => [__DIR__ . '/event-record.xml'],
        ];
    }

    // -------------------------------------------------------------------------
    // Hash tests
    // -------------------------------------------------------------------------

    public function testCalculatesHashForFirstEvent(): void {
        $system = self::buildSystem();
        $record = self::buildRecord($system);
        $record->previousEventType = null;
        $record->previousGeneratedAt = null;
        $record->previousHash = null;
        $record->hash = $record->calculateHash();

        // Expected: NIF=A00000000&ID=&IdSistemaInformatico=TS&Version=0.0.1
        //           &NumeroInstalacion=01234&NIF=B00000001&TipoEvento=10
        //           &HuellaEvento=&FechaHoraHusoGenEvento=2024-01-01T19:20:30+01:00
        $this->assertEquals(
            'E9714B49E59CBCE753FE1D4A4FDA1D34DED66F6A54C3FF6BD52C38520C2361E1',
            $record->hash
        );
        $record->validate(); // Must not throw
    }

    public function testCalculatesHashForChainedEvent(): void {
        $system = self::buildSystem();
        $record = self::buildRecord($system);
        $record->previousEventType = EventType::Summary;
        $record->previousGeneratedAt = new DateTimeImmutable('2024-01-01T13:20:30+01:00');
        $record->previousHash = 'E9714B49E59CBCE753FE1D4A4FDA1D34DED66F6A54C3FF6BD52C38520C2361E1';
        $record->hash = $record->calculateHash();

        $this->assertEquals(
            'AF6FBD2CF071E3A09280F94672E61053CF27BBA9B68BAD6D38F0EE5434B95695',
            $record->hash
        );
        $record->validate(); // Must not throw
    }

    public function testHashDetectsWrongValue(): void {
        $system = self::buildSystem();
        $record = self::buildRecord($system);
        $record->previousHash = null;
        $record->hash = str_repeat('0', 64); // Wrong hash

        $this->expectException(InvalidModelException::class);
        $this->expectExceptionMessageMatches('/Invalid hash/');
        $record->validate();
    }

    // -------------------------------------------------------------------------
    // Chaining validation
    // -------------------------------------------------------------------------

    public function testValidatesPartialChainingFails(): void {
        $system = self::buildSystem();
        $record = self::buildRecord($system);
        // Only previousHash set, others null → invalid
        $record->previousHash = 'E9714B49E59CBCE753FE1D4A4FDA1D34DED66F6A54C3FF6BD52C38520C2361E1';
        $record->previousEventType = null;
        $record->previousGeneratedAt = null;
        $record->hash = str_repeat('A', 64);

        $this->expectException(InvalidModelException::class);
        $this->expectExceptionMessageMatches('/previousEventType.*previousGeneratedAt.*previousHash/');
        $record->validate();
    }

    public function testAllEventTypeValues(): void {
        $system = self::buildSystem();
        $count = 0;
        foreach (EventType::cases() as $type) {
            $record = self::buildRecord($system, $type);
            $record->previousHash = null;
            $record->hash = $record->calculateHash();
            $record->validate(); // All L2E values must be usable
            $count++;
        }
        $this->assertCount($count, EventType::cases());
        $this->assertCount(11, EventType::cases()); // 01–10 + 90
    }

    public function testAllAnomalyTypeValues(): void {
        $this->assertCount(4, AnomalyType::cases());
        foreach (AnomalyType::cases() as $anomaly) {
            $this->assertNotEmpty($anomaly->value);
        }
    }

    // -------------------------------------------------------------------------
    // XML round-trip test
    // -------------------------------------------------------------------------

    #[DataProvider('xmlPathsProvider')]
    public function testImportsAndExportsXmlElement(string $xmlPath): void {
        // Import
        $modelXml = TestUtils::getXmlFile($xmlPath);
        $record = EventRecord::fromXml($modelXml);
        $record->validate();

        // Export – declare the sf: namespace on the container so UXML's XPath can find it
        $container = UXML::newInstance('container', null, ['xmlns:sf' => EventRecord::NS]);
        $record->export($container);
        $exportedXml = $container->get('sf:RegistroEvento');
        $this->assertNotNull($exportedXml);

        $this->assertXmlStringEqualsXmlString($modelXml->asXML(), $exportedXml->asXML());
    }

    // -------------------------------------------------------------------------
    // Validation: missing required fields
    // -------------------------------------------------------------------------

    public function testValidationFailsWhenHashMissing(): void {
        $system = self::buildSystem();
        $record = self::buildRecord($system);
        $record->previousHash = null;
        // $record->hash intentionally not set

        $this->expectException(InvalidModelException::class);
        $record->validate();
    }

    public function testValidationFailsWhenIssuerNifWrongLength(): void {
        $system = self::buildSystem();
        $record = self::buildRecord($system);
        $record->issuer = new FiscalIdentifier('Bad NIF Corp', 'SHORT'); // < 9 chars
        $record->previousHash = null;
        $record->hash = str_repeat('A', 64);

        $this->expectException(InvalidModelException::class);
        $record->validate();
    }

    // -------------------------------------------------------------------------
    // Optional sub-blocks
    // -------------------------------------------------------------------------

    public function testExportsInvoiceAnomalyCheckSubBlock(): void {
        $system = self::buildSystem();
        $record = self::buildRecord($system, EventType::InvoiceAnomalyCheck);
        $record->previousHash = null;
        $record->invoiceHashIntegrityChecked = true;
        $record->invoiceHashIntegrityCount = 100;
        $record->invoiceSignatureIntegrityChecked = false;
        $record->invoiceChainTraceabilityChecked = true;
        $record->invoiceChainTraceabilityCount = 50;
        $record->invoiceDateTraceabilityChecked = false;
        $record->hash = $record->calculateHash();
        $record->validate();

        $container = UXML::newInstance('container', null, ['xmlns:sf' => EventRecord::NS]);
        $record->export($container);
        $xml = $container->get('sf:RegistroEvento');
        $this->assertNotNull($xml);

        $launchEl = $xml->get('sf:Evento/sf:R/sf:LanzamientoProcesoDeteccionAnomaliasRegFacturacion');
        $this->assertNotNull($launchEl);
        $this->assertEquals('S', $launchEl->get('sf:RealizadoProcesoSobreIntegridadHuellasRegFacturacion')?->asText());
        $this->assertEquals('100', $launchEl->get('sf:NumeroDeRegistrosFacturacionProcesadosSobreIntegridadHuellas')?->asText());
        $this->assertEquals('N', $launchEl->get('sf:RealizadoProcesoSobreIntegridadFirmasRegFacturacion')?->asText());
    }

    public function testExportsEventAnomalyDetectedSubBlock(): void {
        $system = self::buildSystem();
        $record = self::buildRecord($system, EventType::EventAnomalyDetected);
        $record->previousHash = null;
        $record->eventAnomalyType = AnomalyType::ChainTraceability;
        $record->eventAnomalyDetails = 'Broken link detected';
        $record->hash = $record->calculateHash();
        $record->validate();

        $container = UXML::newInstance('container', null, ['xmlns:sf' => EventRecord::NS]);
        $record->export($container);
        $xml = $container->get('sf:RegistroEvento');
        $this->assertNotNull($xml);

        $anomaliaEl = $xml->get('sf:Evento/sf:R/sf:DeteccionAnomaliasRegEvento');
        $this->assertNotNull($anomaliaEl);
        $this->assertEquals('03', $anomaliaEl->get('sf:TipoAnomalia')?->asText());
        $this->assertEquals('Broken link detected', $anomaliaEl->get('sf:OtrosDatosAnomalia')?->asText());
    }
}

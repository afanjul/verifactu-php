<?php
namespace josemmo\Verifactu\Tests\Models\Records;

use DateTimeImmutable;
use josemmo\Verifactu\Exceptions\InvalidModelException;
use josemmo\Verifactu\Models\ComputerSystem;
use josemmo\Verifactu\Models\Records\CancellationRecord;
use josemmo\Verifactu\Models\Records\FiscalIdentifier;
use josemmo\Verifactu\Models\Records\GeneratedByType;
use josemmo\Verifactu\Models\Records\InvoiceIdentifier;
use josemmo\Verifactu\Models\Records\Record;
use josemmo\Verifactu\Tests\TestUtils;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UXML\UXML;

final class CancellationRecordTest extends TestCase {
    /**
     * @return array<string,string[]> PHPUnit provider
     */
    public static function xmlPathsProvider(): array {
        return [
            'simple' => [__DIR__ . '/cancellation-record.xml'],
        ];
    }

    public function testAllowsPrimerRegistro(): void {
        $record = new CancellationRecord();
        $record->invoiceId = new InvoiceIdentifier();
        $record->invoiceId->issuerId = '89890001K';
        $record->invoiceId->invoiceNumber = '12345679/G34';
        $record->invoiceId->issueDate = new DateTimeImmutable('2024-01-01');
        $record->previousInvoiceId = null; // PrimerRegistro allowed
        $record->previousHash = null; // PrimerRegistro allowed
        $record->hashedAt = new DateTimeImmutable('2024-01-01T19:20:40+01:00');
        $record->hash = $record->calculateHash();
        $record->validate(); // Should NOT throw

        // But providing only one of them should still fail
        $record->previousInvoiceId = new InvoiceIdentifier();
        $record->previousInvoiceId->issuerId = '89890001K';
        $record->previousInvoiceId->invoiceNumber = '12345679/G34';
        $record->previousInvoiceId->issueDate = new DateTimeImmutable('2024-01-01');
        $record->hash = $record->calculateHash();
        try {
            $record->validate();
            $this->fail('Did not throw exception when previousInvoiceId set without previousHash');
        } catch (InvalidModelException $e) {
            $this->assertStringContainsString('Previous hash is required', $e->getMessage());
        }
    }

    public function testCalculatesHashForOtherRecords(): void {
        $record = new CancellationRecord();
        $record->invoiceId = new InvoiceIdentifier();
        $record->invoiceId->issuerId = '89890001K';
        $record->invoiceId->invoiceNumber = '12345679/G34';
        $record->invoiceId->issueDate = new DateTimeImmutable('2024-01-01');
        $record->previousInvoiceId = new InvoiceIdentifier();
        $record->previousInvoiceId->issuerId = '89890001K';
        $record->previousInvoiceId->invoiceNumber = '12345679/G34';
        $record->previousInvoiceId->issueDate = new DateTimeImmutable('2024-01-01');
        $record->previousHash = 'F7B94CFD8924EDFF273501B01EE5153E4CE8F259766F88CF6ACB8935802A2B97';
        $record->hashedAt = new DateTimeImmutable('2024-01-01T19:20:40+01:00');
        $record->hash = $record->calculateHash();
        $this->assertEquals('177547C0D57AC74748561D054A9CEC14B4C4EA23D1BEFD6F2E69E3A388F90C68', $record->hash);
        $record->validate();
    }

    #[DataProvider('xmlPathsProvider')]
    public function testImportsAndExportsXmlElement(string $xmlPath): void {
        // Import model
        $modelXml = TestUtils::getXmlFile($xmlPath);
        $record = Record::fromXml($modelXml);
        $this->assertInstanceOf(CancellationRecord::class, $record);
        $record->validate();

        // Import computer system
        $computerSystemXml = $modelXml->get('sum1:SistemaInformatico');
        $this->assertNotNull($computerSystemXml);
        $computerSystem = ComputerSystem::fromXml($computerSystemXml);

        // Export model
        $exportedXml = UXML::newInstance('container', null, ['xmlns:sum1' => Record::NS]);
        $record->export($exportedXml, $computerSystem);
        $this->assertXmlStringEqualsXmlString($modelXml, $exportedXml->get('sum1:RegistroAnulacion')?->asXML() ?? '');
    }

    public function testAllGeneratedByTypeValues(): void {
        $record = new CancellationRecord();
        $record->invoiceId = new InvoiceIdentifier('89890001K', '12345679/G34', new DateTimeImmutable('2024-01-01'));
        $record->previousInvoiceId = null;
        $record->previousHash = null;
        $record->hashedAt = new DateTimeImmutable('2024-01-01T19:20:40+01:00');
        $generator = new FiscalIdentifier('Generador SA', 'B00000001');

        foreach (GeneratedByType::cases() as $generatedByType) {
            $record->generatedBy = $generatedByType;
            $record->generator = $generator;
            $record->hash = $record->calculateHash();
            $record->validate(); // All three values (E, D, T) must be valid
        }

        // Verify 'E' (Issuer) specifically exists in the enum
        $this->assertEquals('E', GeneratedByType::Issuer->value);
        $this->assertEquals(3, count(GeneratedByType::cases()));
    }
}

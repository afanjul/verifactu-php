<?php
namespace josemmo\Verifactu\Tests\Models\Exceptions;

use DateTimeImmutable;
use josemmo\Verifactu\Exceptions\InvalidModelException;
use josemmo\Verifactu\Models\Records\CancellationRecord;
use josemmo\Verifactu\Models\Records\InvoiceIdentifier;
use PHPUnit\Framework\TestCase;

final class InvalidModelExceptionTest extends TestCase {
    public function testGeneratesHumanRepresentation(): void {
        $record = new CancellationRecord();
        $record->invoiceId = new InvoiceIdentifier();
        $record->invoiceId->issuerId = '89890001K';
        $record->invoiceId->invoiceNumber = '12345679/G34';
        $record->invoiceId->issueDate = new DateTimeImmutable('2024-01-01');
        $record->previousInvoiceId = new InvoiceIdentifier();
        $record->previousInvoiceId->issuerId = '89890001K';
        $record->previousInvoiceId->invoiceNumber = '12345679/G34';
        $record->previousInvoiceId->issueDate = new DateTimeImmutable('2024-01-01');
        $record->previousHash = null; // Missing hash with previousInvoiceId set
        $record->hashedAt = new DateTimeImmutable('2024-01-01T19:20:40+01:00');
        $record->hash = $record->calculateHash();
        try {
            $record->validate();
            $this->fail('Did not throw exception');
        } catch (InvalidModelException $e) {
            $actual = $e->getMessage();
            $expected = <<<TXT
            Invalid instance of model class:
            - Object(josemmo\Verifactu\Models\Records\CancellationRecord).previousHash:
                Previous hash is required if previous invoice ID is provided
            TXT;
            $this->assertEquals($expected, $actual);
        }
    }
}

<?php
namespace josemmo\Verifactu\Tests\Chain;

use DateTimeImmutable;
use josemmo\Verifactu\Chain\InMemoryChainRepository;
use josemmo\Verifactu\Models\Records\CancellationRecord;
use josemmo\Verifactu\Models\Records\InvoiceIdentifier;
use PHPUnit\Framework\TestCase;

final class InMemoryChainRepositoryTest extends TestCase {
    public function testReturnsNullForEmptyChain(): void {
        $repo = new InMemoryChainRepository();
        $this->assertNull($repo->getLastRecord('A00000000'));
    }

    public function testSavesAndRetrievesRecord(): void {
        $repo = new InMemoryChainRepository();

        $record = new CancellationRecord();
        $record->invoiceId = new InvoiceIdentifier('A00000000', 'FACT-001', new DateTimeImmutable('2025-01-01'));
        $record->previousInvoiceId = null;
        $record->previousHash = null;
        $record->hashedAt = new DateTimeImmutable('2025-01-01T10:00:00+01:00');
        $record->hash = $record->calculateHash();

        $repo->saveRecord($record);

        $lastRecord = $repo->getLastRecord('A00000000');
        $this->assertNotNull($lastRecord);
        $this->assertEquals($record->invoiceId, $lastRecord['invoiceId']);
        $this->assertEquals($record->hash, $lastRecord['hash']);
    }

    public function testOverwritesPreviousRecord(): void {
        $repo = new InMemoryChainRepository();

        $record1 = new CancellationRecord();
        $record1->invoiceId = new InvoiceIdentifier('A00000000', 'FACT-001', new DateTimeImmutable('2025-01-01'));
        $record1->previousInvoiceId = null;
        $record1->previousHash = null;
        $record1->hashedAt = new DateTimeImmutable('2025-01-01T10:00:00+01:00');
        $record1->hash = $record1->calculateHash();
        $repo->saveRecord($record1);

        $record2 = new CancellationRecord();
        $record2->invoiceId = new InvoiceIdentifier('A00000000', 'FACT-002', new DateTimeImmutable('2025-01-02'));
        $record2->previousInvoiceId = $record1->invoiceId;
        $record2->previousHash = $record1->hash;
        $record2->hashedAt = new DateTimeImmutable('2025-01-02T10:00:00+01:00');
        $record2->hash = $record2->calculateHash();
        $repo->saveRecord($record2);

        $lastRecord = $repo->getLastRecord('A00000000');
        $this->assertNotNull($lastRecord);
        $this->assertEquals('FACT-002', $lastRecord['invoiceId']->invoiceNumber);
        $this->assertEquals($record2->hash, $lastRecord['hash']);
    }

    public function testSeparatesChainsByIssuer(): void {
        $repo = new InMemoryChainRepository();

        $record1 = new CancellationRecord();
        $record1->invoiceId = new InvoiceIdentifier('A00000000', 'FACT-001', new DateTimeImmutable('2025-01-01'));
        $record1->previousInvoiceId = null;
        $record1->previousHash = null;
        $record1->hashedAt = new DateTimeImmutable('2025-01-01T10:00:00+01:00');
        $record1->hash = $record1->calculateHash();
        $repo->saveRecord($record1);

        $record2 = new CancellationRecord();
        $record2->invoiceId = new InvoiceIdentifier('B11111111', 'FACT-001', new DateTimeImmutable('2025-01-01'));
        $record2->previousInvoiceId = null;
        $record2->previousHash = null;
        $record2->hashedAt = new DateTimeImmutable('2025-01-01T11:00:00+01:00');
        $record2->hash = $record2->calculateHash();
        $repo->saveRecord($record2);

        $lastA = $repo->getLastRecord('A00000000');
        $lastB = $repo->getLastRecord('B11111111');
        $this->assertNotNull($lastA);
        $this->assertNotNull($lastB);
        $this->assertEquals($record1->hash, $lastA['hash']);
        $this->assertEquals($record2->hash, $lastB['hash']);
    }
}

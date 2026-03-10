<?php
namespace josemmo\Verifactu\Chain;

use josemmo\Verifactu\Models\Records\InvoiceIdentifier;
use josemmo\Verifactu\Models\Records\Record;

/**
 * In-memory implementation of ChainRepositoryInterface.
 *
 * Suitable for testing or single-request scenarios. Data is not persisted
 * between requests. For production use, implement your own persistent
 * repository (e.g., database-backed).
 */
class InMemoryChainRepository implements ChainRepositoryInterface {
    /** @var array<string, array{invoiceId: InvoiceIdentifier, hash: string}> */
    private array $records = [];

    /**
     * @inheritDoc
     */
    public function getLastRecord(string $issuerId): ?array {
        return $this->records[$issuerId] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function saveRecord(Record $record): void {
        $this->records[$record->invoiceId->issuerId] = [
            'invoiceId' => $record->invoiceId,
            'hash' => $record->hash,
        ];
    }
}

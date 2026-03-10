<?php
namespace josemmo\Verifactu\Chain;

use josemmo\Verifactu\Models\Records\InvoiceIdentifier;
use josemmo\Verifactu\Models\Records\Record;

/**
 * Repository interface for managing the hash chain of invoice records.
 *
 * Consumers must implement this interface to persist and retrieve the last
 * record in the chain for each issuer (NIF), ensuring correct hash chaining
 * across submissions.
 */
interface ChainRepositoryInterface {
    /**
     * Get the last record in the chain for the given issuer
     *
     * @param string $issuerId NIF of the invoice issuer
     *
     * @return array{invoiceId: InvoiceIdentifier, hash: string}|null Last chain record or null if chain is empty
     */
    public function getLastRecord(string $issuerId): ?array;

    /**
     * Save a record as the latest in the chain for its issuer
     *
     * @param Record $record Record to save
     */
    public function saveRecord(Record $record): void;
}

<?php
namespace josemmo\Verifactu\Models\Responses;

/**
 * Estado del registro duplicado (L21)
 */
enum DuplicateRecordStatus: string {
    case Correct = 'Correcto';
    case AcceptedWithErrors = 'AceptadoConErrores';
    case Cancelled = 'Anulada';
}

<?php
namespace josemmo\Verifactu\Models\Records;

/**
 * Indicador de rechazo previo (L17)
 */
enum PreviousRejectionType: string {
    /** No es rechazo previo */
    case N = 'N';

    /** El registro fue rechazado y fue remitido previamente a la AEAT */
    case S = 'S';

    /** El registro fue rechazado pero no fue remitido previamente a la AEAT */
    case X = 'X';
}

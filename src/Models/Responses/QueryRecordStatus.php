<?php
namespace josemmo\Verifactu\Models\Responses;

/**
 * Estado del registro almacenado en el sistema (respuesta de consulta)
 *
 * Diferente de ItemStatus (para respuesta de envío), estos estados reflejan
 * la situación del registro ya persistido en AEAT.
 */
enum QueryRecordStatus: string {
    /** El registro se almacenó sin errores */
    case Correct = 'Correcto';

    /** El registro se almacenó con errores no impeditivos */
    case AcceptedWithErrors = 'AceptadoConErrores';

    /** El registro fue anulado mediante una operación de anulación */
    case Cancelled = 'Anulado';
}

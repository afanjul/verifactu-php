<?php
namespace josemmo\Verifactu\Models\Records;

/**
 * Identificación del generador del registro de anulación (L16)
 */
enum GeneratedByType: string {
    /** El expedidor (obligado a expedir la factura) generó el registro de anulación */
    case Issuer = 'E';

    /** El destinatario generó el registro de anulación */
    case Recipient = 'D';

    /** Un tercero generó el registro de anulación */
    case ThirdParty = 'T';
}

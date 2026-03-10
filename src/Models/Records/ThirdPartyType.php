<?php
namespace josemmo\Verifactu\Models\Records;

/**
 * Indicador de si la factura fue emitida por un tercero o por el destinatario (L6)
 */
enum ThirdPartyType: string {
    /** El destinatario emite la factura en nombre del emisor */
    case Recipient = 'D';

    /** Un tercero emite la factura en nombre del emisor */
    case ThirdParty = 'T';
}

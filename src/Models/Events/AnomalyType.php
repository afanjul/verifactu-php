<?php
namespace josemmo\Verifactu\Models\Events;

/**
 * Anomaly type (Tabla L1E)
 *
 * @field TipoAnomalia
 */
enum AnomalyType: string {
    /** Anomalía en la integridad de las huellas */
    case HashIntegrity = '01';

    /** Anomalía en la integridad de las firmas */
    case SignatureIntegrity = '02';

    /** Anomalía en la trazabilidad del encadenamiento */
    case ChainTraceability = '03';

    /** Anomalía en la trazabilidad de las fechas */
    case DateTraceability = '04';
}

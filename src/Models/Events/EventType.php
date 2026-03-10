<?php
namespace josemmo\Verifactu\Models\Events;

/**
 * Event type (Tabla L2E)
 *
 * @field TipoEvento
 */
enum EventType: string {
    /** Inicio del funcionamiento del sistema informático como «NO VERI*FACTU» */
    case StartNoVerifactu = '01';

    /** Fin del funcionamiento del sistema informático como «NO VERI*FACTU» */
    case StopNoVerifactu = '02';

    /** Lanzamiento del proceso de detección de anomalías en los registros de facturación */
    case InvoiceAnomalyCheck = '03';

    /** Detección de anomalías en la integridad, inalterabilidad y trazabilidad de registros de facturación */
    case InvoiceAnomalyDetected = '04';

    /** Lanzamiento del proceso de detección de anomalías en los registros de evento */
    case EventAnomalyCheck = '05';

    /** Detección de anomalías en la integridad, inalterabilidad y trazabilidad de registros de evento */
    case EventAnomalyDetected = '06';

    /** Restauración de copia de seguridad, cuando ésta se gestione desde el propio SIF */
    case BackupRestored = '07';

    /** Exportación de registros de facturación generados en un periodo */
    case InvoiceExport = '08';

    /** Exportación de registros de evento generados en un periodo */
    case EventExport = '09';

    /**
     * Registro resumen de eventos
     *
     * Must be generated at least every 6 hours of operation and when shutting down the system.
     */
    case Summary = '10';

    /** Otros tipos de eventos a registrar voluntariamente por la persona o entidad productora del SIF */
    case Other = '90';
}

Registro de Eventos
===================

Un **registro de evento** es un tipo de registro especial que documenta sucesos relevantes del Sistema Informático de Facturación (SIF): inicios/paradas, exportaciones, verificaciones de integridad y resúmenes periódicos de actividad.

.. note::

    Para los SIF que operan **exclusivamente** en modalidad VERI*FACTU (*SOLO VERI*FACTU*), el registro de eventos es **voluntario**.
    Solo es obligatorio en SIF duales o en los que puedan funcionar en modo *No VERI*FACTU*.


Tipos de evento
---------------

El campo ``TipoEvento`` se define mediante el enum :php:class:`josemmo\\Verifactu\\Models\\Events\\EventType` (tabla L2E de la normativa):

.. list-table::
   :header-rows: 1
   :widths: 10 25 65

   * - Valor
     - Constante PHP
     - Descripción
   * - ``01``
     - ``StartNoVerifactu``
     - Inicio del SIF en modalidad «NO VERI*FACTU»
   * - ``02``
     - ``StopNoVerifactu``
     - Fin del SIF en modalidad «NO VERI*FACTU»
   * - ``03``
     - ``InvoiceAnomalyCheck``
     - Lanzamiento del proceso de detección de anomalías en registros de facturación
   * - ``04``
     - ``InvoiceAnomalyDetected``
     - Anomalía detectada en la integridad/trazabilidad de registros de facturación
   * - ``05``
     - ``EventAnomalyCheck``
     - Lanzamiento del proceso de detección de anomalías en registros de evento
   * - ``06``
     - ``EventAnomalyDetected``
     - Anomalía detectada en la integridad/trazabilidad de registros de evento
   * - ``07``
     - ``BackupRestored``
     - Restauración de copia de seguridad gestionada desde el propio SIF
   * - ``08``
     - ``InvoiceExport``
     - Exportación de registros de facturación de un periodo
   * - ``09``
     - ``EventExport``
     - Exportación de registros de evento de un periodo
   * - ``10``
     - ``Summary``
     - Resumen de eventos (debe generarse al menos cada 6 horas de operatividad)
   * - ``90``
     - ``Other``
     - Otros eventos registrados voluntariamente por el productor del SIF


Generación de un registro de evento
------------------------------------

Crea un objeto :php:class:`josemmo\\Verifactu\\Models\\Events\\EventRecord` e informar sus campos obligatorios:

.. code-block:: php

    use DateTimeImmutable;
    use josemmo\Verifactu\Models\ComputerSystem;
    use josemmo\Verifactu\Models\Events\EventRecord;
    use josemmo\Verifactu\Models\Events\EventType;
    use josemmo\Verifactu\Models\Records\FiscalIdentifier;

    $event = new EventRecord();

    // Datos del SIF (el mismo objeto que usas para enviar facturas)
    $event->system = $computerSystem; // instancia de ComputerSystem ya validada

    // Obligado tributario al que pertenece esta cadena de eventos
    $event->issuer = new FiscalIdentifier('Empresa Ejemplo SL', 'B00000001');

    // Tipo de evento (ver enum EventType)
    $event->eventType = EventType::Summary;

    // Fecha y hora de generación del evento (con huso horario)
    $event->generatedAt = new DateTimeImmutable();


Encadenamiento
--------------

Al igual que los registros de facturación, los registros de evento deben encadenarse entre sí.
Cada cadena de eventos es independiente por obligado tributario.

**Primer evento del SIF** (o del obligado en sistemas multi-tenante):

.. code-block:: php

    $event->previousEventType = null;
    $event->previousGeneratedAt = null;
    $event->previousHash = null;

**Eventos sucesivos** (referencia al evento inmediatamente anterior):

.. code-block:: php

    use josemmo\Verifactu\Models\Events\EventType;

    $event->previousEventType    = EventType::Summary;
    $event->previousGeneratedAt  = $lastEvent->generatedAt;
    $event->previousHash         = $lastEvent->hash;


Cálculo del hash y validación
------------------------------

El hash se calcula automáticamente a partir de 9 campos específicos (Doc. Huella §3.3).
Solo debes llamar a ``calculateHash()`` y asignar el resultado:

.. code-block:: php

    use josemmo\Verifactu\Exceptions\InvalidModelException;

    $event->hash = $event->calculateHash();

    try {
        $event->validate();
    } catch (InvalidModelException $e) {
        echo "Registro de evento inválido: $e\n";
    }


Datos opcionales de evento
---------------------------

Según el ``TipoEvento``, puedes informar sub-bloques adicionales de ``DatosPropiosEvento``.

**Proceso de verificación de anomalías en registros de facturación** (tipos ``03`` / ``04``):

.. code-block:: php

    // Informar resultados del proceso de verificación de huellas (tipo 03)
    $event->invoiceHashIntegrityChecked    = true;   // ¿Se ejecutó el proceso?
    $event->invoiceHashIntegrityCount      = 1500;   // Nº de registros procesados
    $event->invoiceSignatureIntegrityChecked = false;
    $event->invoiceChainTraceabilityChecked  = true;
    $event->invoiceChainTraceabilityCount    = 1500;
    $event->invoiceDateTraceabilityChecked   = true;
    $event->invoiceDateTraceabilityCount     = 1500;

    // Anomalía detectada (tipo 04)
    use josemmo\Verifactu\Models\Events\AnomalyType;

    $event->eventType            = EventType::InvoiceAnomalyDetected;
    $event->invoiceAnomalyType   = AnomalyType::ChainTraceability;
    $event->invoiceAnomalyDetails = 'Descripción adicional opcional (max 100 chars)';

**Proceso de verificación de anomalías en registros de evento** (tipos ``05`` / ``06``):

.. code-block:: php

    // Funcionan de forma análoga pero con los campos event* en lugar de invoice*
    $event->eventHashIntegrityChecked    = true;
    $event->eventHashIntegrityCount      = 42;
    $event->eventSignatureIntegrityChecked = false;
    $event->eventChainTraceabilityChecked  = true;
    $event->eventChainTraceabilityCount    = 42;
    $event->eventDateTraceabilityChecked   = false;

    // Anomalía detectada en eventos (tipo 06)
    $event->eventType          = EventType::EventAnomalyDetected;
    $event->eventAnomalyType   = AnomalyType::HashIntegrity;
    $event->eventAnomalyDetails = 'Detalles adicionales opcionales';

**Nota sobre datos adicionales libres:**

.. code-block:: php

    // Campo libre para cualquier dato adicional que el SIF quiera registrar
    $event->additionalData = 'Información complementaria opcional (max 100 chars)';


Exportación e importación XML
-------------------------------

Los registros de evento usan un espacio de nombres XML diferente al de los registros de facturación: ``EventosSIF.xsd``.

.. code-block:: php

    use josemmo\Verifactu\Models\Events\EventRecord;
    use UXML\UXML;

    // Exportar
    $container = UXML::newInstance('container', null, ['xmlns:sf' => EventRecord::NS]);
    $event->export($container);
    $xml = $container->get('sf:RegistroEvento');
    echo $xml->asXML() . "\n";

    // Importar
    $data = file_get_contents(__DIR__ . '/path/to/evento.xml');
    $xml  = UXML::fromString($data);
    $event = EventRecord::fromXml($xml);
    $event->validate();

Consulta de registros de facturación
=====================================

La librería permite consultar los registros de facturación presentados en modo remisión voluntaria (VERI*FACTU) mediante la clase :php:class:`josemmo\\Verifactu\\Services\\AeatClient`.

Filtros de consulta
-------------------

Crea un :php:class:`josemmo\\Verifactu\\Models\\Queries\\QueryFilter` con el ejercicio y periodo a consultar (obligatorios), y opcionalmente otros filtros:

.. code-block:: php

    use josemmo\Verifactu\Models\Queries\QueryFilter;

    $filter = new QueryFilter();
    $filter->year   = 2025;   // Ejercicio (YYYY)
    $filter->period = '10';   // Periodo (01-12)

    // Filtros opcionales
    $filter->invoiceNumber = 'FACT-2025-001';   // Nº serie+factura
    $filter->externalRef   = 'pedido-999';      // Referencia externa

Filtro por fecha
~~~~~~~~~~~~~~~~

Puedes filtrar por una fecha de expedición exacta o por un rango. Ambas opciones son mutuamente excluyentes:

.. code-block:: php

    use DateTimeImmutable;

    // Fecha exacta
    $filter->exactIssueDate = new DateTimeImmutable('2025-10-15');

    // O rango de fechas
    $filter->issueDateFrom = new DateTimeImmutable('2025-10-01');
    $filter->issueDateTo   = new DateTimeImmutable('2025-10-31');

Filtro por contraparte
~~~~~~~~~~~~~~~~~~~~~~

Si la consulta la realiza el emisor, la contraparte es el destinatario (y viceversa):

.. code-block:: php

    use josemmo\Verifactu\Models\Records\FiscalIdentifier;
    use josemmo\Verifactu\Models\Records\ForeignFiscalIdentifier;
    use josemmo\Verifactu\Models\Records\ForeignIdType;

    // Contraparte con NIF español
    $filter->counterpart = new FiscalIdentifier('Empresa Destino S.L.', 'B12345678');

    // Contraparte extranjera
    $filter->foreignCounterpart = new ForeignFiscalIdentifier(
        'Acme Corp',
        'DE',
        ForeignIdType::VatNumber,
        'DE123456789'
    );

Realizar la consulta
--------------------

Usa el método ``query()`` del cliente ya configurado (ver :doc:`comunicacion`):

.. code-block:: php

    use josemmo\Verifactu\Models\Responses\QueryResult;

    $response = $client->query($filter)->wait();

    if ($response->result === QueryResult::WithData) {
        echo "Registros encontrados: " . count($response->items) . "\n";
    } else {
        echo "Sin resultados para el periodo indicado.\n";
    }

Opciones adicionales de respuesta
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

El método ``query()`` acepta dos parámetros opcionales que amplían la información devuelta:

.. code-block:: php

    $response = $client->query(
        $filter,
        showIssuerName: true,     // Incluir NombreRazonEmisor en cada registro
        showComputerSystem: true  // Incluir bloque SistemaInformatico (sólo consulta por emisor)
    )->wait();

Procesar los resultados
-----------------------

Cada elemento de ``$response->items`` es un :php:class:`josemmo\\Verifactu\\Models\\Responses\\QueryResponseItem`:

.. code-block:: php

    use josemmo\Verifactu\Models\Responses\QueryRecordStatus;

    foreach ($response->items as $item) {
        echo $item->invoiceId->invoiceNumber . ': ' . $item->status->value . "\n";

        if ($item->status === QueryRecordStatus::AcceptedWithErrors) {
            echo '  Error ' . $item->errorCode . ': ' . $item->errorDescription . "\n";
        }
    }

Los estados posibles de ``$item->status`` son:

- ``QueryRecordStatus::Correct`` — El registro se almacenó sin errores.
- ``QueryRecordStatus::AcceptedWithErrors`` — Almacenado con errores no impeditivos. Consulta ``$item->errorCode`` y ``$item->errorDescription``.
- ``QueryRecordStatus::Cancelled`` — El registro fue anulado.

Paginación
----------

La AEAT devuelve un máximo de 10.000 registros por consulta. Si hay más, ``$response->hasMorePages`` será ``true`` y ``$response->paginationKey`` contendrá la clave para solicitar la siguiente página:

.. code-block:: php

    use josemmo\Verifactu\Models\Responses\QueryResult;

    do {
        $response = $client->query($filter)->wait();

        foreach ($response->items as $item) {
            // procesar $item ...
        }

        // Preparar siguiente página si la hay
        if ($response->hasMorePages) {
            $filter->paginationKey = $response->paginationKey;
        }
    } while ($response->hasMorePages);

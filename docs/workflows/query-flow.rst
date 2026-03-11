Flujo de consulta
==================

Esta guía resume cómo consultar registros ya presentados en remisión voluntaria.

Preparación del filtro
----------------------

- Crea un :php:class:`josemmo\Verifactu\Models\Queries\QueryFilter`.
- Informa siempre `year` y `period`.
- Añade filtros opcionales como `invoiceNumber`, `externalRef`, contraparte o fechas.
- No combines `exactIssueDate` con `issueDateFrom` o `issueDateTo`.
- No combines `counterpart` con `foreignCounterpart`.
- Ejecuta `validate()` antes de llamar a `query()`.

Ejecución de la consulta
------------------------

- Reutiliza un :php:class:`josemmo\Verifactu\Services\AeatClient` ya configurado.
- Llama a `query($filter)->wait()`.
- Interpreta `QueryResponse::result` y recorre `items`.
- Si `hasMorePages` es `true`, reutiliza `paginationKey` para pedir la siguiente página.

Qué devuelve la respuesta
-------------------------

- `result`
  - indica si la consulta tiene datos
- `items`
  - contiene registros individuales con su `status`
- `paginationKey`
  - permite continuar una consulta paginada
- `year` y `period`
  - reflejan el periodo de consulta devuelto por AEAT

Siguientes lecturas
-------------------

- :doc:`../consulta`
- :doc:`validation-and-errors`

Flujo de envío a AEAT
======================

Esta guía resume el flujo recomendado para enviar registros de facturación a la AEAT usando :php:class:`josemmo\Verifactu\Services\AeatClient`.

Preparación del registro
------------------------

- Crea el registro de alta o anulación.
- Rellena su identificación de factura.
- Informa el encadenamiento con el registro previo si existe.
- Asigna `hashedAt` y calcula `hash`.
- Ejecuta `validate()`.

Preparación del sistema y cliente
---------------------------------

- Crea un :php:class:`josemmo\Verifactu\Models\ComputerSystem` con los datos del SIF.
- Ejecuta `validate()` sobre el sistema.
- Instancia :php:class:`josemmo\Verifactu\Services\AeatClient` con el sistema y el contribuyente.
- Configura `setCertificate()`.
- Usa `setProduction(false)` para preproducción.
- Configura, si aplica, `setRepresentative()`, `setVoluntaryRemissionEndDate()`, `setRequirementReference()` o `setEntitySeal()`.

Envío y manejo de respuesta
---------------------------

- Envía entre 1 y 1000 registros por llamada.
- Espera la promesa con `wait()`.
- Evalúa `AeatResponse::status` para conocer el resultado global.
- Recorre `AeatResponse::items` para ver el resultado individual de cada registro.
- Si `waitSeconds` está informado, usa `AeatClient::waitIfNeeded()` antes del siguiente lote.

Errores esperables
------------------

- :php:class:`josemmo\Verifactu\Exceptions\InvalidModelException`
  - cuando el modelo no cumple las restricciones declaradas
- :php:class:`josemmo\Verifactu\Exceptions\AeatException`
  - cuando AEAT devuelve un SOAP Fault o la respuesta no puede parsearse
- `ClientExceptionInterface`
  - cuando la capa HTTP falla durante la petición

Siguientes lecturas
-------------------

- :doc:`../comunicacion`
- :doc:`../operativas`
- :doc:`validation-and-errors`

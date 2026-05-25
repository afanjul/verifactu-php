Inicio rápido
==============

Este flujo resume la secuencia mínima para integrar la librería en un SIF propio.

Pasos
-----

1. Instala el paquete con Composer.
2. Crea y rellena un :php:class:`josemmo\Verifactu\Models\Records\RegistrationRecord` o un :php:class:`josemmo\Verifactu\Models\Records\CancellationRecord`.
3. Asigna la información de encadenamiento y calcula `hash` con `calculateHash()`.
4. Llama a `validate()` en el registro.
5. Crea y valida un :php:class:`josemmo\Verifactu\Models\ComputerSystem`.
6. Instancia :php:class:`josemmo\Verifactu\Services\AeatClient` con el contribuyente.
7. Configura certificado y entorno.
8. Envía el lote con `send([$record])->wait()` y recibe un `AeatSubmissionResult`.
9. Conserva `request->xml` y `response->xml` si necesitas evidencias de auditoría.
10. Revisa `status`, `csv` e `items` en `response`.

Invariantes del flujo
---------------------

- `validate()` debe llamarse después de rellenar todos los campos requeridos.
- `hashedAt` debe estar asignado antes de calcular `hash`.
- `previousInvoiceId` y `previousHash` deben estar ambos a `null` o ambos informados.
- `send()` solo acepta lotes entre 1 y 1000 registros.
- `send()` devuelve el XML SOAP exacto enviado y recibido junto a la respuesta parseada.
- Si AEAT devuelve `waitSeconds`, debe respetarse antes del siguiente envío.

Siguientes lecturas
-------------------

- :doc:`../generacion`
- :doc:`../comunicacion`
- :doc:`validation-and-errors`

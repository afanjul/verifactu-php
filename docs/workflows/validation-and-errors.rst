Validación y errores
====================

Esta guía agrupa las reglas prácticas que más afectan a integraciones y agentes de IA.

Validación
----------

Todos los modelos públicos heredan de :php:class:`josemmo\Verifactu\Models\Model` y comparten el método `validate()`.
Debe llamarse después de completar los campos requeridos y antes de usar el modelo en un flujo de envío, consulta o serialización controlada.

Excepciones públicas
--------------------

- :php:class:`josemmo\Verifactu\Exceptions\InvalidModelException`
  - se lanza cuando un modelo incumple restricciones de formato, obligatoriedad o dependencia entre campos
- :php:class:`josemmo\Verifactu\Exceptions\ImportException`
  - se lanza cuando un `fromXml()` recibe un XML inválido o incompleto
- :php:class:`josemmo\Verifactu\Exceptions\AeatException`
  - se lanza cuando falla la comunicación SOAP o la respuesta de AEAT no puede parsearse

Reglas que conviene recordar
----------------------------

- `hash` debe corresponder exactamente al valor calculado por `calculateHash()`.
- `previousInvoiceId` y `previousHash` deben usarse juntos.
- Los filtros de fechas de `QueryFilter` tienen exclusiones mutuas.
- Los filtros de contraparte de `QueryFilter` tienen exclusiones mutuas.
- `waitSeconds` no debe ignorarse si AEAT lo devuelve.

Qué deberían hacer los agentes consumidores
-------------------------------------------

- Priorizar `README.md`, `docs/` y `llms.txt` para descubrir el contrato público.
- Usar solo las clases listadas como puntos de entrada públicos.
- Evitar depender de helpers internos o detalles no documentados como contrato estable.
- No ocultar las excepciones públicas de la librería.

Siguientes lecturas
-------------------

- :doc:`../modelos`
- :doc:`../comunicacion`
- :doc:`../consulta`

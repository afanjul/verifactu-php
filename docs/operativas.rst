Operativas y Gestión de Errores
===============================

En VERI*FACTU, el ciclo de vida de un registro de facturación se rige por un conjunto estricto de **operativas de alta** y **operativas de anulación**, que permiten rectificar o subsanar los datos erróneos de un registro inicialmente enviado a la AEAT.

Tipos de Errores
----------------

Cuando se envían registros a la AEAT, la respuesta (AeatResponse) indicará si se han producido errores en algunos de ellos. La Agencia Tributaria distingue dos grandes grupos de errores asociados a las validaciones de negocio:

* **No admisibles (Rechazo Completo):** Provocan el rechazo inmediato del registro de facturación porque hay errores graves en su formato o las validaciones de negocio impiden su registro. Estos errores requieren que usted subsane la información enviada mediante una **Operativa Especial** y se vuelva a enviar para que conste validado en el sistema de la AEAT.
* **Admisibles (Aceptados con errores):** Errores no bloqueantes (por ejemplo, errores menores en el NIF del destinatario o pequeñas diferencias en totales admisibles). El registro se graba en los sistemas de la AEAT (es admitido), pero deberá ser subsanado *a posteriori* a través de una **Operativa de Subsanación**, para contar finalmente con unos datos válidos y correctos.


Operativas de Alta
------------------

Registrar una factura en VERI*FACTU implica crear un registro de **Alta**. Si es necesario, este registro puede someterse a subsanaciones, identificadas por medio de la propiedad booleana ``$isCorrection`` y el enumerado ``$isPriorRejection`` de la clase :php:class:`RegistrationRecord`.

.. list-table::
   :header-rows: 1

   * - Operativa
     - Clase PHP y Variables a establecer
     - Descripción y Condiciones
   * - **Alta Normal**
     - ``$record = new RegistrationRecord();``
     - Es un alta inicial estándar. El registro no debe existir previamente en la AEAT.
   * - **Alta por Rechazo**
     - ``$record->isPriorRejection = PreviousRejectionType::S;``
     - Intento de reenvío de un Alta Normal que fue previamente rechazada (Error No Admisible).
   * - **Alta de Subsanación**
     - ``$record->isCorrection = true;``
     - Su objetivo es corregir un registro de facturación previamente admitido o "aceptado con errores". En la AEAT, sustituye a los datos registrados anteriormente.
   * - **Alta por Rechazo de Subsanación**
     - ``$record->isCorrection = true; $record->isPriorRejection = PreviousRejectionType::S;``
     - Parecido al alta de subsanación anterior, pero este intento en sí fue rechazado previamente (por ejemplo, porque la subsanación contenía un error fatal). El origen de la factura previamente ya fue remitido o existía en los sistemas de la AEAT.
   * - **Alta de Subsanación Sin Registro Previo**
     - ``$record->isCorrection = true; $record->isPriorRejection = PreviousRejectionType::X;``
     - Subsanación de una factura existente localmente en el SIF, pero que *no llegó a ser remitida* a la AEAT (por ejemplo, el SIF antes funcionaba en modo "No VERI*FACTU").


Operativas de Anulación
-----------------------

Si una factura no debió emitirse o necesita ser eliminada por completo de efectos fiscales, se utilizará un registro de **Anulación**, implementado a través de la clase :php:class:`CancellationRecord`.

.. list-table::
   :header-rows: 1

   * - Operativa
     - Clase PHP y Variables a establecer
     - Descripción y Condiciones
   * - **Anulación Normal**
     - ``$record = new CancellationRecord();``
     - Anulación habitual de una factura cuyo registro original existe en la AEAT.
   * - **Anulación por Rechazo**
     - ``$record->isPriorRejection = true;``
     - Cuando en un envío previo se intentó una "Anulación Normal", pero la AEAT rechazó el registro de anulación por datos inválidos.
   * - **Anulación Sin Registro Previo**
     - ``$record->withoutPriorRecord = true;``
     - Anulación de una factura registrada inicialmente en el SIF, pero que aún no había sido enviada a la AEAT (caso de cambio de NO VERI*FACTU a VERI*FACTU, o envío agendado que nunca ocurrió).
   * - **Anulación por Rechazo Sin Registro Previo**
     - ``$record->isPriorRejection = true; $record->withoutPriorRecord = true;``
     - Cuando la "Anulación sin registro previo" se intentó enviar, pero la AEAT la rechazó.

Modelos y validación
====================

Verifactu-PHP proporciona una serie de clases llamadas **modelos** que representan los distintos elementos de un registro VERI*FACTU.
Todos los modelos de la librería heredan de la clase :php:class:`josemmo\Verifactu\Models\Model`.
Algunos de los ejemplos más reprensentativos de modelos de esta librería son:

* :php:class:`josemmo\Verifactu\Models\Records\RegistrationRecord`: Clase principal para un registro de alta.
* :php:class:`josemmo\Verifactu\Models\Records\FiscalIdentifier`: Identificación fiscal de una entidad (nombre y NIF). Se usa principalmente para identificar al responsable tributario en el envío.
* :php:class:`josemmo\Verifactu\Models\Records\ForeignFiscalIdentifier`: Identificación fiscal para contrapartes o emisores extranjeros, como empresas intracomunitarias.
* :php:class:`josemmo\Verifactu\Models\Records\BreakdownDetails`: Detalle de desglose fiscal por cada tipo impositivo aplicado.
* :php:class:`josemmo\Verifactu\Models\Records\CorrectiveType`: Enum utilizado en facturas rectificativas para definir la naturaleza de la rectificación (Sustitución o Diferencias).
* :php:class:`josemmo\Verifactu\Models\Records\ThirdPartyType`: Enum para identificar a un tercero o al destinatario como emisor de la factura.
* :php:class:`josemmo\Verifactu\Models\ComputerSystem`: Datos del sistema informático emisor (SIF). Este objeto se usa para informar a AEAT sobre el software que genera los registros.
* :php:class:`josemmo\Verifactu\Models\Responses\AeatSubmissionResult`: Resultado de un envío a AEAT, con la petición SOAP enviada y la respuesta parseada.
* :php:class:`josemmo\Verifactu\Models\Responses\AeatRequest`: XML SOAP exacto enviado a AEAT.
* :php:class:`josemmo\Verifactu\Models\Responses\AeatResponse`: Datos parseados de una respuesta recibida de AEAT y XML SOAP exacto recibido.
* :php:class:`josemmo\Verifactu\Models\Events\EventRecord`: Registro de evento del SIF (inicio/parada, anomalías, exportaciones, resumen periódico). Véase :doc:`eventos`.
* :php:class:`josemmo\Verifactu\Models\Events\EventType`: Enum con los tipos de evento de la tabla L2E (valores ``01``--``10`` y ``90``).

Validación y Excepciones
------------------------

Todos los modelos disponen del método :php:method:`josemmo\Verifactu\Models\Model::validate()`, que revisa las reglas de formato y presencia de datos obligatorios según la normativa.
Si algún campo tiene un valor incorrecto, este método lanzará una excepción del tipo :php:class:`josemmo\Verifactu\Exceptions\InvalidModelException`.

Es importante destacar que la librería también hace uso de otras excepciones clave durante la generación y el envío de registros:

* :php:class:`josemmo\Verifactu\Exceptions\AeatException`: Lanzada cuando el cliente (``AeatClient``) experimenta errores HTTP, fallos de conexión SOAP, o problemas en el uso de los certificados SSL con el servidor de la Agencia Tributaria.
* :php:class:`josemmo\Verifactu\Exceptions\ImportException`: Lanzada al intentar importar o leer documentos XML inválidos o carentes de etiquetas obligatorias a través de los métodos de deserialización estáticos como ``fromXml()``.

A continuación se muestra un ejemplo de uso:

.. code-block:: php

    use josemmo\Verifactu\Exceptions\InvalidModelException;
    use josemmo\Verifactu\Models\Records\BreakdownDetails;

    $details = new BreakdownDetails();
    $details->taxType = TaxType::IVA;
    $details->regimeType = RegimeType::C01;
    $details->operationType = OperationType::Subject;
    $details->baseAmount = '100.00';
    $details->taxRate = '10.00';
    $details->taxAmount = '12.34'; // <-- Debería ser 10.00
    try {
        $details->validate();
    } catch (InvalidModelException $e) {
        echo "Not a valid model: $e\n";
    }

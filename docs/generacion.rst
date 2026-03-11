Generación de registros
=======================

Un **registro de facturación** es un tipo concreto de modelo de datos que indica la creación, modificación o anulación de una factura.
Todos los registros deben ir encadenados con el registro anterior, salvo en el caso del primer registro emitido por el SIF.

Existen dos tipos de registros según la especificación de VERI*FACTU:

* **Registro de facturación de alta**: Utilizado para notificar los datos de una nueva factura que acaba de ser emitida, o la subsanación/modificación de los mismos en caso de un error.
* **Registro de facturación de anulación**: Para anular un registro de alta emitido anteriormente.

.. note::

    Los registros de anulación solo deben usarse en caso muy concretos, y rara vez tienen sentido.
    En el caso de que necesites "anular" una factura, debes emitir una **factura rectificativa** y crear el registro de alta correspondiente.

    Por ejemplo:

    1. Se emite la factura FAC2026/123 por un total de 100,00 €, que ahora queremos "anular"
    2. Se encadena un registro de facturación de alta para la factura FAC2026/123
    3. Se emite la factura rectificativa REC2026/001 por un total de -100,00 €
    4. Se encadena un registro de facturación de alta para la factura REC2026/001


Registros de alta
-----------------

Para generar un registro de alta de una nueva factura emitida usando Verifactu-PHP, sigue los siguientes pasos:

Crea el objeto :php:class:`josemmo\Verifactu\Models\Records\RegistrationRecord` y asigna la información básica de la cabecera de la factura:

.. code-block:: php

    use DateTimeImmutable;
    use josemmo\Verifactu\Models\Records\InvoiceIdentifier;
    use josemmo\Verifactu\Models\Records\InvoiceType;
    use josemmo\Verifactu\Models\Records\RegistrationRecord;

    $record = new RegistrationRecord();
    $record->invoiceId = new InvoiceIdentifier();
    $record->invoiceId->issuerId  = 'A00000000'; // NIF del emisor
    $record->invoiceId->invoiceNumber = 'TICKET-2025-002'; // Número de factura
    $record->invoiceId->issueDate = new DateTimeImmutable('2026-01-01'); // Fecha de emisión
    $record->issuerName = 'Nombre del Emisor, S.A.';
    $record->invoiceType = InvoiceType::Simplificada; // Tipo de factura
    $record->description = 'Factura simplificada de prueba';

Añade el desglose de totales de la factura.
No debes detallar el importe de cada línea de la factura, sino la suma de la base imposible y la cuota de cada impuesto:

.. code-block:: php

    use josemmo\Verifactu\Models\Records\BreakdownDetails;
    use josemmo\Verifactu\Models\Records\OperationType;
    use josemmo\Verifactu\Models\Records\RegimeType;
    use josemmo\Verifactu\Models\Records\TaxType;

    $record->breakdown[] = new BreakdownDetails();
    $record->breakdown[0]->taxType = TaxType::IVA;
    $record->breakdown[0]->regimeType = RegimeType::C01;
    $record->breakdown[0]->operationType = OperationType::Subject; // Sujeto a IVA
    $record->breakdown[0]->baseAmount = '10.00'; // Base imponible
    $record->breakdown[0]->taxRate = '21.00'; // Tipo de IVA
    $record->breakdown[0]->taxAmount = '2.10'; // Cuota de IVA


Define los totales de la factura:

.. code-block:: php

    $record->totalTaxAmount = '2.10';
    $record->totalAmount = '12.10';

Encadena la factura con la anterior:

.. code-block:: php

    // A. Si este registro es el primero que emite el SIF
    $record->previousInvoiceId = null;
    $record->previousHash = null;

    // B. Si hay más registros antes de este (lo normal)
    $record->previousInvoiceId = new InvoiceIdentifier('A00000000', 'TICKET-2025-001', new DateTimeImmutable('2025-12-31'));
    $record->previousHash = 'F7B94CFD8924EDFF273501B01EE5153E4CE8F259766F88CF6ACB8935802A2B97';


Calcula y asigna el hash del registro.
Este proceso es prácticamente autómatico y lo gestiona Verifactu-PHP, aunque tienes que pedirle a la librería que lo haga:

.. code-block:: php

    use DateTimeImmutable;

    $record->hashedAt = new DateTimeImmutable(); // Fecha y hora actual
    $record->hash = $record->calculateHash();

Puedes comprobar que el registro que has creado es correcto usando el método :php:method:`josemmo\Verifactu\Models\Model::validate()`:

.. code-block:: php

    use josemmo\Verifactu\Exceptions\InvalidModelException;

    try {
        $record->validate();
    } catch (InvalidModelException $e) {
        echo "Not a valid record: $e\n";
    }

Campos adicionales y facturas rectificativas
--------------------------------------------

La clase :php:class:`RegistrationRecord` incluye multitud de campos opcionales y banderas lógicas para adaptarse a las distintas operativas más inusuales que recoge el reglamento:

* ``$isSimplifiedArt7273`` y ``$withoutRecipientIdArt61d``: Booleanos que indican si se trata de una factura simplificada cualificada o sin identificación del destinatario según los reglamentos de facturación.
* ``$isMacrodato``: Booleano obligatorio y que debe pasarse a ``true`` cuando el importe de una factura excede o es igual a 100.000.000 €.
* ``$hasCoupon``: Para indicar si la operación consta del canje de cupones, tarjetas de fidelidad o prepago.
* Facturaciones por terceros: Uso de ``$issuedByThirdParty`` con un valor del enumerado :php:class:`ThirdPartyType` junto con los datos fiscales de dicho tercero a través del atributo ``$thirdParty``.
* Acuerdos de facturación: ``$systemAgreementId`` y ``$billingAgreementNumber`` proporcionan el identificador técnico en aquellos casos donde haya un marco de facturación externo subcontratado.

Adicionalmente, si el registro de alta trata sobre una **factura rectificativa**, debes incluir el tipo de corrección con las facturas dependientes:

.. code-block:: php

    use josemmo\Verifactu\Models\Records\CorrectiveType;

    // Ejemplo para Rectificativa por Diferencias
    $record->correctiveType = CorrectiveType::Differences;
    $record->correctedInvoices[] = new InvoiceIdentifier('A00000000', 'FACT2025-001', new DateTimeImmutable('2025-10-15'));

    // Ejemplo para Rectificativa por Sustitución
    $record->correctiveType = CorrectiveType::Substitution;
    $record->replacedInvoices[] = new InvoiceIdentifier('A00000000', 'FACT2025-001', new DateTimeImmutable('2025-10-15'));
    $record->correctedBaseAmount = '100.00';
    $record->correctedTaxAmount = '21.00';

Recuerda siempre llamar la método ``validate()`` para que la librería se encargue de comprobar estas estrictas obligaciones de interdependencia de campos entre sí antes del envío a la AEAT.

Registros de anulación
----------------------

Para un registro de anulación (factura cancelada), se genera un nuevo :php:class:`josemmo\Verifactu\Models\Records\CancellationRecord` vinculando al registro original.
En la práctica, la factura original debe conservarse con su registro y además registrarse la anulación con importes negativos.
La normativa indica que este "registro de anulación" debe referenciar la factura cancelada para mantener la integridad de la cadena.

Los registros de anulación se generar de forma muy similar a los de alta, aunque disponen de menos campos:

.. code-block:: php

    use DateTimeImmutable;
    use josemmo\Verifactu\Models\Records\CancellationRecord;
    use josemmo\Verifactu\Models\Records\InvoiceIdentifier;

    $record = new CancellationRecord();
    $record->invoiceId = new InvoiceIdentifier();
    $record->invoiceId->issuerId = '89890001K';
    $record->invoiceId->invoiceNumber = '12345679/G34';
    $record->invoiceId->issueDate = new DateTimeImmutable('2024-01-01');
    $record->previousInvoiceId = new InvoiceIdentifier();
    $record->previousInvoiceId->issuerId = '89890001K';
    $record->previousInvoiceId->invoiceNumber = '12345679/G34';
    $record->previousInvoiceId->issueDate = new DateTimeImmutable('2024-01-01');
    $record->hashedAt = new DateTimeImmutable();
    $record->hash = $record->calculateHash();
    $record->validate();

Exportación a XML
-----------------

Todos los registros de esta librería pueden exportarse a su representación en XML usando el método :php:method:`josemmo\Verifactu\Models\Records\Record::export()`.
Por ejemplo, para el registro de alta que hemos creado anteriormente, podemos obtener su XML de esta forma:

.. code-block:: php

    use josemmo\Verifactu\Models\Records\Record;
    use UXML\UXML;

    $exportedXml = UXML::newInstance('container', null, ['xmlns:sum1' => Record::NS]);
    $record->export($exportedXml, $computerSystem);
    echo $exportedXml->get('sum1:RegistroAlta')->asXML() . "\n";

Importación desde XML
---------------------

Al igual que puedes exportar registros, Verifactu-PHP permite rellenar los campos de estos a través de un documento de XML ya existente usando el método estático :php:method:`josemmo\Verifactu\Models\Records\Record::fromXml()`:

.. code-block:: php

    use josemmo\Verifactu\Models\Records\Record;
    use UXML\UXML;

    $data = file_get_contents(__DIR__ . '/path/to/registro.xml');
    if ($data === false) {
        throw new RuntimeException('Failed to read document');
    }
    $xml = UXML::fromString($data);
    $record = Record::fromXml($xml);
    $record->validate();

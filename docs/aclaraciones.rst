Aclaraciones Oficiales (Desarrolladores)
========================================

A lo largo del desarrollo de un Sistema Informático de Facturación (SIF) que cumpla con los requisitos del Reglamento (RD 1007/2023) y la Orden Ministerial de VERI*FACTU, pueden presentarse distintos escenarios o procesos contables complejos donde la especificación no es inmediatamente intuitiva.

Esta sección recoge las aclaraciones oficiales de la Administración (AEAT) aportadas para aquellos SIF que integren esta librería (Verifactu-PHP).

Sistemas Multipropósito e Instalación
--------------------------------------

Un SIF puede estar diseñado para permitir distintos tipos de facturación, o incluso estar preparado para escenarios normativos paralelos (VERI*FACTU VS. TicketBAI).
No obstante, la AEAT es tajante sobre las configuraciones e integraciones:

* **Configuración Estática:** No se permite que el SIF esté configurándose y reconfigurándose "dinámicamente" a la hora de emitir. Una vez que un entorno de usuario está configurado bajo VERI*FACTU, este modo permanecerá activo sin interrupciones arbitrarias.
* **Número de Instalación (``installationNumber``):** El "número de instalación" de cada SIF en el modelo SIF (``ComputerSystem``) debe ser unívoco por obligado tributario. La recomendación es formarlo con un *Timestamp* en combinación con un número secuencial; la librería no restringe este valor, pero debes asegurarte de su unicidad. En sistemas alojados en la nube (SaaS), cada usuario final (o empresa que factura) supone un número de instalación o "entorno distinto" independiente.

Facturas de "prueba" o Borradores
---------------------------------

* **Entornos en Producción:** La AEAT indica claramente que **NO** existen "facturas de prueba" que lleguen a expedirse desde un SIF que ya esté actuando y facturando plenamente en modo VERI*FACTU. Todo documento expedido en producción que actúe en pruebas ha de ser *real* (contendría código QR, registro de Alta validado, etc.) y no poseería valor fiscal, debiendo ser procesado inmediatamente como **Anulado** para revertir los efectos, con su consiguiente envío de Anulación.
* **Proformas, tickets o borradores:** El proceso de un albarán, borrador o factura previsualizable nunca dispara el proceso de envío a la AEAT y, por tanto, tampoco se usa la librería. Estos son documentos internos "sin validez fiscal".

Equivalencia con TicketBAI
--------------------------

Las claves de **No Sujeción** (``RegimeType`` o ``OperationType``) difieren con respecto a las establecidas en las normativas forales vascas. Existen las siguientes analogías oficiales:

* **TicketBAI ("OT"):** En VERI*FACTU, corresponde a **"N1"** (Operación no sujeta por artículo 7, 14 u otros).
* **TicketBAI ("RL" e "IE"):** En VERI*FACTU, corresponde a **"N2"** (Operación no sujeta por Reglas de Localización).
* **TicketBAI ("VT" - Suplidos):** En VERI*FACTU, **NO** tiene correspondencia ni se incluye directamente en la huella y desglose fiscal del registro de facturación de alta (salvo que varíen el *"Total a Pagar"* del documento al cliente). 

Criterio de Caja
----------------

Para aplicar el **Régimen Especial del Criterio de Caja (Cód. "07" en ``RegimeType``)**, hay restricciones:

* Un vendedor peninsular adscrito al criterio de caja que facture servicios hacia Canarias, opera bajo un escenario exento del TAI español y no entra en el Criterio de Caja. En la librería: ``RegimeType::C08`` indicando operación no sujeta a IVA (``OperationType::NotSubjectByLocation`` / **N2**).
* Estas exclusiones afectan a **servicios intracomunitarios, de Inversión del Sujeto Pasivo, hacia/desde Canarias (IGIC, IPSI, etc)**.

Impuesto General Indirecto Canario (IGIC) e IPSI
------------------------------------------------

Cuando emitas una factura con **IGIC** o **IPSI**, recuerda que el modelo de registro incluye el impuesto como metadato del desglose:

* ``TaxType::IGIC`` (código 03): Lleva una de las claves de régimen especial del IGIC. Adicionalmente, ciertas exenciones tienen equivalencia a "E6" (Exenta por otros).
* ``TaxType::IPSI`` (código 02): Este modelo de impuesto en un régimen simplificado habitualmente **no debe enviar ``RegimeType``**. Simplemente queda sin definir en el bloque de desglose del ``RegistrationRecord``.
* **Un mismo registro o XML puede poseer operaciones mixtas.** Por ejemplo, un registro de una única factura puede presentar un desglose gravado por IVA, y a la vez, otro desglose indicativo de IGIC.

Importe Total y Suplidos
------------------------

El Importe Total fiscal requerido por la AEAT y reportado en el ``RegistrationRecord->totalAmount`` **NO** debe contemplar retenciones bancarias (IRPF o IS), suplidos de otras terceras partes u otros recargos financieros no tributarios que solo aplican al concepto del cliente como *"Total pagadero"*. Estos montos se excluyen voluntariamente y únicamente se incluirán la Base Imponible, la Cuota Repercutida y sus Regargos.

Requerimiento de Facturas "Importes y Extranjeros"
--------------------------------------------------

* Para facturas procedentes de software subcontratado ajeno, que solo te transfiere *la responsabilidad* (por lo que tú eres un importador/intermediario documental), tu SIF original certificado de emisión **NO** los reimprime ni los procesa con esta librería asumiendo el QR de generación.
* Las facturas con el artículo 15 de IVA Pendiente en AAPP llevan en el modelo la ``issueDate``, y consecuentemente se retrasará u omitirá el proceso contable sobre ellas según regulaciones; esto significa que las facturas no se anulan/alta cada vez que cambian de anticipos ni nada.

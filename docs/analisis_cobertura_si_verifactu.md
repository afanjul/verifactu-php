# Analisis de cobertura normativa (SI VERI*FACTU)

## Alcance de esta revision

Este documento evalua el repositorio **solo** para la modalidad de **remision voluntaria de un SIF que opera como SI VERI*FACTU**.

En consecuencia:

- Se consideran requisitos aplicables los relacionados con generacion de registros, remision SOAP a AEAT, respuesta, hash, QR y trazabilidad minima para este modo.
- Se marcan como **no necesario cubrir** las funcionalidades que derivan del modo **No VERI*FACTU** o de la **remision por requerimiento**, como la firma electronica por registro, el libro de eventos o el bloque de requerimiento.
- La evaluacion sigue midiendo la cobertura del **repositorio actual**, no la de un SIF final construido encima.

## 1. Inventario de requisitos

### 1.1 Requisitos aplicables al alcance SI VERI*FACTU

#### GEN - Requisitos generales del sistema

- **GEN-001** - Garantizar integridad, inalterabilidad y trazabilidad de los registros de facturacion.
- **GEN-002** - Operar como SIF en modalidad **VERI*FACTU** con remision inmediata/telematica.
- **GEN-003** - Identificar correctamente el `SistemaInformatico` (`NombreRazon`, `NIF`, `IdSistemaInformatico`, `Version`, `NumeroInstalacion`).
- **GEN-004** - Declarar correctamente capacidades multi-obligado tributario (`TipoUsoPosibleMultiOT`, `IndicadorMultiplesOT`).
- **GEN-005** - Evitar cambio dinamico de producto/SIF o de marco normativo operativo.

#### REG - Generacion de registros de facturacion

- **REG-001** - Construir mensajes de remision con `Cabecera` y hasta 1000 `RegistroFactura`.
- **REG-002** - Informar `Cabecera/ObligadoEmision`.
- **REG-003** - Soportar `Cabecera/Representante` cuando aplique.
- **REG-004** - Soportar `Cabecera/RemisionVoluntaria` (`FechaFinVeriFactu`, `Incidencia`) cuando aplique.
- **REG-005** - Generar `RegistroAlta` con sus campos nucleares.
- **REG-006** - Cubrir rectificativas/sustitutivas y sus reglas (`TipoRectificativa`, facturas rectificadas/sustituidas, importes rectificados).
- **REG-007** - Modelar destinatarios nacionales y extranjeros con sus reglas.
- **REG-008** - Cubrir campos condicionales de tipologia (`EmitidaPorTerceroODestinatario`, `Tercero`, `Macrodato`, `Cupon`, etc.).
- **REG-009** - Cubrir `Desglose/DetalleDesglose` y sus reglas fiscales.
- **REG-010** - Validar `CuotaTotal` e `ImporteTotal`.
- **REG-011** - Incluir encadenamiento, `SistemaInformatico`, `FechaHoraHusoGenRegistro`, `TipoHuella` y `Huella` en `RegistroAlta`.
- **REG-012** - Generar `RegistroAnulacion` con sus campos nucleares.
- **REG-013** - Soportar `GeneradoPor` y `Generador` en anulacion cuando aplique.
- **REG-014** - Incluir encadenamiento, `SistemaInformatico`, `FechaHoraHusoGenRegistro`, `TipoHuella` y `Huella` en `RegistroAnulacion`.
- **REG-015** - Cubrir operativas AEAT de alta/subsanacion/rechazo en remision voluntaria.
- **REG-016** - Cubrir operativas AEAT de anulacion/rechazo en remision voluntaria.

#### HASH - Encadenamiento criptografico

- **HASH-001** - Usar SHA-256 y salida de 64 hex mayusculas.
- **HASH-002** - Construir el payload exacto del hash de `RegistroAlta`.
- **HASH-003** - Construir el payload exacto del hash de `RegistroAnulacion`.
- **HASH-004** - Realizar los chequeos automaticos previos de encadenamiento/fechas exigidos al generar un nuevo RF.

#### FIRMA - Alcance aplicable en SI VERI*FACTU

- **FIRMA-001** - Usar certificado admitido/cualificado para la **remision** a AEAT.

#### QR - Codigo QR

- **QR-001** - Generar la URL del QR con endpoint correcto segun entorno.
- **QR-002** - Codificar parametros correctamente (`URL encoding`, formato y restricciones).
- **QR-003** - Permitir cumplir los requisitos de presentacion fisica del QR.
- **QR-004** - Permitir incluir la leyenda obligatoria `Factura verificable...` o `VERI*FACTU`.

#### REMISION - Remision a AEAT

- **REM-001** - Remitir por SOAP 1.1 / HTTPS / XML UTF-8.
- **REM-002** - Soportar entornos produccion/preproduccion y variante de sello de entidad.
- **REM-003** - Parsear la respuesta sincrona de AEAT (`CSV`, `TimestampPresentacion`, `TiempoEsperaEnvio`, `EstadoEnvio`, `RespuestaLinea`).
- **REM-004** - Respetar control de flujo (`TiempoEsperaEnvio`) y maximo de 1000 registros por envio.
- **REM-005** - Tratar correctamente `SOAP Fault`, errores tecnicos y reintentos.
- **REM-006** - Garantizar conformidad estructural/sintactica con los XSD oficiales.

#### CONSERV - Conservacion y trazabilidad minima aplicable

- **CON-001** - Conservar/exportar registros con integridad y trazabilidad suficiente para el SIF.
- **CON-002** - Conservar el `CSV` y la evidencia de presentacion AEAT cuando exista.

#### CERT - Certificacion / declaracion responsable

- **CERT-001** - El SIF final debe poder sostener autocertificacion/declaracion responsable por version.

#### ERROR - Manejo de errores

- **ERR-001** - Validar localmente formato, obligatoriedad y parte de reglas de negocio.
- **ERR-002** - Modelar correctamente estados de envio y registro (`Correcto`, `ParcialmenteCorrecto`, `Incorrecto`, `AceptadoConErrores`).
- **ERR-003** - Soportar operativas de subsanacion/reenvio propias de remision voluntaria.

#### OTROS

- **OTR-001** - Evitar reutilizacion de numeracion y contemplar operativa de facturas de prueba.

### 1.2 Requisitos no necesario cubrir para este alcance

| ID | Categoria | Requisito | Motivo |
|---|---|---|---|
| NNC-001 | REG | `Cabecera/RemisionRequerimiento` (`RefRequerimiento`, `FinRequerimiento`) | Solo aplica a remision bajo requerimiento. |
| NNC-002 | FIRMA | `Signature` XAdES en `RegistroAlta`/`RegistroAnulacion` | No se exige para esta revision limitada a remision voluntaria SI VERI*FACTU. |
| NNC-003 | FIRMA | Firma electronica por registro en modo **No VERI*FACTU** | Fuera de alcance por modalidad. |
| NNC-004 | EVENTOS | `RegistroEvento` completo | Fuera de alcance por modalidad. |
| NNC-005 | EVENTOS | Libro de eventos y deteccion de anomalias de modo No VERI*FACTU/Dual | Fuera de alcance por modalidad. |
| NNC-006 | REMISION | Servicio de consulta de registros presentados | Funcionalidad opcional, no necesaria para remision voluntaria basica. |

## 2. Tabla comparativa de cobertura

### 2.1 Requisitos aplicables

| ID | Categoria | Requisito | Obligatorio | Implementado | Cobertura | Modulo/Fichero | Observaciones |
|---|---|---|---|---|---|---|---|
| GEN-001 | GEN | Integridad/inalterabilidad/trazabilidad general | Si | ⚠️ | Parcial | `Record.php`, `RegistrationRecord.php`, `CancellationRecord.php` | Hay hash y encadenamiento, pero no una solucion integral de conservacion/trazabilidad operativa. |
| GEN-002 | GEN | Operacion como SI VERI*FACTU | Si | ⚠️ | Parcial | `AeatClient.php`, `ComputerSystem.php` | El nucleo online esta, pero no toda la gobernanza operativa del SIF. |
| GEN-003 | GEN | Identificacion completa del SIF | Si | ⚠️ | Parcial | `ComputerSystem.php` | Modela datos principales, pero no `IDOtro` ni todas las reglas oficiales. |
| GEN-004 | GEN | Flags multi-OT | Si | ⚠️ | Parcial | `ComputerSystem.php` | Existen los campos, pero no calculo automatico real. |
| GEN-005 | GEN | No cambio dinamico de SIF/modo | Si | ❌ | Ausente | - | No hay control de instalacion/producto o politica de modo. |
| REG-001 | REG | Cabecera + 1..1000 registros | Si | ⚠️ | Parcial | `AeatClient.php` | Genera cabecera y multiples registros, sin tope explicito de 1000. |
| REG-002 | REG | `Cabecera/ObligadoEmision` | Si | ✅ | Completa | `AeatClient.php`, `FiscalIdentifier.php` | Serializa `NombreRazon` y `NIF`. |
| REG-003 | REG | `Cabecera/Representante` | Condicional | ✅ | Completa | `AeatClient.php` | `setRepresentative()` lo cubre. |
| REG-004 | REG | `Cabecera/RemisionVoluntaria` | Condicional | ✅ | Completa | `AeatClient.php` | `setVoluntaryRemissionEndDate()` cubre fecha fin e incidencia. |
| REG-005 | REG | `RegistroAlta` basico | Si | ⚠️ | Parcial | `RegistrationRecord.php`, `InvoiceIdentifier.php` | Buen nucleo, pero no todo el diseno oficial. |
| REG-006 | REG | Rectificativas/sustitutivas | Condicional | ⚠️ | Parcial | `RegistrationRecord.php` | Soporta parte importante, no toda la casuistica oficial. |
| REG-007 | REG | Destinatarios nacionales/extranjeros | Si/Cond. | ⚠️ | Parcial | `RegistrationRecord.php`, `FiscalIdentifier.php`, `ForeignFiscalIdentifier.php` | Cobertura util, pero incompleta frente a todas las reglas AEAT. |
| REG-008 | REG | Campos de tipologia adicional (`Tercero`, `Macrodato`, etc.) | Condicional | ❌ | Ausente | - | No modelados. |
| REG-009 | REG | `Desglose` y reglas fiscales | Si | ⚠️ | Parcial | `BreakdownDetails.php` | Hay validacion aritmetica y parte de las reglas tributarias. |
| REG-010 | REG | `CuotaTotal` e `ImporteTotal` | Si | ⚠️ | Parcial | `RegistrationRecord.php` | Se validan, pero no con toda la logica de negocio AEAT. |
| REG-011 | REG | Alta con encadenamiento+sistema+timestamp+huella | Si | ⚠️ | Parcial | `Record.php`, `ComputerSystem.php` | Bien cubierto salvo reglas completas de entorno/SIF. |
| REG-012 | REG | `RegistroAnulacion` basico | Si | ⚠️ | Parcial | `CancellationRecord.php` | Nucleo presente. |
| REG-013 | REG | `GeneradoPor` / `Generador` | Condicional | ❌ | Ausente | - | No existe ese modelado. |
| REG-014 | REG | Anulacion con encadenamiento+sistema+timestamp+huella | Si | ⚠️ | Parcial | `CancellationRecord.php`, `Record.php` | Bien cubierto en nucleo tecnico. |
| REG-015 | REG | Operativas de alta/subsanacion/rechazo | Si | ⚠️ | Parcial | `RegistrationRecord.php` | Modela flags, no toda la operativa/estado AEAT. |
| REG-016 | REG | Operativas de anulacion/rechazo | Si | ⚠️ | Parcial | `CancellationRecord.php` | Igual: flags si, workflow completo no. |
| HASH-001 | HASH | SHA-256 / 64 hex mayusculas | Si | ✅ | Completa | `Record.php` | Implementado y validado. |
| HASH-002 | HASH | Payload exacto hash alta | Si | ✅ | Completa | `RegistrationRecord.php` | Implementado segun estructura AEAT. |
| HASH-003 | HASH | Payload exacto hash anulacion | Si | ✅ | Completa | `CancellationRecord.php` | Implementado segun estructura AEAT. |
| HASH-004 | HASH | Chequeos automaticos previos de encadenamiento/fechas | Si | ❌ | Ausente | - | No hay validacion previa del "ultimo RF" antes de generar el nuevo. |
| FIRMA-001 | FIRMA | Certificado para remision a AEAT | Si | ⚠️ | Parcial | `AeatClient.php` | Se puede adjuntar certificado cliente TLS, pero no hay mas gestion criptografica. |
| QR-001 | QR | URL QR correcta por entorno | Si | ✅ | Completa | `QrGenerator.php` | Endpoints `ValidarQR` correctos para prod/preprod. |
| QR-002 | QR | Encoding y parametros del QR | Si | ⚠️ | Parcial | `QrGenerator.php` | Usa `http_build_query`, pero no valida ASCII/longitud/formato normativo. |
| QR-003 | QR | Presentacion fisica del QR | Si | ❌ | Ausente | - | No genera imagen ni ayuda a maquetarla. |
| QR-004 | QR | Leyenda `VERI*FACTU` / "Factura verificable..." | Si | ❌ | Ausente | - | Fuera del alcance actual del SDK. |
| REM-001 | REMISION | SOAP 1.1 / HTTPS / XML UTF-8 | Si | ✅ | Completa | `AeatClient.php` | Envio SOAP/XML implementado. |
| REM-002 | REMISION | Entornos prod/preprod y sello | Si | ⚠️ | Parcial | `AeatClient.php` | Cambia hosts y sello, pero no cubre mas variantes operativas. |
| REM-003 | REMISION | Parseo de respuesta AEAT | Si | ⚠️ | Parcial | `AeatResponse.php`, `ResponseItem.php` | Parsea lo principal, no todo el detalle posible. |
| REM-004 | REMISION | Control de flujo y maximo 1000 | Si | ⚠️ | Parcial | `AeatResponse.php`, `AeatClient.php` | Expone `waitSeconds`, pero no lo orquesta ni limita el lote. |
| REM-005 | REMISION | Faults tecnicos y reintentos | Si | ⚠️ | Parcial | `AeatClient.php`, `AeatResponse.php` | Trata fallos y parseo, pero no implementa retry policy. |
| REM-006 | REMISION | Conformidad XSD oficial | Si | ❌ | Ausente | - | No hay validacion formal contra los XSD. |
| CON-001 | CONSERV | Conservacion/exportacion minima trazable | Si | ⚠️ | Parcial | `Record.php`, `RegistrationRecord.php`, `CancellationRecord.php` | Puede serializar XML; no conserva ni persiste por si mismo. |
| CON-002 | CONSERV | Conservacion de CSV/evidencia AEAT | Si | ⚠️ | Parcial | `AeatResponse.php` | El CSV se parsea, no se persiste. |
| CERT-001 | CERT | Autocertificacion / DR del SIF final | Si | ❌ | Ausente | `docs/certificacion.rst` | El repo declara expresamente que no aporta DR propia. |
| ERR-001 | ERROR | Validaciones locales sync/business | Si | ⚠️ | Parcial | `Model.php`, `RegistrationRecord.php`, `BreakdownDetails.php`, `ForeignFiscalIdentifier.php` | Buen set base, no la matriz completa AEAT. |
| ERR-002 | ERROR | Modelado de estados AEAT | Si | ⚠️ | Parcial | `ResponseStatus.php`, `ItemStatus.php`, `AeatResponse.php` | Estados modelados; tratamiento operativo incompleto. |
| ERR-003 | ERROR | Subsanacion/reenvio voluntario | Si | ⚠️ | Parcial | `RegistrationRecord.php`, `CancellationRecord.php` | Soporta flags, no workflow end-to-end. |
| OTR-001 | OTROS | No reutilizacion de numeracion / facturas de prueba | Si | ❌ | Ausente | - | El SDK no gestiona numeracion ni "modo pruebas". |

### 2.2 Requisitos fuera de alcance / no necesario cubrir

| ID | Categoria | Requisito | Implementado | Cobertura | Modulo/Fichero | Observaciones |
|---|---|---|---|---|---|---|
| NNC-001 | REG | Remision por requerimiento (`RefRequerimiento`) | ✅ | No necesario cubrir | `AeatClient.php` | Existe, pero no cuenta como requisito del alcance. |
| NNC-002 | FIRMA | `Signature` XAdES en RF de alta/anulacion | ❌ | No necesario cubrir | - | No se exige para esta revision de remision voluntaria. |
| NNC-003 | FIRMA | Firma electronica por registro en No VERI*FACTU | ❌ | No necesario cubrir | - | Fuera de alcance por modalidad. |
| NNC-004 | EVENTOS | `RegistroEvento` | ❌ | No necesario cubrir | - | Fuera de alcance por modalidad. |
| NNC-005 | EVENTOS | Libro de eventos / anomalias No VERI*FACTU | ❌ | No necesario cubrir | - | Fuera de alcance por modalidad. |
| NNC-006 | REMISION | Consulta de registros presentados | ❌ | No necesario cubrir | - | Funcionalidad opcional, no obligatoria para remision voluntaria basica. |

## 3. Resumen ejecutivo de brechas

### 3.1 Conteo ajustado al alcance pedido

- **Requisitos aplicables identificados**: **43**
- **Cubiertos completamente**: **8**
- **Cubiertos parcialmente**: **26**
- **No cubiertos**: **9**
- **Fuera de alcance / no necesario cubrir**: **6**

### 3.2 Lectura ejecutiva

Con el alcance reducido a **SI VERI*FACTU / remision voluntaria**, la foto mejora de forma material:

- El proyecto **si tiene un nucleo solido y util** para este alcance: alta/anulacion, hash encadenado, XML, SOAP AEAT, parser de respuesta y URL QR.
- Las brechas criticas ya **no** estan en firma XAdES o libro de eventos, porque esas obligaciones han quedado fuera de alcance por modalidad.
- El mayor riesgo se desplaza a la **completitud del modelo normativo de remision voluntaria**, a la **operativa AEAT** y a la **presentacion final del QR** si el objetivo es acercarse a un SIF utilizable directamente.

### 3.3 Riesgos de incumplimiento mas criticos en este alcance

1. **Cobertura incompleta del diseno oficial de** `RegistroAlta` **y** `RegistroAnulacion`. El repositorio implementa el nucleo tecnico, pero faltan partes relevantes del diseno oficial para remision voluntaria: tercero, macrodato, cupon, generador de anulacion y parte de las reglas cruzadas AEAT.
2. **Falta de validacion formal contra XSD y de control operativo de envio**. `AeatClient` construye XML y lo envia, pero no valida contra XSD, no impone el maximo de 1000 registros y no gestiona automaticamente el pacing mediante `TiempoEsperaEnvio`.
3. **Chequeos previos de encadenamiento/fechas no implementados**. El proyecto calcula la huella correctamente, pero no aparece una comprobacion automatica del ultimo registro antes de generar el siguiente.
4. **QR correcto como URL, pero no como artefacto normativo final**. `QrGenerator` resuelve la URL, pero no la imagen/maquetacion ni la leyenda obligatoria.
5. **El repo sigue siendo un SDK, no un SIF certificado**. Incluso restringiendo el analisis a VERI*FACTU online, la documentacion deja claro que no aporta declaracion responsable y que debe auditarse/integrarse dentro de un SIF final.

### 3.4 Deuda tecnica estimada por categoria

- **GEN**: M
- **REG**: XL
- **HASH**: M
- **FIRMA**: S
- **QR**: M
- **REMISION**: L
- **CONSERV**: L
- **CERT**: M
- **ERROR**: M
- **OTROS**: S

## 4. Plan de desarrollo

### Fase 1 - Conformidad basica obligatoria para SI VERI*FACTU

#### TASK-001 - Cerrar el alcance del producto como "SDK VERI*FACTU" vs "SIF final VERI*FACTU"

- **Requisitos que cubre**: GEN-002, CERT-001
- **Prioridad**: 🔴 Critica
- **Estimacion**: S
- **Dependencias**: -
- **Descripcion tecnica**: decidir si este repositorio seguira siendo una libreria o si va a evolucionar hacia un SIF listo para produccion.
- **Criterios de aceptacion**: documento de alcance aprobado; lista explicita de responsabilidades del SDK y del SIF integrador.

#### TASK-002 - Completar el modelo oficial de `RegistroAlta`

- **Requisitos que cubre**: REG-005, REG-006, REG-007, REG-008, REG-009, REG-010, REG-011, ERR-001
- **Prioridad**: 🔴 Critica
- **Estimacion**: XL
- **Dependencias**: TASK-001
- **Descripcion tecnica**: ampliar `RegistrationRecord`, `BreakdownDetails`, identificadores y export/import XML para incorporar campos y reglas faltantes de remision voluntaria.
- **Criterios de aceptacion**: fixtures XML representativos; validacion positiva/negativa automatizada; export/import fiel.

#### TASK-003 - Completar el modelo oficial de `RegistroAnulacion`

- **Requisitos que cubre**: REG-012, REG-013, REG-014, REG-016, ERR-001
- **Prioridad**: 🔴 Critica
- **Estimacion**: L
- **Dependencias**: TASK-001
- **Descripcion tecnica**: anadir `GeneradoPor`, `Generador`, reglas de negocio y casos completos de anulacion voluntaria.
- **Criterios de aceptacion**: soporte XML de anulacion habitual, por rechazo y sin registro previo; tests de validacion.

#### TASK-004 - Endurecer el contrato XML con XSD oficiales

- **Requisitos que cubre**: REM-006, ERR-001
- **Prioridad**: 🔴 Critica
- **Estimacion**: M
- **Dependencias**: TASK-002, TASK-003
- **Descripcion tecnica**: integrar validacion XSD previa al envio y utilidades de validacion del XML generado.
- **Criterios de aceptacion**: validacion satisfactoria frente a XSD de entrada oficiales; fallos estructurales detectados localmente antes del POST.

### Fase 2 - Robustez operativa de remision voluntaria

#### TASK-005 - Orquestar control de flujo y limites de envio

- **Requisitos que cubre**: REG-001, REM-004, REM-005
- **Prioridad**: 🟠 Alta
- **Estimacion**: M
- **Dependencias**: TASK-004
- **Descripcion tecnica**: incorporar limitacion a 1000 registros, respeto de `TiempoEsperaEnvio` y estrategia basica de reintento tecnico.
- **Criterios de aceptacion**: cliente o helper de batching; tests de pacing y lotes maximos; manejo explicito de `SOAP Fault`.

#### TASK-006 - Completar el parseo y tratamiento de respuesta AEAT

- **Requisitos que cubre**: REM-003, ERR-002, ERR-003, CON-002
- **Prioridad**: 🟠 Alta
- **Estimacion**: M
- **Dependencias**: TASK-005
- **Descripcion tecnica**: ampliar `AeatResponse`/`ResponseItem` para cubrir mas detalle util de la respuesta y facilitar workflows de subsanacion.
- **Criterios de aceptacion**: parser de respuestas parciales, aceptadas con errores y duplicados; tests dedicados.

#### TASK-007 - Implementar chequeos automaticos previos de encadenamiento

- **Requisitos que cubre**: HASH-004, GEN-001
- **Prioridad**: 🟠 Alta
- **Estimacion**: M
- **Dependencias**: TASK-001
- **Descripcion tecnica**: anadir un servicio que, dado el ultimo RF persistido, compruebe coherencia de `previousHash`, `previousInvoiceId` y secuencia temporal antes de emitir el nuevo.
- **Criterios de aceptacion**: API explicita de pre-check; tests de cadena correcta, hash roto y fecha inconsistente.

### Fase 3 - Completitud funcional del SIF VERI*FACTU

#### TASK-008 - Mejorar `ComputerSystem` y reglas de identificacion del SIF

- **Requisitos que cubre**: GEN-003, GEN-004, GEN-005
- **Prioridad**: 🟠 Alta
- **Estimacion**: M
- **Dependencias**: TASK-001
- **Descripcion tecnica**: endurecer validacion de `IdSistemaInformatico`, instalacion, flags multi-OT y restricciones operativas.
- **Criterios de aceptacion**: validaciones alineadas con la norma y tests negativos/positivos.

#### TASK-009 - Hacer utilizable el QR para facturacion real

- **Requisitos que cubre**: QR-001, QR-002, QR-003, QR-004
- **Prioridad**: 🟡 Media
- **Estimacion**: M
- **Dependencias**: TASK-001
- **Descripcion tecnica**: mantener la URL actual y anadir un modulo opcional para renderizar QR o, como minimo, una especificacion utilitaria de presentacion.
- **Criterios de aceptacion**: utilidades para componer el bloque QR; tests de encoding; ejemplos documentados.

#### TASK-010 - Anadir persistencia/conservacion minima del lado SDK o adaptadores

- **Requisitos que cubre**: CON-001, CON-002, OTR-001
- **Prioridad**: 🟡 Media
- **Estimacion**: L
- **Dependencias**: TASK-001
- **Descripcion tecnica**: incorporar adaptadores de persistencia, o interfaces/repositorios para guardar RF, hash previo, CSV y estado de presentacion.
- **Criterios de aceptacion**: contrato claro de persistencia; ejemplos de uso; posibilidad de reanudar cadena y subsanar con evidencia almacenada.

### Fase 4 - Cumplimiento demostrable y mantenible

#### TASK-011 - Crear suite de compliance tests para remision voluntaria

- **Requisitos que cubre**: transversal
- **Prioridad**: 🟢 Baja
- **Estimacion**: L
- **Dependencias**: TASK-002 a TASK-010
- **Descripcion tecnica**: convertir la matriz normativa aplicable a SI VERI*FACTU en fixtures y tests automaticos.
- **Criterios de aceptacion**: suite separada de "compliance VERI*FACTU voluntario" en CI.

#### TASK-012 - Preparar artefactos de certificacion para el SIF integrador

- **Requisitos que cubre**: CERT-001
- **Prioridad**: 🟢 Baja
- **Estimacion**: M
- **Dependencias**: TASK-001, TASK-008
- **Descripcion tecnica**: si el objetivo es usar este codigo como base de un SIF final, preparar plantillas/manifiestos/versionado para soportar la DR en el producto integrador.
- **Criterios de aceptacion**: guia de certificacion/DR y trazabilidad de versiones documentadas.

## 5. Riesgos asumidos

- Se ha tratado como **no necesario cubrir** todo lo que nace del modo No VERI*FACTU o del envio por requerimiento, aunque parte de ese soporte exista ya en el codigo.
- La declaracion responsable sigue siendo necesaria para un SIF final, pero no se considera una brecha de implementacion critica del repositorio si este sigue siendo un SDK.
- La presentacion fisica del QR puede pertenecer a una capa superior; aun asi, se mantiene visible como hueco si el objetivo final fuese aproximarse a un SIF utilizable directamente.

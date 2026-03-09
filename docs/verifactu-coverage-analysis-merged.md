# Análisis de Cobertura Normativa — VERI*FACTU
**Modalidad analizada:** Remisión Voluntaria («SOLO VERI*FACTU»)  
**Proyecto:** `josemmo/verifactu-php`  
**Fecha de análisis:** Enero 2025  
**Especificación base:** Orden HAC/1177/2024 + Real Decreto 1007/2023

---

## Alcance de esta revisión y Exenciones Legales

Este documento evalúa el repositorio **solo** para la modalidad de **remisión voluntaria de un SIF que opera como SI VERI*FACTU** (sistemas "SOLO VERI*FACTU"). 

De acuerdo con el **Artículo 3 de la Orden HAC/1177/2024** (*Particularidades en el caso de sistemas informáticos VERI\*FACTU*), al presumirse que estos sistemas cumplen los requisitos por diseño al remitir la información a la Sede Electrónica de la AEAT en tiempo real, **quedan exentos de la aplicación de los artículos 8 y 9 de dicha orden**. Por lo tanto, **no están obligados a implementar un Registro de Eventos** local ni a aplicar firma electrónica (XAdES) por registro. La evaluación se centrará estrictamente en los requisitos exigibles para esta modalidad.

---

## 1. Inventario de Requisitos (Paso 1)

### Módulos del código identificados
| Módulo/Fichero | Responsabilidad |
|---|---|
| `src/Models/Records/Record.php` | Base de registros: encadenamiento, hash, XML |
| `src/Models/Records/RegistrationRecord.php` | `RegistroAlta` |
| `src/Models/Records/CancellationRecord.php` | `RegistroAnulacion` |
| `src/Models/Records/InvoiceIdentifier.php` | `IDFactura` |
| `src/Models/Records/BreakdownDetails.php` | `DetalleDesglose` |
| `src/Models/Records/FiscalIdentifier.php` | Identificador fiscal NIF |
| `src/Models/Records/ForeignFiscalIdentifier.php` | Identificador fiscal extranjero IDOtro |
| `src/Models/Records/InvoiceType.php` | Enum TipoFactura (L2) |
| `src/Models/Records/CorrectiveType.php` | Enum TipoRectificativa (L3) |
| `src/Models/Records/TaxType.php` | Enum Impuesto (L1) |
| `src/Models/Records/RegimeType.php` | Enum ClaveRegimen (L8A/L8B) |
| `src/Models/Records/OperationType.php` | Enum CalificacionOperacion/OperacionExenta (L9/L10) |
| `src/Models/Records/ForeignIdType.php` | Enum IDType (L7) |
| `src/Models/ComputerSystem.php` | Bloque `SistemaInformatico` |
| `src/Models/Model.php` | Base con validación Symfony Validator |
| `src/Models/Queries/QueryFilter.php` | `FiltroConsulta` |
| `src/Models/Queries/QueryPaginationKey.php` | `ClavePaginacion` |
| `src/Models/Responses/AeatResponse.php` | Respuesta de remisión |
| `src/Models/Responses/QueryResponse.php` | Respuesta de consulta |
| `src/Models/Responses/ResponseItem.php` | Estado por registro en respuesta |
| `src/Models/Responses/ItemStatus.php` | Enum EstadoRegistro (L19) |
| `src/Models/Responses/ResponseStatus.php` | Enum EstadoEnvio (L18) |
| `src/Services/AeatClient.php` | Cliente SOAP: remisión y consulta AEAT |
| `src/Services/QrGenerator.php` | Generador de URL código QR |
| `src/Exceptions/AeatException.php` | Excepción de comunicación AEAT |

### GEN — Requisitos Generales

| ID | Descripción | Obligatorio | Referencia |
|---|---|---|---|
| REQ-GEN-001 | Operar en modo VERI*FACTU (remisión voluntaria inmediata) | Sí | Anexo II |
| REQ-GEN-002 | Soporte entornos pruebas y producción | Sí | Anexo I |
| REQ-GEN-003 | Inalterabilidad de registros (integridad) | Sí | Art. 13 RD 1007/2023 |

### REG — Cabecera y Registros (Alta y Anulación)

| ID | Descripción | Obligatorio | Referencia |
|---|---|---|---|
| REQ-REG-001 | Cabecera/ObligadoEmision: NombreRazon (máx 120) | Sí | DR §1 |
| REQ-REG-002 | Cabecera/ObligadoEmision: NIF (9 chars) | Sí | DR §1 |
| REQ-REG-003 | Cabecera/Representante: NombreRazon + NIF | No | DR §1 |
| REQ-REG-004 | Cabecera/RemisionVoluntaria: FechaFinVeriFactu + Incidencia | No | DR §1 |
| REQ-REG-005 | Cabecera/RemisionRequerimiento: RefRequerimiento + FinRequerimiento | Cond. | DR §1 |
| REQ-REG-010 | IDVersion (alfanum 3, L15) | Sí | DR §2 |
| REQ-REG-011 | IDFactura: IDEmisorFactura, NumSerieFactura, FechaExpedicionFactura | Sí | DR §2 |
| REQ-REG-012 | RefExterna (alfanum 60) | No | DR §2 |
| REQ-REG-013 | NombreRazonEmisor (alfanum 120) | Sí | DR §2 |
| REQ-REG-014 | Subsanacion (L4) | No | DR §2 |
| REQ-REG-015 | RechazoPrevio (L17) | No | DR §2 |
| REQ-REG-016 | TipoFactura (L2: F1-F3, R1-R5) | Sí | DR §2 |
| REQ-REG-017 | TipoRectificativa (L3: S/I) | Cond. | DR §2 |
| REQ-REG-018 | FacturasRectificadas / FacturasSustituidas (1-1000) | Cond. | DR §2 |
| REQ-REG-019 | ImporteRectificacion: BaseRectificada, CuotaRectificada, CuotaRecargoRectificado | Cond. | DR §2 |
| REQ-REG-020 | FechaOperacion (dd-mm-yyyy) | No | DR §2 |
| REQ-REG-021 | DescripcionOperacion (alfanum 500) | Sí | DR §2 |
| REQ-REG-022 | FacturaSimplificadaArt7273 (L4) | No | DR §2 |
| REQ-REG-023 | FacturaSinIdentifDestinatarioArt61d (L5) | No | DR §2 |
| REQ-REG-024 | Macrodato (L14) | No | DR §2 |
| REQ-REG-025 | EmitidaPorTerceroODestinatario (L6) | No | DR §2 |
| REQ-REG-026 | Tercero: NombreRazon + NIF o IDOtro | Cond. | DR §2 |
| REQ-REG-027 | Destinatarios/IDDestinatario (1-1000): NombreRazon + NIF o IDOtro | Cond. | DR §2 |
| REQ-REG-028 | Cupon (L4) | No | DR §2 |
| REQ-REG-029 | Desglose/DetalleDesglose (1-12): Impuesto, ClaveRegimen, CalificacionOperacion/OperacionExenta | Sí | DR §2 |
| REQ-REG-030 | DetalleDesglose: TipoImpositivo, BaseImponibleOimporteNoSujeto, BaseImponibleACoste, CuotaRepercutida, TipoRecargoEquivalencia, CuotaRecargoEquivalencia | Cond. | DR §2 |
| REQ-REG-031 | CuotaTotal, ImporteTotal (decimal 12,2) | Sí | DR §2 |
| REQ-REG-032 | Encadenamiento: PrimerRegistro o RegistroAnterior/Huella | Sí | DR §2 |
| REQ-REG-033 | SistemaInformatico (bloque completo) | Sí | DR §2, §5 |
| REQ-REG-034 | FechaHoraHusoGenRegistro (ISO 8601) | Sí | DR §2 |
| REQ-REG-035 | NumRegistroAcuerdoFacturacion (alfanum 15) | No | DR §2 |
| REQ-REG-036 | IdAcuerdoSistemaInformatico (alfanum 16) | No | DR §2 |
| REQ-REG-037 | TipoHuella (L12) + Huella (alfanum 64) | Sí | DR §2 |
| REQ-REG-040 | RegistroAnulacion/IDVersion, IDFactura anulada | Sí | DR §3 |
| REQ-REG-041 | RegistroAnulacion/RefExterna (alfanum 60) | No | DR §3 |
| REQ-REG-042 | RegistroAnulacion/SinRegistroPrevio (L4) | No | DR §3 |
| REQ-REG-043 | RegistroAnulacion/RechazoPrevio (L4) | No | DR §3 |
| REQ-REG-044 | RegistroAnulacion/GeneradoPor (L16) + bloque Generador | No | DR §3 |
| REQ-REG-045 | RegistroAnulacion/Encadenamiento | Sí | DR §3 |
| REQ-REG-046 | RegistroAnulacion: SistemaInformatico, FechaHoraHusoGenRegistro, TipoHuella, Huella | Sí | DR §3 |
| REQ-REG-050 | SistemaInformatico/NombreRazon (alfanum 120) | Sí | DR §5 |
| REQ-REG-051 | SistemaInformatico/NIF o IDOtro | Sí | DR §5 |
| REQ-REG-052 | SistemaInformatico/NombreSistemaInformatico (alfanum 30) | Sí | DR §5 |
| REQ-REG-053 | SistemaInformatico/IdSistemaInformatico (alfanum 2) | Sí | DR §5 |
| REQ-REG-054 | SistemaInformatico/Version (alfanum 50) | Sí | DR §5 |
| REQ-REG-055 | SistemaInformatico/NumeroInstalacion (alfanum 100) | Sí | DR §5 |
| REQ-REG-056 | SistemaInformatico/TipoUsoPosibleSoloVerifactu, TipoUsoPosibleMultiOT, IndicadorMultiplesOT | Sí | DR §5 |

### HASH — Encadenamiento Criptográfico

| ID | Descripción | Obligatorio | Referencia |
|---|---|---|---|
| REQ-HASH-001 | Algoritmo SHA-256 (TipoHuella='01') | Sí | Doc. Huella §2 |
| REQ-HASH-002 | Payload RegistroAlta: 8 campos exactos en orden | Sí | Doc. Huella §3.1 |
| REQ-HASH-003 | Payload RegistroAnulacion: 5 campos exactos en orden | Sí | Doc. Huella §3.2 |
| REQ-HASH-004 | Primer registro: Huella= (vacío) en payload | Sí | Doc. Huella §3 |
| REQ-HASH-005 | Salida hexadecimal mayúsculas 64 chars | Sí | Doc. Huella §5 |
| REQ-HASH-006 | Codificación UTF-8 entrada | Sí | Doc. Huella §3 |
| REQ-HASH-007 | Trim de espacios en valores de campos | Sí | Doc. Huella §3, §6 |
| REQ-HASH-008 | Validación de la huella al importar XML | Sí | Doc. Huella §7 |

### QR — Código QR

| ID | Descripción | Obligatorio | Referencia |
|---|---|---|---|
| REQ-QR-001 | URL con parámetros nif, numserie, fecha, importe (URL encoded UTF-8) | Sí | Doc. QR §4 |
| REQ-QR-002 | Endpoints producción/pruebas VERIFACTU y No-VERIFACTU | Sí | Doc. QR §5 |
| REQ-QR-003 | Generación de imagen QR física (ISO/IEC 18004:2015, Nivel M, 30-40mm) | Sí | Doc. QR §2 |
| REQ-QR-004 | Texto "QR tributario:" encima y leyenda VERIFACTU debajo en la factura | Sí | Doc. QR §3 |
| REQ-QR-005 | Solo caracteres ASCII 32-126 en parámetros URL | Sí | Doc. QR §4 |

### REMISION — Comunicación con AEAT

| ID | Descripción | Obligatorio | Referencia |
|---|---|---|---|
| REQ-REMISION-001 | SOAP/HTTPS con autenticación mTLS (PEM + PKCS#12) | Sí | Anexo I |
| REQ-REMISION-002 | Operación RegFactuSistemaFacturacion | Sí | Anexo I |
| REQ-REMISION-003 | Operación ConsultaFactuSistemaFacturacion con paginación | No | Anexo IV |
| REQ-REMISION-004 | Endpoints sello de entidad | No | WSDL |
| REQ-REMISION-005 | Límite 1-1000 registros por envío | Sí | DR §1 |
| REQ-REMISION-006 | Parseo respuesta: EstadoEnvio (L18), EstadoRegistro (L19), CodigoError (L20) | Sí | Doc. Respuesta |
| REQ-REMISION-007 | Parseo CSV + TimestampPresentacion | Sí | Doc. Respuesta |
| REQ-REMISION-008 | Respeto TiempoEsperaEnvio | Sí | Doc. Respuesta |
| REQ-REMISION-009 | Parseo EstadoRegistroDuplicado (L21) | No | Doc. Respuesta |
| REQ-REMISION-010 | Manejo SOAP Fault | Sí | Protocolo SOAP |

### CONSERV — Conservación

| ID | Descripción | Obligatorio | Referencia |
|---|---|---|---|
| REQ-CONSERV-001 | Persistencia local de la cadena de registros | Sí | Art. 7 RD 1007/2023 |
| REQ-CONSERV-002 | Gestión del último hash para encadenamiento | Sí | Doc. Huella §3 |

### ERROR — Manejo de Errores

| ID | Descripción | Obligatorio | Referencia |
|---|---|---|---|
| REQ-ERROR-001 | Validación formato y lógica antes de envío | Sí | Doc. Validaciones |
| REQ-ERROR-002 | Distinción Correcto/AceptadoConErrores/Incorrecto | Sí | L19 |
| REQ-ERROR-003 | Validación tamaño de lote (1-1000) | Sí | DR §1 |

### Requisitos NO necesario cubrir para este alcance (Exenciones Legales)

| Categoría | Requisito | Motivo de Exclusión |
|---|---|---|
| FIRMA | `Signature` XAdES en `RegistroAlta` / `RegistroAnulacion` | No se exige para la modalidad de remisión voluntaria VERI*FACTU. |
| EVENTOS | `RegistroEvento` completo | Exención explícita según el Art. 3 de la Orden HAC/1177/2024 para sistemas "SOLO VERI*FACTU". |
| EVENTOS | Libro de eventos y detección de anomalías | Exención explícita según el Art. 3 de la Orden HAC/1177/2024. |

---

## 2. Tabla Comparativa de Cobertura (Paso 3)

| ID | Cat. | Requisito | Oblig. | Implementado | Cobertura | Módulo | Observaciones |
|---|---|---|---|---|---|---|---|
| REQ-GEN-001 | GEN | Modo VERI*FACTU (remisión voluntaria) | Sí | ✅ Sí | Completa | `AeatClient.php` | `send()` implementado |
| REQ-GEN-002 | GEN | Entornos pruebas/producción | Sí | ✅ Sí | Completa | `AeatClient.php`, `QrGenerator.php` | `setProduction()` en ambos |
| REQ-GEN-003 | GEN | Inalterabilidad registros | Sí | ⚠️ Parcial | Parcial | — | La librería es stateless; integridad delegada al consumidor |
| REQ-REG-001 | REG | Cabecera/ObligadoEmision NombreRazon + NIF | Sí | ✅ Sí | Completa | `AeatClient.php` L177-178 | |
| REQ-REG-003 | REG | Cabecera/Representante | No | ✅ Sí | Completa | `AeatClient.php` L179-183 | `setRepresentative()` |
| REQ-REG-004 | REG | Cabecera/RemisionVoluntaria | No | ✅ Sí | Completa | `AeatClient.php` L184-188 | `setVoluntaryRemissionEndDate()` |
| REQ-REG-005 | REG | Cabecera/RemisionRequerimiento | Cond. | ✅ Sí | Completa | `AeatClient.php` L189-193 | `setRequirementReference()` |
| REQ-REG-010 | REG | RegistroAlta/IDVersion | Sí | ✅ Sí | Completa | `Record.php` L169 | Hardcodeado '1.0' |
| REQ-REG-011 | REG | RegistroAlta/IDFactura (3 campos) | Sí | ✅ Sí | Completa | `InvoiceIdentifier.php` | Validado formato y longitud |
| REQ-REG-012 | REG | RegistroAlta/RefExterna | No | ❌ No | Ausente | — | No existe en `RegistrationRecord` |
| REQ-REG-013 | REG | RegistroAlta/NombreRazonEmisor | Sí | ✅ Sí | Completa | `RegistrationRecord.php` L46 | Max 120 validado |
| REQ-REG-014 | REG | RegistroAlta/Subsanacion | No | ✅ Sí | Completa | `RegistrationRecord.php` L23 | `$isCorrection` |
| REQ-REG-015 | REG | RegistroAlta/RechazoPrevio (L17, valor 'X') | No | ⚠️ Parcial | Parcial | `RegistrationRecord.php` L37 | Modelado como `?bool`, debe ser Enum ('N','S','X') |
| REQ-REG-016 | REG | RegistroAlta/TipoFactura | Sí | ✅ Sí | Completa | `RegistrationRecord.php` L54 | `InvoiceType` F1-F3, R1-R5 |
| REQ-REG-017 | REG | RegistroAlta/TipoRectificativa | Cond. | ✅ Sí | Completa | `RegistrationRecord.php` L90 | `CorrectiveType` S/I |
| REQ-REG-018 | REG | FacturasRectificadas/Sustituidas | Cond. | ✅ Sí | Completa | `RegistrationRecord.php` L99, L124 | |
| REQ-REG-019 | REG | ImporteRectificacion (Base+Cuota rectificadas) | Cond. | ⚠️ Parcial | Parcial | `RegistrationRecord.php` L107-115 | Falta `CuotaRecargoRectificado` |
| REQ-REG-020 | REG | FechaOperacion | No | ✅ Sí | Completa | `RegistrationRecord.php` L63 | |
| REQ-REG-021 | REG | DescripcionOperacion | Sí | ✅ Sí | Completa | `RegistrationRecord.php` L72 | Max 500 validado |
| REQ-REG-022 | REG | FacturaSimplificadaArt7273 | No | ❌ No | Ausente | — | No implementado |
| REQ-REG-023 | REG | FacturaSinIdentifDestinatarioArt61d | No | ❌ No | Ausente | — | No implementado |
| REQ-REG-024 | REG | Macrodato | No | ❌ No | Ausente | — | No implementado |
| REQ-REG-025 | REG | EmitidaPorTerceroODestinatario | No | ❌ No | Ausente | — | Necesario en casos de tercero emisor |
| REQ-REG-026 | REG | Bloque Tercero (NombreRazon + NIF/IDOtro) | Cond. | ❌ No | Ausente | — | Bloque completo ausente |
| REQ-REG-027 | REG | Destinatarios/IDDestinatario | Cond. | ✅ Sí | Completa | `RegistrationRecord.php` L83 | FiscalIdentifier + ForeignFiscalIdentifier |
| REQ-REG-028 | REG | Cupon | No | ❌ No | Ausente | — | No implementado |
| REQ-REG-029 | REG | Desglose/DetalleDesglose (Impuesto, ClaveRegimen, Operacion) | Sí | ✅ Sí | Completa | `BreakdownDetails.php` | Enums completos L1/L8/L9/L10 |
| REQ-REG-030 | REG | DetalleDesglose importes (TipoImpositivo, BaseImponible, Cuotas, Recargo) | Cond. | ⚠️ Parcial | Parcial | `BreakdownDetails.php` | Falta `BaseImponibleACoste` (régimen C06) |
| REQ-REG-031 | REG | CuotaTotal + ImporteTotal | Sí | ⚠️ Parcial | Parcial | `RegistrationRecord.php` L144-153 | Validación asume reglas para todo régimen, ignorando exclusión de ImporteTotal de ClaveRegimen 03, 05, 06, 08 y 09 |
| REQ-REG-032 | REG | Encadenamiento PrimerRegistro / RegistroAnterior | Sí | ✅ Sí | Completa | `Record.php` L173-180 | |
| REQ-REG-033 | REG | SistemaInformatico (bloque completo) | Sí | ✅ Sí | Completa | `ComputerSystem.php` | |
| REQ-REG-034 | REG | FechaHoraHusoGenRegistro (ISO 8601) | Sí | ✅ Sí | Completa | `Record.php` L184 | Formato `c` PHP |
| REQ-REG-035 | REG | NumRegistroAcuerdoFacturacion | No | ❌ No | Ausente | — | No implementado |
| REQ-REG-036 | REG | IdAcuerdoSistemaInformatico | No | ❌ No | Ausente | — | No implementado |
| REQ-REG-037 | REG | TipoHuella + Huella | Sí | ✅ Sí | Completa | `Record.php` L185-186 | TipoHuella='01' hardcodeado |
| REQ-REG-040 | REG | RegistroAnulacion/IDVersion + IDFactura anulada | Sí | ✅ Sí | Completa | `Record.php`, `InvoiceIdentifier.php` | Sufijo 'Anulada' en campos |
| REQ-REG-041 | REG | RegistroAnulacion/RefExterna | No | ❌ No | Ausente | — | No implementado |
| REQ-REG-042 | REG | RegistroAnulacion/SinRegistroPrevio | No | ✅ Sí | Completa | `CancellationRecord.php` L22 | |
| REQ-REG-043 | REG | RegistroAnulacion/RechazoPrevio | No | ✅ Sí | Completa | `CancellationRecord.php` L35 | |
| REQ-REG-044 | REG | RegistroAnulacion/GeneradoPor + Generador | No | ❌ No | Ausente | — | No implementado |
| REQ-REG-045 | REG | RegistroAnulacion/Encadenamiento | Sí | ⚠️ Parcial | Parcial | `CancellationRecord.php` L57-68 | **Bug:** validador fuerza siempre previousInvoiceId aunque el registro sea PrimerRegistro |
| REQ-REG-046 | REG | RegistroAnulacion: SistemaInformatico, FechaHoraHuso, TipoHuella, Huella | Sí | ✅ Sí | Completa | `Record.php`, `ComputerSystem.php` | |
| REQ-REG-050 | REG | SistemaInformatico/NombreRazon | Sí | ✅ Sí | Completa | `ComputerSystem.php` L21 | |
| REQ-REG-051 | REG | SistemaInformatico/NIF o IDOtro (selección) | Sí | ⚠️ Parcial | Parcial | `ComputerSystem.php` L30 | Solo NIF; sin soporte IDOtro para vendores extranjeros |
| REQ-REG-052 | REG | SistemaInformatico/NombreSistemaInformatico | Sí | ✅ Sí | Completa | `ComputerSystem.php` L39 | |
| REQ-REG-053 | REG | SistemaInformatico/IdSistemaInformatico | Sí | ⚠️ Parcial | Parcial | `ComputerSystem.php` L48 | Falta validación regex estricta (Letras mayúsculas excl. Ñ y dígitos) |
| REQ-REG-054 | REG | SistemaInformatico/Version | Sí | ✅ Sí | Completa | `ComputerSystem.php` L57 | |
| REQ-REG-055 | REG | SistemaInformatico/NumeroInstalacion | Sí | ✅ Sí | Completa | `ComputerSystem.php` L66 | |
| REQ-REG-056 | REG | SistemaInformatico/TipoUsoPosibleSoloVerifactu + MultiOT + IndicadorMultiplesOT | Sí | ✅ Sí | Completa | `ComputerSystem.php` L75-93 | |
| REQ-HASH-001 | HASH | SHA-256 | Sí | ✅ Sí | Completa | `RegistrationRecord.php` L175 | |
| REQ-HASH-002 | HASH | Payload RegistroAlta: 8 campos en orden | Sí | ✅ Sí | Completa | `RegistrationRecord.php` L166-175 | Conforme ejemplos AEAT |
| REQ-HASH-003 | HASH | Payload RegistroAnulacion: 5 campos en orden | Sí | ✅ Sí | Completa | `CancellationRecord.php` L49-54 | |
| REQ-HASH-004 | HASH | Huella= (vacío) para primer registro | Sí | ✅ Sí | Completa | `RegistrationRecord.php` L173 | `?? ''` |
| REQ-HASH-005 | HASH | Salida hex mayúsculas 64 chars | Sí | ✅ Sí | Completa | `RegistrationRecord.php` L175 | `strtoupper()` |
| REQ-HASH-006 | HASH | UTF-8 input encoding | Sí | ✅ Sí | Completa | — | PHP `hash()` usa UTF-8 |
| REQ-HASH-007 | HASH | Trim de espacios en valores de campos del payload | Sí | ❓ No det. | Req. verificación | `RegistrationRecord.php` L167+ | No hay `trim()` explícito; asumido limpio por el consumidor |
| REQ-HASH-008 | HASH | Validación de huella al importar XML | Sí | ✅ Sí | Completa | `Record.php` L138-145 | Callback `validateHash()` |
| REQ-QR-001 | QR | URL con parámetros nif/numserie/fecha/importe URL-encoded | Sí | ✅ Sí | Completa | `QrGenerator.php` L74-84 | `http_build_query()` |
| REQ-QR-002 | QR | Endpoints pruebas/producción VERIFACTU y No-VERIFACTU | Sí | ✅ Sí | Completa | `QrGenerator.php` L75-77 | |
| REQ-QR-003 | QR | Imagen QR física (ISO 18004, Nivel M, 30-40mm) | Sí | ❌ No | Ausente | — | Solo URL; imagen QR a cargo del consumidor |
| REQ-QR-004 | QR | "QR tributario:" y leyenda VERIFACTU en factura | Sí | ❌ No | Ausente | — | Presentación fuera del scope de la librería |
| REQ-QR-005 | QR | Solo ASCII 32-126 en parámetros URL | Sí | ❓ No det. | Req. verificación | `QrGenerator.php` | Sin validación explícita de charset |
| REQ-REMISION-001 | REMISION | SOAP/HTTPS + mTLS PEM/PFX | Sí | ✅ Sí | Completa | `AeatClient.php` L76-83, L210 | |
| REQ-REMISION-002 | REMISION | RegFactuSistemaFacturacion | Sí | ✅ Sí | Completa | `AeatClient.php` L164 | |
| REQ-REMISION-003 | REMISION | ConsultaFactuSistemaFacturacion + paginación | No | ✅ Sí | Completa | `AeatClient.php` L242 | |
| REQ-REMISION-004 | REMISION | Sello de entidad | No | ✅ Sí | Completa | `AeatClient.php` L149 | `setEntitySeal()` |
| REQ-REMISION-005 | REMISION | Límite 1-1000 registros por envío | Sí | ⚠️ Parcial | Parcial | `AeatClient.php` L164 | Sin validación del tamaño del array |
| REQ-REMISION-006 | REMISION | Parseo EstadoEnvio (L18), EstadoRegistro (L19), CodigoError (L20) | Sí | ✅ Sí | Completa | `AeatResponse.php` | Enums + campos completos |
| REQ-REMISION-007 | REMISION | Parseo CSV + TimestampPresentacion | Sí | ✅ Sí | Completa | `AeatResponse.php` L51-64 | |
| REQ-REMISION-008 | REMISION | Respeto TiempoEsperaEnvio | Sí | ⚠️ Parcial | Parcial | `AeatResponse.php` L168 | Se parsea pero no se aplica automáticamente |
| REQ-REMISION-009 | REMISION | EstadoRegistroDuplicado (L21) | No | ❌ No | Ausente | — | No en `ResponseItem` |
| REQ-REMISION-010 | REMISION | SOAP Fault → AeatException | Sí | ✅ Sí | Completa | `AeatResponse.php` L39 | |
| REQ-CONSERV-001 | CONSERV | Persistencia local de cadena de registros | Sí | ❌ No | Ausente | — | Librería stateless por diseño |
| REQ-CONSERV-002 | CONSERV | Gestión del último hash | Sí | ❌ No | Ausente | — | Responsabilidad del consumidor |
| REQ-ERROR-001 | ERROR | Validación formato + lógica (Symfony Validator) | Sí | ✅ Sí | Completa | `Model.php`, `RegistrationRecord.php`, `BreakdownDetails.php` | Callbacks exhaustivos |
| REQ-ERROR-002 | ERROR | Distinción Correcto/AceptadoConErrores/Incorrecto | Sí | ✅ Sí | Completa | `ItemStatus.php`, `ResponseStatus.php` | |
| REQ-ERROR-003 | ERROR | Validación tamaño lote (1-1000) | Sí | ❌ No | Ausente | `AeatClient.php` L164 | Sin validación de `count($records)` |

---

## 3. Resumen Ejecutivo de Brechas (Paso 4)

### Totales por categoría (Excluyendo exenciones legales)

| Categoría | Total | ✅ Completa | ⚠️ Parcial | ❌ Ausente | ❓ Verificar |
|---|---|---|---|---|---|
| GEN | 3 | 2 | 1 | 0 | 0 |
| REG (todos los bloques) | 48 | 28 | 8 | 12 | 0 |
| HASH | 8 | 6 | 0 | 0 | 2 |
| QR | 5 | 2 | 0 | 2 | 1 |
| REMISION | 10 | 7 | 2 | 1 | 0 |
| CONSERV | 2 | 0 | 0 | 2 | 0 |
| ERROR | 3 | 2 | 0 | 1 | 0 |
| **TOTAL** | **79** | **47 (59,5%)** | **11 (13,9%)** | **18 (22,8%)** | **3 (3,8%)** |

**Cobertura efectiva (completa + parcial): 73,4%**

### Los 4 Riesgos Críticos

**Riesgo 1 — Sin persistencia ni gestión de cadena de hashes** 🔴  
La librería es completamente stateless. El consumidor debe gestionar manualmente el último hash (`previousHash`) y `previousInvoiceId`. Un error de encadenamiento en producción propaga hashes incorrectos irreversiblemente. No hay interfaz ni abstracción para facilitar esto.

**Riesgo 2 — Bug: CancellationRecord obliga a previousInvoiceId** 🔴  
El validador en `CancellationRecord::validateEnforcePreviousInvoice()` rechaza siempre registros sin `previousInvoiceId`, pero la especificación permite que el primer `RegistroAnulacion` sea el `PrimerRegistro` del SIF.

**Riesgo 3 — Bloque Tercero ausente** 🟠  
Los campos `EmitidaPorTerceroODestinatario` y el bloque `Tercero` no están implementados. Frecuente en ERPs que facturan en nombre de terceros.

**Riesgo 4 — TiempoEsperaEnvio no aplicado + sin validación de lote** 🟠  
`waitSeconds` se parsea pero no se aplica. `send()` acepta cualquier número de registros sin validar el límite de 1000. Ambos pueden causar rechazos masivos en producción.

### Deuda Técnica por Categoría

| Categoría | Esfuerzo | Naturaleza |
|---|---|---|
| CONSERV | L | Interfaz de persistencia + implementación de referencia |
| REG (Tercero, indicadores) | M | EmitidaPorTerceroODestinatario, Tercero, FacturaSimplificada, Macrodato, Cupon |
| REG (SistemaInformatico IDOtro) | S | Soporte IDOtro para vendores extranjeros |
| REMISION (guardianes) | S | Validación tamaño lote, enforcement TiempoEsperaEnvio |
| Bug PrimerRegistro y Enum RechazoPrevio | S | Corregir validador y migrar `RechazoPrevio` a Enum ('N', 'S', 'X') |
| Bug ImporteTotal | S | Excluir regímenes especiales 03, 05, 06, 08 y 09 del cálculo estricto de ImporteTotal. |
| HASH (Payload Numérico / Trim) | XS | `trim()` sin tocar empotrados, homogeneizar string numérico |
| REG (Regex IdSistemaInformatico) | XS | Validación estricta mayúsculas/dígitos |
| ERROR (L21) | XS | Parseo EstadoRegistroDuplicado |

---

## 4. Plan de Desarrollo (Paso 5)

### Fase 1 — Conformidad Básica Obligatoria

#### TASK-001 — Validar tamaño de lote en `AeatClient::send()`
- **Requisitos:** REQ-REMISION-005, REQ-ERROR-003
- **Prioridad:** 🔴 Crítica | **Esfuerzo:** XS | **Dependencias:** Ninguna
- **Descripción:** Al inicio de `send()`, verificar `count($records) >= 1 && count($records) <= 1000`; lanzar `InvalidArgumentException` si no se cumple.
- **Aceptación:** `send([])` y `send(1001 records)` lanzan excepción; límites válidos pasan.

#### TASK-002 — Corregir bug PrimerRegistro y Refactorizar RechazoPrevio
- **Requisitos:** REQ-REG-045, REQ-REG-015
- **Prioridad:** 🔴 Crítica | **Esfuerzo:** S | **Dependencias:** Ninguna
- **Descripción:** 
  1. Eliminar o condicionar el validador `validateEnforcePreviousInvoice()` en `CancellationRecord`. Un `CancellationRecord` con `previousInvoiceId = null` y `previousHash = null` debe ser válido (representa `PrimerRegistro`).
  2. Migrar la propiedad `$previousRejection` (RechazoPrevio) a un Enum estricto de 3 estados (`'N'`, `'S'`, `'X'`) para soportar correctamente el "Alta de Subsanación sin Registro Previo" exigido por la AEAT. Abandonar el modelado como `?bool`.
- **Aceptación:** `CancellationRecord` sin encadenamiento pasa validación y exporta `<PrimerRegistro>S</PrimerRegistro>`. La etiqueta de rechazo previo procesa los tres valores de la lista L17 adecuadamente.

#### TASK-003 — Estricta normalización de payload númerico y trim() en calculateHash()
- **Requisitos:** REQ-HASH-007
- **Prioridad:** 🔴 Crítica | **Esfuerzo:** XS | **Dependencias:** Ninguna
- **Descripción:** En `RegistrationRecord::calculateHash()` y `CancellationRecord::calculateHash()`:
  1. Aplicar `trim()` a los valores para eliminar espacios al inicio y al final, pero **preservando intactos los espacios empotrados** o internos de las cadenas (ej. series espaciadas).
  2. Estandarizar el casting de los importes y cuotas para que la representación en el payload del hash **coincida exactamente** (como string exacto) con el valor impreso en el XML, evitando auto-casteos numéricos impredecibles de PHP que causen Error 2000.
- **Aceptación:** Tramos con espacios internos se hashean tal cual. Valores numéricos mantienen su representación estricta igual al XML generado.

#### TASK-004 — Documentar y facilitar TiempoEsperaEnvio
- **Requisitos:** REQ-REMISION-008
- **Prioridad:** 🔴 Crítica | **Esfuerzo:** S | **Dependencias:** Ninguna
- **Descripción:** Añadir PHPDoc claro en `AeatResponse::$waitSeconds` indicando que debe respetarse. Añadir método `AeatClient::waitIfNeeded(AeatResponse $response): void` que llame a `sleep()` si `waitSeconds > 0`.
- **Aceptación:** El método `waitIfNeeded()` existe y duerme el tiempo indicado. La documentación refleja el uso obligatorio.

#### TASK-005 — Añadir RefExterna en RegistrationRecord y CancellationRecord
- **Requisitos:** REQ-REG-012, REQ-REG-041
- **Prioridad:** 🟠 Alta | **Esfuerzo:** S | **Dependencias:** Ninguna
- **Descripción:** Añadir `?string $externalRef = null` (max 60) con anotación `@field RefExterna`, validación `Assert\Length(max: 60)`, exportación en XML (posición correcta según XSD) e importación en ambas clases de registro.
- **Aceptación:** Campo informado se exporta como `<sum1:RefExterna>`; nulo no genera elemento; importación carga valor correctamente.

---

### Fase 2 — Conformidad Completa

#### TASK-006 — Implementar indicadores booleanos opcionales en RegistrationRecord
- **Requisitos:** REQ-REG-022, REQ-REG-023, REQ-REG-024, REQ-REG-028
- **Prioridad:** 🟡 Media | **Esfuerzo:** S | **Dependencias:** Ninguna
- **Descripción:** Añadir a `RegistrationRecord`:
  - `bool $isSimplifiedArt7273 = false` → `FacturaSimplificadaArt7273`
  - `bool $withoutRecipientIdArt61d = false` → `FacturaSinIdentifDestinatarioArt61d`
  - `bool $isMacrodato = false` → `Macrodato` (Añadir validación estricta que exija este valor en true si `ImporteTotal >= 100000000`)
  - `bool $hasCoupon = false` → `Cupon`
  
  Exportar solo si `true` (valor 'S'). No exportar si `false`. Importar 'S'→true, 'N'→false.
- **Aceptación:** Cada campo `true` genera el elemento XML con valor 'S'. Los `false` no generan elemento. Se valida la condición obligatoria del Macrodato.

#### TASK-007 — Implementar EmitidaPorTerceroODestinatario y bloque Tercero
- **Requisitos:** REQ-REG-025, REQ-REG-026
- **Prioridad:** 🟠 Alta | **Esfuerzo:** M | **Dependencias:** Ninguna
- **Descripción:** Añadir a `RegistrationRecord`:
  - `?string $issuedByThirdParty = null` (valores 'D' o 'T' de L6)
  - `FiscalIdentifier|ForeignFiscalIdentifier|null $thirdParty = null`
  
  Validación: si `$issuedByThirdParty` está informado, `$thirdParty` es obligatorio. Exportar bloque `Tercero` con NIF o IDOtro según tipo. Importar del XML.
- **Aceptación:** Factura con tercero emisor exporta correctamente ambos campos. Validación falla si falta el bloque Generador cuando está informado el indicador.

#### TASK-008 — Implementar GeneradoPor y bloque Generador en CancellationRecord
- **Requisitos:** REQ-REG-044
- **Prioridad:** 🟡 Media | **Esfuerzo:** S | **Dependencias:** Ninguna
- **Descripción:** Añadir a `CancellationRecord`:
  - `?string $generatedBy = null` (L16)
  - `FiscalIdentifier|ForeignFiscalIdentifier|null $generator = null`
  
  Validación: si `$generatedBy !== null`, `$generator` es obligatorio. Exportar e importar.
- **Aceptación:** Registro con generador informado exporta correctamente. Validación cohesiva entre los dos campos.

#### TASK-009 — Añadir soporte IDOtro en SistemaInformatico
- **Requisitos:** REQ-REG-051
- **Prioridad:** 🟡 Media | **Esfuerzo:** S | **Dependencias:** Ninguna
- **Descripción:** En `ComputerSystem`, hacer `vendorNif` opcional. Añadir `?string $vendorCountry`, `?ForeignIdType $vendorIdType`, `?string $vendorId`. Validación callback: NIF XOR IDOtro (uno obligatorio, no ambos). Actualizar `export()` y `fromXml()`.
- **Aceptación:** Vendor con NIF español: exporta `<sum1:NIF>`. Vendor extranjero: exporta `<sum1:IDOtro>`. Ninguno o ambos: validación falla.

#### TASK-010 — Añadir BaseImponibleACoste en BreakdownDetails
- **Requisitos:** REQ-REG-030 (parcial)
- **Prioridad:** 🟢 Baja | **Esfuerzo:** XS | **Dependencias:** Ninguna
- **Descripción:** Añadir `?string $baseCostAmount = null` (decimal 12,2) en `BreakdownDetails`. Validación callback: obligatorio si `regimeType === C06`. Exportar como `<sum1:BaseImponibleACoste>` tras `BaseImponibleOimporteNoSujeto`.
- **Aceptación:** Desglose C06 sin `baseCostAmount` falla validación. Valor presente se exporta correctamente.

#### TASK-011 — Añadir CuotaRecargoRectificado en ImporteRectificacion
- **Requisitos:** REQ-REG-019 (parcial)
- **Prioridad:** 🟢 Baja | **Esfuerzo:** XS | **Dependencias:** Ninguna
- **Descripción:** Añadir `?string $correctedSurchargeAmount = null` en `RegistrationRecord`. Exportar como `<sum1:CuotaRecargoRectificado>` dentro del bloque `ImporteRectificacion`.
- **Aceptación:** Campo presente se exporta. Campo nulo no genera elemento.

#### TASK-012 — Parsear EstadoRegistroDuplicado (L21) en respuesta
- **Requisitos:** REQ-REMISION-009
- **Prioridad:** 🟡 Media | **Esfuerzo:** XS | **Dependencias:** Ninguna
- **Descripción:** Crear enum `DuplicateRecordStatus` (Correcta, AceptadaConErrores, Anulada). Añadir `?DuplicateRecordStatus $duplicateStatus` a `ResponseItem`. Parsear `<tikR:EstadoRegistroDuplicado>` en `AeatResponse::from()`.
- **Aceptación:** Respuesta con duplicado se parsea. `$item->duplicateStatus` es null cuando no hay duplicado.

#### TASK-013 — Corregir validación local de ImporteTotal según ClaveRegimen
- **Requisitos:** REQ-REG-031
- **Prioridad:** 🟠 Alta | **Esfuerzo:** S | **Dependencias:** Ninguna
- **Descripción:** Modificar la validación aritmética de `RegistrationRecord::validateTotals()` para que NO exija la cuadratura exacta del campo `ImporteTotal` cuando la factura contenga desgloses con `ClaveRegimen` igual a `03`, `05`, `06`, `08` o `09`. La normativa oficial excluye expresamente estos regímenes de la validación matemática mencionada.
- **Aceptación:** Una factura bajo régimen `03`, `05`, `06`, `08` o `09` (ej. REBU o Agencias de Viaje) no provoca fallo de validación local si su `ImporteTotal` difiere del sumatorio estricto.

---

### Fase 3 — Mejoras y Robustez

#### TASK-015 — Interfaz de persistencia para gestión del chain
- **Requisitos:** REQ-CONSERV-001, REQ-CONSERV-002
- **Prioridad:** 🟠 Alta | **Esfuerzo:** L | **Dependencias:** Ninguna
- **Descripción:** Definir interfaz `ChainRepositoryInterface` con métodos:
  - `getLastRecord(string $issuerId): ?array` → devuelve `['invoiceId' => InvoiceIdentifier, 'hash' => string]`
  - `saveRecord(Record $record): void`
  
  Proporcionar implementación de referencia en memoria (`InMemoryChainRepository`). La implementación en base de datos queda a cargo del consumidor. Documentar el patrón de uso en el README.
- **Aceptación:** El consumidor puede implementar la interfaz y conectarla a `AeatClient`. El repositorio en memoria pasa tests de integración básicos.

#### TASK-016 — Añadir campos opcionales menores (acuerdos de facturación)
- **Requisitos:** REQ-REG-035, REQ-REG-036
- **Prioridad:** 🟢 Baja | **Esfuerzo:** XS | **Dependencias:** Ninguna
- **Descripción:** Añadir a `RegistrationRecord`:
  - `?string $billingAgreementNumber = null` (alfanum 15) → `NumRegistroAcuerdoFacturacion`
  - `?string $systemAgreementId = null` (alfanum 16) → `IdAcuerdoSistemaInformatico`
- **Aceptación:** Campos informados se exportan en el XML. Nulos no generan elemento.

#### TASK-017 — Validación charset ASCII en QrGenerator
- **Requisitos:** REQ-QR-005
- **Prioridad:** 🟠 Alta | **Esfuerzo:** XS | **Dependencias:** Ninguna
- **Descripción:** En `QrGenerator::from()`, validar que `nif`, `invoiceNumber` e `importe` contengan solo caracteres ASCII 32-126. Si no, lanzar `InvalidArgumentException`.
- **Aceptación:** Parámetro con caracteres fuera del rango lanza excepción. Parámetros ASCII válidos funcionan correctamente.

#### TASK-018 — Validación estricta (Regex) en IdSistemaInformatico
- **Requisitos:** REQ-REG-053
- **Prioridad:** 🟠 Alta | **Esfuerzo:** XS | **Dependencias:** Ninguna
- **Descripción:** Aplicar en `ComputerSystem` una validación con expresión regular para el campo `idSistemaInformatico` de tamaño 2, exigiendo que sus caracteres sean **exclusivamente letras mayúsculas (excluyendo la Ñ) o dígitos**.
- **Aceptación:** Fallo de validación local si se introduce minúsculas, espacios, o caracteres especiales.

---

### Riesgos Asumidos

1. **Exención de Firma XAdES y Eventos:** Este análisis asume estrictamente el uso del sistema en modalidad "SOLO VERI*FACTU". Si el sistema llegara a operar en modalidad DUAL o "No VERI*FACTU", perdería las exenciones del Artículo 3 de la Orden HAC/1177/2024, pasando a ser obligatoria la implementación completa del Registro de Eventos y la firma electrónica XAdES por registro.
2. **Persistencia stateless como decisión de diseño:** La librería delega la responsabilidad de almacenamiento al consumidor. Esta es una decisión de arquitectura válida para una librería PHP de propósito general, pero introduce riesgo operativo si el consumidor no implementa correctamente el encadenamiento. Se recomienda proveer al menos una interfaz y una implementación de referencia (TASK-015).
3. **Generación de imagen QR fuera del scope:** La especificación obliga a incluir un QR físico en la factura impresa. La librería genera correctamente la URL, pero la generación de la imagen QR (con las dimensiones y corrección de error indicadas) es responsabilidad del consumidor o de una librería adicional (p.ej. `endroid/qr-code`). Esto debe documentarse claramente.
4. **Zona gris en `IndicadorMultiplesOT`:** El Reglamento indica que este campo "deberá obtenerlo automáticamente el sistema informático" y "no pudiendo obtenerse a partir de otra información ni ser introducido directamente por el usuario". La librería permite setearlo manualmente, lo cual podría ser cuestionado. El consumidor debe obtenerlo de forma automática desde su base de datos de clientes.
5. **Interpretación de `TipoUsoPosibleSoloVerifactu`:** Si el SIF opera solo en modo VERIFACTU (`onlySupportsVerifactu = true`), la librería no impide su uso en modo No-VERIFACTU. El cumplimiento de esta restricción operativa es responsabilidad del consumidor.
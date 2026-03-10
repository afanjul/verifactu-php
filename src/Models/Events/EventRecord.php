<?php
namespace josemmo\Verifactu\Models\Events;

use DateTimeImmutable;
use DateTimeInterface;
use josemmo\Verifactu\Exceptions\ImportException;
use josemmo\Verifactu\Models\ComputerSystem;
use josemmo\Verifactu\Models\Model;
use josemmo\Verifactu\Models\Records\FiscalIdentifier;
use josemmo\Verifactu\Models\Records\ForeignFiscalIdentifier;
use josemmo\Verifactu\Models\Records\ForeignIdType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use UXML\UXML;

/**
 * System event record (RegistroEvento)
 *
 * Represents a VERI*FACTU system event record as defined in DR §4 and Annex §5.
 * Events must be chained sequentially per taxpayer to guarantee auditability.
 *
 * Note: XAdES signature (REQ-EVENTOS-005) is not implemented because this library
 * targets the SOLO VERI*FACTU modality, for which signature is not mandatory.
 */
class EventRecord extends Model {
    /** XML namespace for event records */
    public const NS = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/EventosSIF.xsd';

    /**
     * Datos del sistema informático de facturación (SIF)
     *
     * @field Evento/SistemaInformatico
     */
    #[Assert\NotNull]
    #[Assert\Valid]
    public ComputerSystem $system;

    /**
     * Datos del obligado a expedir las facturas
     *
     * @field Evento/ObligadoEmision
     */
    #[Assert\NotNull]
    #[Assert\Valid]
    public FiscalIdentifier $issuer;

    /**
     * Indica si las facturas son expedidas materialmente por un tercero o destinatario
     *
     * @field Evento/EmitidaPorTerceroODestinatario
     */
    public bool $isThirdPartyIssued = false;

    /**
     * Datos del tercero o destinatario que expida las facturas materialmente (opcional)
     *
     * @field Evento/TerceroODestinatario
     */
    #[Assert\Valid]
    public FiscalIdentifier|ForeignFiscalIdentifier|null $thirdPartyIssuer = null;

    /**
     * Fecha, hora y huso horario de generación del registro de evento
     *
     * @field Evento/FechaHoraHusoGenEvento
     */
    #[Assert\NotNull]
    public DateTimeImmutable $generatedAt;

    /**
     * Tipo de evento a registrar (tabla L2E)
     *
     * @field Evento/TipoEvento
     */
    #[Assert\NotNull]
    public EventType $eventType;

    /**
     * Tipo del evento anterior en la cadena
     *
     * @field Evento/Encadenamiento/EventoAnterior/TipoEvento
     */
    public ?EventType $previousEventType = null;

    /**
     * Fecha, hora y huso horario del registro de evento anterior
     *
     * @field Evento/Encadenamiento/EventoAnterior/FechaHoraHusoGenEvento
     */
    public ?DateTimeImmutable $previousGeneratedAt = null;

    /**
     * Primeros 64 caracteres de la huella del registro de evento anterior
     *
     * @field Evento/Encadenamiento/EventoAnterior/HuellaEvento
     */
    #[Assert\Regex(pattern: '/^[0-9A-F]{64}$/')]
    public ?string $previousHash = null;

    /**
     * Huella (hash SHA-256) de este registro de evento
     *
     * @field Evento/HuellaEvento
     */
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[0-9A-F]{64}$/')]
    public string $hash;

    /**
     * Datos adicionales sobre el evento (opcional, max 100 chars)
     *
     * @field Evento/OtrosDatosEvento
     */
    #[Assert\Length(max: 100)]
    public ?string $additionalData = null;

    // -------------------------------------------------------------------------
    // DatosPropiosEvento – optional sub-fields (EventType 03 / 04)
    // -------------------------------------------------------------------------

    /**
     * Indica si se realizó proceso sobre integridad de huellas de registros de facturación
     *
     * @field Evento/R/LanzamientoProcesoDeteccionAnomaliasRegFacturacion/RealizadoProcesoSobreIntegridadHuellasRegFacturacion
     */
    public ?bool $invoiceHashIntegrityChecked = null;

    /**
     * Número de registros de facturación procesados sobre integridad de huellas
     *
     * @field ...NumeroDeRegistrosFacturacionProcesadosSobreIntegridadHuellas
     */
    #[Assert\PositiveOrZero]
    public ?int $invoiceHashIntegrityCount = null;

    /**
     * Indica si se realizó proceso sobre integridad de firmas de registros de facturación
     *
     * @field Evento/R/LanzamientoProcesoDeteccionAnomaliasRegFacturacion/RealizadoProcesoSobreIntegridadFirmasRegFacturacion
     */
    public ?bool $invoiceSignatureIntegrityChecked = null;

    /**
     * @field ...NumeroDeRegistrosFacturacionProcesadosSobreIntegridadFirmas
     */
    #[Assert\PositiveOrZero]
    public ?int $invoiceSignatureIntegrityCount = null;

    /**
     * Indica si se realizó proceso sobre trazabilidad del encadenamiento de registros de facturación
     *
     * @field Evento/R/LanzamientoProcesoDeteccionAnomaliasRegFacturacion/RealizadoProcesoSobreTrazabilidadCadenaRegFacturacion
     */
    public ?bool $invoiceChainTraceabilityChecked = null;

    /**
     * @field ...NumeroDeRegistrosFacturacionProcesadosSobreTrazabilidadCadena
     */
    #[Assert\PositiveOrZero]
    public ?int $invoiceChainTraceabilityCount = null;

    /**
     * Indica si se realizó proceso sobre trazabilidad de fechas de registros de facturación
     *
     * @field Evento/R/LanzamientoProcesoDeteccionAnomaliasRegFacturacion/RealizadoProcesoSobreTrazabilidadFechasRegFacturacion
     */
    public ?bool $invoiceDateTraceabilityChecked = null;

    /**
     * @field ...NumeroDeRegistrosFacturacionProcesadosSobreTrazabilidadFechas
     */
    #[Assert\PositiveOrZero]
    public ?int $invoiceDateTraceabilityCount = null;

    /**
     * Tipo de anomalía detectada en registros de facturación (EventType 04)
     *
     * @field Evento/R/DeteccionAnomaliasRegFacturacion/TipoAnomalia
     */
    public ?AnomalyType $invoiceAnomalyType = null;

    /**
     * Descripción adicional de la anomalía detectada en registros de facturación
     *
     * @field Evento/R/DeteccionAnomaliasRegFacturacion/OtrosDatosAnomalia
     */
    #[Assert\Length(max: 100)]
    public ?string $invoiceAnomalyDetails = null;

    // -------------------------------------------------------------------------
    // DatosPropiosEvento – optional sub-fields (EventType 05 / 06)
    // -------------------------------------------------------------------------

    /**
     * Indica si se realizó proceso sobre integridad de huellas de registros de evento
     *
     * @field Evento/R/LanzamientoProcesoDeteccionAnomaliasRegEvento/RealizadoProcesoSobreIntegridadHuellasRegEvento
     */
    public ?bool $eventHashIntegrityChecked = null;

    /**
     * @field ...NumeroDeRegistrosEventoProcesadosSobreIntegridadHuellas
     */
    #[Assert\PositiveOrZero]
    public ?int $eventHashIntegrityCount = null;

    /**
     * Indica si se realizó proceso sobre integridad de firmas de registros de evento
     *
     * @field Evento/R/LanzamientoProcesoDeteccionAnomaliasRegEvento/RealizadoProcesoSobreIntegridadFirmasRegEvento
     */
    public ?bool $eventSignatureIntegrityChecked = null;

    /**
     * @field ...NumeroDeRegistrosEventoProcesadosSobreIntegridadFirmas
     */
    #[Assert\PositiveOrZero]
    public ?int $eventSignatureIntegrityCount = null;

    /**
     * Indica si se realizó proceso sobre trazabilidad del encadenamiento de registros de evento
     *
     * @field Evento/R/LanzamientoProcesoDeteccionAnomaliasRegEvento/RealizadoProcesoSobreTrazabilidadCadenaRegEvento
     */
    public ?bool $eventChainTraceabilityChecked = null;

    /**
     * @field ...NumeroDeRegistrosEventoProcesadosSobreTrazabilidadCadena
     */
    #[Assert\PositiveOrZero]
    public ?int $eventChainTraceabilityCount = null;

    /**
     * Indica si se realizó proceso sobre trazabilidad de fechas de registros de evento
     *
     * @field Evento/R/LanzamientoProcesoDeteccionAnomaliasRegEvento/RealizadoProcesoSobreTrazabilidadFechasRegEvento
     */
    public ?bool $eventDateTraceabilityChecked = null;

    /**
     * @field ...NumeroDeRegistrosEventoProcesadosSobreTrazabilidadFechas
     */
    #[Assert\PositiveOrZero]
    public ?int $eventDateTraceabilityCount = null;

    /**
     * Tipo de anomalía detectada en registros de evento (EventType 06)
     *
     * @field Evento/R/DeteccionAnomaliasRegEvento/TipoAnomalia
     */
    public ?AnomalyType $eventAnomalyType = null;

    /**
     * Descripción adicional de la anomalía detectada en registros de evento
     *
     * @field Evento/R/DeteccionAnomaliasRegEvento/OtrosDatosAnomalia
     */
    #[Assert\Length(max: 100)]
    public ?string $eventAnomalyDetails = null;

    // -------------------------------------------------------------------------
    // Validation callbacks
    // -------------------------------------------------------------------------

    #[Assert\Callback]
    public function validateChaining(ExecutionContextInterface $context): void {
        $setPrevious = array_filter([
            $this->previousEventType !== null,
            $this->previousGeneratedAt !== null,
            $this->previousHash !== null,
        ]);
        $count = count($setPrevious);
        if ($count > 0 && $count < 3) {
            $context->buildViolation('previousEventType, previousGeneratedAt and previousHash must all be set or all be null')
                ->atPath('previousHash')
                ->addViolation();
        }
    }

    #[Assert\Callback]
    public function validateHash(ExecutionContextInterface $context): void {
        if (!isset($this->hash)) {
            return;
        }
        $expectedHash = $this->calculateHash();
        if ($this->hash !== $expectedHash) {
            $context->buildViolation("Invalid hash, expected value $expectedHash")
                ->atPath('hash')
                ->addViolation();
        }
    }

    #[Assert\Callback]
    public function validateThirdParty(ExecutionContextInterface $context): void {
        if ($this->isThirdPartyIssued && $this->thirdPartyIssuer === null) {
            $context->buildViolation('thirdPartyIssuer is required when isThirdPartyIssued is true')
                ->atPath('thirdPartyIssuer')
                ->addViolation();
        }
    }

    // -------------------------------------------------------------------------
    // Hash calculation (Doc. Huella §3.3 – 9 fields, exact order)
    // -------------------------------------------------------------------------

    /**
     * Calculate event record hash
     *
     * The payload uses the exact 9 fields specified in Doc. Huella §3.3:
     * NIF, ID, IdSistemaInformatico, Version, NumeroInstalacion, NIF (issuer),
     * TipoEvento, HuellaEvento (previous), FechaHoraHusoGenEvento
     *
     * Values are NOT URL-encoded; spaces at the start/end of each value are trimmed.
     * When a field is absent its value is left blank (e.g. "ID=").
     *
     * @return string Expected hash (64-char uppercase hex string)
     */
    public function calculateHash(): string {
        // NOTE: Values should NOT be escaped as that is what the AEAT says ¯\_(ツ)_/¯
        $payload  = 'NIF=' . trim($this->system->vendorNif ?? '');
        $payload .= '&ID=' . trim($this->system->vendorId ?? '');
        $payload .= '&IdSistemaInformatico=' . trim($this->system->id);
        $payload .= '&Version=' . trim($this->system->version);
        $payload .= '&NumeroInstalacion=' . trim($this->system->installationNumber);
        $payload .= '&NIF=' . trim($this->issuer->nif);
        $payload .= '&TipoEvento=' . $this->eventType->value;
        $payload .= '&HuellaEvento=' . ($this->previousHash ?? '');
        $payload .= '&FechaHoraHusoGenEvento=' . $this->generatedAt->format('c');
        return strtoupper(hash('sha256', $payload));
    }

    // -------------------------------------------------------------------------
    // XML Export
    // -------------------------------------------------------------------------

    /**
     * Export event record to XML
     *
     * @param UXML $xml XML parent element to append to
     */
    public function export(UXML $xml): void {
        $ns = self::NS;
        $root = $xml->add("sf:RegistroEvento", null, ["xmlns:sf" => $ns]);
        $root->add('sf:IDVersion', '1.0');

        $evento = $root->add('sf:Evento');

        // SistemaInformatico (reuses ComputerSystem::export but under sf: namespace)
        $this->exportComputerSystem($evento);

        // ObligadoEmision
        $obligadoEl = $evento->add('sf:ObligadoEmision');
        $obligadoEl->add('sf:NombreRazon', $this->issuer->name);
        $obligadoEl->add('sf:NIF', $this->issuer->nif);

        // EmitidaPorTerceroODestinatario + TerceroODestinatario
        if ($this->isThirdPartyIssued) {
            $evento->add('sf:EmitidaPorTerceroODestinatario', 'S');
        }
        if ($this->thirdPartyIssuer !== null) {
            $terceroEl = $evento->add('sf:TerceroODestinatario');
            $terceroEl->add('sf:NombreRazon', $this->thirdPartyIssuer->name);
            if ($this->thirdPartyIssuer instanceof FiscalIdentifier) {
                $terceroEl->add('sf:NIF', $this->thirdPartyIssuer->nif);
            } else {
                $idOtroEl = $terceroEl->add('sf:IDOtro');
                $idOtroEl->add('sf:CodigoPais', $this->thirdPartyIssuer->country);
                $idOtroEl->add('sf:IDType', $this->thirdPartyIssuer->type->value);
                $idOtroEl->add('sf:ID', $this->thirdPartyIssuer->value);
            }
        }

        // FechaHoraHusoGenEvento
        $evento->add('sf:FechaHoraHusoGenEvento', $this->generatedAt->format('c'));

        // TipoEvento
        $evento->add('sf:TipoEvento', $this->eventType->value);

        // DatosPropiosEvento (optional sub-blocks)
        $this->exportDatosPropios($evento);

        // OtrosDatosEvento
        if ($this->additionalData !== null) {
            $evento->add('sf:OtrosDatosEvento', $this->additionalData);
        }

        // Encadenamiento
        $encadenamientoEl = $evento->add('sf:Encadenamiento');
        if ($this->previousHash === null) {
            $encadenamientoEl->add('sf:PrimerEvento', 'S');
        } else {
            $eventoAnteriorEl = $encadenamientoEl->add('sf:EventoAnterior');
            $eventoAnteriorEl->add('sf:TipoEvento', $this->previousEventType?->value);
            $eventoAnteriorEl->add('sf:FechaHoraHusoGenEvento', $this->previousGeneratedAt?->format('c'));
            $eventoAnteriorEl->add('sf:HuellaEvento', $this->previousHash);
        }

        $root->add('sf:TipoHuella', '01'); // SHA-256
        $root->add('sf:HuellaEvento', $this->hash);
    }

    /**
     * Export ComputerSystem data under sf: namespace
     *
     * @param UXML $evento Evento XML element
     */
    private function exportComputerSystem(UXML $evento): void {
        $el = $evento->add('sf:SistemaInformatico');
        $el->add('sf:NombreRazon', $this->system->vendorName);
        if ($this->system->vendorNif !== null) {
            $el->add('sf:NIF', $this->system->vendorNif);
        } else {
            $idOtroEl = $el->add('sf:IDOtro');
            $idOtroEl->add('sf:CodigoPais', $this->system->vendorCountry);
            $idOtroEl->add('sf:IDType', $this->system->vendorIdType?->value);
            $idOtroEl->add('sf:ID', $this->system->vendorId);
        }
        $el->add('sf:NombreSistemaInformatico', $this->system->name);
        $el->add('sf:IdSistemaInformatico', $this->system->id);
        $el->add('sf:Version', $this->system->version);
        $el->add('sf:NumeroInstalacion', $this->system->installationNumber);
        $el->add('sf:TipoUsoPosibleSoloVerifactu', $this->system->onlySupportsVerifactu ? 'S' : 'N');
        $el->add('sf:TipoUsoPosibleMultiOT', $this->system->supportsMultipleTaxpayers ? 'S' : 'N');
        $el->add('sf:IndicadorMultiplesOT', $this->system->hasMultipleTaxpayers ? 'S' : 'N');
    }

    /**
     * Export optional DatosPropiosEvento sub-blocks
     *
     * @param UXML $evento Evento XML element
     */
    private function exportDatosPropios(UXML $evento): void {
        $hasInvoiceCheck = $this->invoiceHashIntegrityChecked !== null
            || $this->invoiceSignatureIntegrityChecked !== null
            || $this->invoiceChainTraceabilityChecked !== null
            || $this->invoiceDateTraceabilityChecked !== null;

        $hasInvoiceAnomaly = $this->invoiceAnomalyType !== null;

        $hasEventCheck = $this->eventHashIntegrityChecked !== null
            || $this->eventSignatureIntegrityChecked !== null
            || $this->eventChainTraceabilityChecked !== null
            || $this->eventDateTraceabilityChecked !== null;

        $hasEventAnomaly = $this->eventAnomalyType !== null;

        if (!$hasInvoiceCheck && !$hasInvoiceAnomaly && !$hasEventCheck && !$hasEventAnomaly) {
            return;
        }

        $rEl = $evento->add('sf:R');

        if ($hasInvoiceCheck) {
            $launchEl = $rEl->add('sf:LanzamientoProcesoDeteccionAnomaliasRegFacturacion');
            $this->exportBoolWithCount(
                $launchEl,
                'sf:RealizadoProcesoSobreIntegridadHuellasRegFacturacion',
                'sf:NumeroDeRegistrosFacturacionProcesadosSobreIntegridadHuellas',
                $this->invoiceHashIntegrityChecked,
                $this->invoiceHashIntegrityCount
            );
            $this->exportBoolWithCount(
                $launchEl,
                'sf:RealizadoProcesoSobreIntegridadFirmasRegFacturacion',
                'sf:NumeroDeRegistrosFacturacionProcesadosSobreIntegridadFirmas',
                $this->invoiceSignatureIntegrityChecked,
                $this->invoiceSignatureIntegrityCount
            );
            $this->exportBoolWithCount(
                $launchEl,
                'sf:RealizadoProcesoSobreTrazabilidadCadenaRegFacturacion',
                'sf:NumeroDeRegistrosFacturacionProcesadosSobreTrazabilidadCadena',
                $this->invoiceChainTraceabilityChecked,
                $this->invoiceChainTraceabilityCount
            );
            $this->exportBoolWithCount(
                $launchEl,
                'sf:RealizadoProcesoSobreTrazabilidadFechasRegFacturacion',
                'sf:NumeroDeRegistrosFacturacionProcesadosSobreTrazabilidadFechas',
                $this->invoiceDateTraceabilityChecked,
                $this->invoiceDateTraceabilityCount
            );
        }

        if ($hasInvoiceAnomaly) {
            $anomaliaEl = $rEl->add('sf:DeteccionAnomaliasRegFacturacion');
            $anomaliaEl->add('sf:TipoAnomalia', $this->invoiceAnomalyType?->value);
            if ($this->invoiceAnomalyDetails !== null) {
                $anomaliaEl->add('sf:OtrosDatosAnomalia', $this->invoiceAnomalyDetails);
            }
        }

        if ($hasEventCheck) {
            $launchEventEl = $rEl->add('sf:LanzamientoProcesoDeteccionAnomaliasRegEvento');
            $this->exportBoolWithCount(
                $launchEventEl,
                'sf:RealizadoProcesoSobreIntegridadHuellasRegEvento',
                'sf:NumeroDeRegistrosEventoProcesadosSobreIntegridadHuellas',
                $this->eventHashIntegrityChecked,
                $this->eventHashIntegrityCount
            );
            $this->exportBoolWithCount(
                $launchEventEl,
                'sf:RealizadoProcesoSobreIntegridadFirmasRegEvento',
                'sf:NumeroDeRegistrosEventoProcesadosSobreIntegridadFirmas',
                $this->eventSignatureIntegrityChecked,
                $this->eventSignatureIntegrityCount
            );
            $this->exportBoolWithCount(
                $launchEventEl,
                'sf:RealizadoProcesoSobreTrazabilidadCadenaRegEvento',
                'sf:NumeroDeRegistrosEventoProcesadosSobreTrazabilidadCadena',
                $this->eventChainTraceabilityChecked,
                $this->eventChainTraceabilityCount
            );
            $this->exportBoolWithCount(
                $launchEventEl,
                'sf:RealizadoProcesoSobreTrazabilidadFechasRegEvento',
                'sf:NumeroDeRegistrosEventoProcesadosSobreTrazabilidadFechas',
                $this->eventDateTraceabilityChecked,
                $this->eventDateTraceabilityCount
            );
        }

        if ($hasEventAnomaly) {
            $anomaliaEventEl = $rEl->add('sf:DeteccionAnomaliasRegEvento');
            $anomaliaEventEl->add('sf:TipoAnomalia', $this->eventAnomalyType?->value);
            if ($this->eventAnomalyDetails !== null) {
                $anomaliaEventEl->add('sf:OtrosDatosAnomalia', $this->eventAnomalyDetails);
            }
        }
    }

    /**
     * Helper to export a boolean S/N field and an optional count
     *
     * @param UXML      $parent   Parent element
     * @param string    $boolTag  Tag name for the S/N value
     * @param string    $countTag Tag name for the count
     * @param bool|null $checked  Whether the process was performed
     * @param int|null  $count    Number of records processed
     */
    private function exportBoolWithCount(
        UXML $parent,
        string $boolTag,
        string $countTag,
        ?bool $checked,
        ?int $count
    ): void {
        if ($checked === null) {
            return;
        }
        $parent->add($boolTag, $checked ? 'S' : 'N');
        if ($checked && $count !== null) {
            $parent->add($countTag, (string) $count);
        }
    }

    // -------------------------------------------------------------------------
    // XML Import
    // -------------------------------------------------------------------------

    /**
     * Import event record from XML element
     *
     * @param UXML $xml XML element (the <sf:RegistroEvento> element)
     *
     * @return self New event record instance
     *
     * @throws ImportException if failed to parse XML
     */
    public static function fromXml(UXML $xml): self {
        $record = new self();

        $evento = $xml->get('sf:Evento');
        if ($evento === null) {
            throw new ImportException('Missing <sf:Evento /> element');
        }

        // ComputerSystem
        $sistemaEl = $evento->get('sf:SistemaInformatico');
        if ($sistemaEl === null) {
            throw new ImportException('Missing <sf:SistemaInformatico /> element');
        }
        $record->system = self::importComputerSystem($sistemaEl);

        // ObligadoEmision
        $obligadoEl = $evento->get('sf:ObligadoEmision');
        if ($obligadoEl === null) {
            throw new ImportException('Missing <sf:ObligadoEmision /> element');
        }
        $issuerName = $obligadoEl->get('sf:NombreRazon')?->asText();
        if ($issuerName === null) {
            throw new ImportException('Missing <sf:NombreRazon /> from <sf:ObligadoEmision />');
        }
        $issuerNif = $obligadoEl->get('sf:NIF')?->asText();
        if ($issuerNif === null) {
            throw new ImportException('Missing <sf:NIF /> from <sf:ObligadoEmision />');
        }
        $record->issuer = new FiscalIdentifier($issuerName, $issuerNif);

        // EmitidaPorTerceroODestinatario
        $terceroFlag = $evento->get('sf:EmitidaPorTerceroODestinatario')?->asText() ?? 'N';
        $record->isThirdPartyIssued = ($terceroFlag === 'S');

        // TerceroODestinatario (optional)
        $terceroEl = $evento->get('sf:TerceroODestinatario');
        if ($terceroEl !== null) {
            $terceroName = $terceroEl->get('sf:NombreRazon')?->asText();
            if ($terceroName === null) {
                throw new ImportException('Missing <sf:NombreRazon /> from <sf:TerceroODestinatario />');
            }
            $terceroNif = $terceroEl->get('sf:NIF')?->asText();
            if ($terceroNif !== null) {
                $record->thirdPartyIssuer = new FiscalIdentifier($terceroName, $terceroNif);
            } else {
                $terceroCountry = $terceroEl->get('sf:IDOtro/sf:CodigoPais')?->asText();
                if ($terceroCountry === null) {
                    throw new ImportException('Missing <sf:CodigoPais /> from <sf:TerceroODestinatario />');
                }
                $rawTerceroType = $terceroEl->get('sf:IDOtro/sf:IDType')?->asText();
                if ($rawTerceroType === null) {
                    throw new ImportException('Missing <sf:IDType /> from <sf:TerceroODestinatario />');
                }
                $terceroType = ForeignIdType::tryFrom($rawTerceroType);
                if ($terceroType === null) {
                    throw new ImportException('Invalid value for <sf:IDType /> from <sf:TerceroODestinatario />');
                }
                $terceroValue = $terceroEl->get('sf:IDOtro/sf:ID')?->asText();
                if ($terceroValue === null) {
                    throw new ImportException('Missing <sf:ID /> from <sf:TerceroODestinatario />');
                }
                $record->thirdPartyIssuer = new ForeignFiscalIdentifier($terceroName, $terceroCountry, $terceroType, $terceroValue);
            }
        }

        // FechaHoraHusoGenEvento
        $rawGeneratedAt = $evento->get('sf:FechaHoraHusoGenEvento')?->asText();
        if ($rawGeneratedAt === null) {
            throw new ImportException('Missing <sf:FechaHoraHusoGenEvento /> element');
        }
        $generatedAt = DateTimeImmutable::createFromFormat(DateTimeInterface::ISO8601, $rawGeneratedAt);
        if ($generatedAt === false) {
            throw new ImportException('Invalid value for <sf:FechaHoraHusoGenEvento /> element');
        }
        $record->generatedAt = $generatedAt;

        // TipoEvento
        $rawEventType = $evento->get('sf:TipoEvento')?->asText();
        if ($rawEventType === null) {
            throw new ImportException('Missing <sf:TipoEvento /> element');
        }
        $eventType = EventType::tryFrom($rawEventType);
        if ($eventType === null) {
            throw new ImportException('Invalid value for <sf:TipoEvento /> element');
        }
        $record->eventType = $eventType;

        // DatosPropiosEvento (optional)
        $rEl = $evento->get('sf:R');
        if ($rEl !== null) {
            $record->importDatosPropios($rEl);
        }

        // OtrosDatosEvento
        $record->additionalData = $evento->get('sf:OtrosDatosEvento')?->asText();

        // Encadenamiento
        $encadenamientoEl = $evento->get('sf:Encadenamiento');
        if ($encadenamientoEl === null) {
            throw new ImportException('Missing <sf:Encadenamiento /> element');
        }
        $primerEvento = $encadenamientoEl->get('sf:PrimerEvento')?->asText();
        if ($primerEvento === null) {
            // Must have EventoAnterior
            $eventoAnteriorEl = $encadenamientoEl->get('sf:EventoAnterior');
            if ($eventoAnteriorEl === null) {
                throw new ImportException('Missing <sf:PrimerEvento /> or <sf:EventoAnterior /> in <sf:Encadenamiento />');
            }
            $rawPrevType = $eventoAnteriorEl->get('sf:TipoEvento')?->asText();
            if ($rawPrevType === null) {
                throw new ImportException('Missing <sf:TipoEvento /> from <sf:EventoAnterior />');
            }
            $prevType = EventType::tryFrom($rawPrevType);
            if ($prevType === null) {
                throw new ImportException('Invalid value for <sf:TipoEvento /> from <sf:EventoAnterior />');
            }
            $record->previousEventType = $prevType;

            $rawPrevAt = $eventoAnteriorEl->get('sf:FechaHoraHusoGenEvento')?->asText();
            if ($rawPrevAt === null) {
                throw new ImportException('Missing <sf:FechaHoraHusoGenEvento /> from <sf:EventoAnterior />');
            }
            $prevAt = DateTimeImmutable::createFromFormat(DateTimeInterface::ISO8601, $rawPrevAt);
            if ($prevAt === false) {
                throw new ImportException('Invalid value for <sf:FechaHoraHusoGenEvento /> from <sf:EventoAnterior />');
            }
            $record->previousGeneratedAt = $prevAt;

            $prevHash = $eventoAnteriorEl->get('sf:HuellaEvento')?->asText();
            if ($prevHash === null) {
                throw new ImportException('Missing <sf:HuellaEvento /> from <sf:EventoAnterior />');
            }
            $record->previousHash = $prevHash;
        }

        // HuellaEvento (root level, not inside Evento)
        $hash = $xml->get('sf:HuellaEvento')?->asText();
        if ($hash === null) {
            throw new ImportException('Missing <sf:HuellaEvento /> element');
        }
        $record->hash = $hash;

        return $record;
    }

    /**
     * Import ComputerSystem from XML (sf: namespace)
     *
     * @param UXML $el SistemaInformatico element
     *
     * @return ComputerSystem
     *
     * @throws ImportException if failed to parse XML
     */
    private static function importComputerSystem(UXML $el): ComputerSystem {
        $system = new ComputerSystem();

        $vendorName = $el->get('sf:NombreRazon')?->asText();
        if ($vendorName === null) {
            throw new ImportException('Missing <sf:NombreRazon /> from <sf:SistemaInformatico />');
        }
        $system->vendorName = $vendorName;

        $vendorNif = $el->get('sf:NIF')?->asText();
        if ($vendorNif !== null) {
            $system->vendorNif = $vendorNif;
        } else {
            $system->vendorCountry = $el->get('sf:IDOtro/sf:CodigoPais')?->asText();
            $rawVendorIdType = $el->get('sf:IDOtro/sf:IDType')?->asText();
            if ($rawVendorIdType !== null) {
                $system->vendorIdType = ForeignIdType::tryFrom($rawVendorIdType);
            }
            $system->vendorId = $el->get('sf:IDOtro/sf:ID')?->asText();
        }

        $name = $el->get('sf:NombreSistemaInformatico')?->asText();
        if ($name === null) {
            throw new ImportException('Missing <sf:NombreSistemaInformatico /> from <sf:SistemaInformatico />');
        }
        $system->name = $name;

        $id = $el->get('sf:IdSistemaInformatico')?->asText();
        if ($id === null) {
            throw new ImportException('Missing <sf:IdSistemaInformatico /> from <sf:SistemaInformatico />');
        }
        $system->id = $id;

        $version = $el->get('sf:Version')?->asText();
        if ($version === null) {
            throw new ImportException('Missing <sf:Version /> from <sf:SistemaInformatico />');
        }
        $system->version = $version;

        $installationNumber = $el->get('sf:NumeroInstalacion')?->asText();
        if ($installationNumber === null) {
            throw new ImportException('Missing <sf:NumeroInstalacion /> from <sf:SistemaInformatico />');
        }
        $system->installationNumber = $installationNumber;

        $onlySupportsVerifactu = $el->get('sf:TipoUsoPosibleSoloVerifactu')?->asText() ?? 'N';
        $supportsMultipleTaxpayers = $el->get('sf:TipoUsoPosibleMultiOT')?->asText() ?? 'N';
        $hasMultipleTaxpayers = $el->get('sf:IndicadorMultiplesOT')?->asText() ?? 'N';
        $system->onlySupportsVerifactu = ($onlySupportsVerifactu === 'S');
        $system->supportsMultipleTaxpayers = ($supportsMultipleTaxpayers === 'S');
        $system->hasMultipleTaxpayers = ($hasMultipleTaxpayers === 'S');

        return $system;
    }

    /**
     * Import DatosPropiosEvento sub-blocks
     *
     * @param UXML $rEl The <sf:R> element
     */
    private function importDatosPropios(UXML $rEl): void {
        // Invoice anomaly check (type 03)
        $invoiceLaunchEl = $rEl->get('sf:LanzamientoProcesoDeteccionAnomaliasRegFacturacion');
        if ($invoiceLaunchEl !== null) {
            $this->invoiceHashIntegrityChecked = $this->importBool(
                $invoiceLaunchEl,
                'sf:RealizadoProcesoSobreIntegridadHuellasRegFacturacion'
            );
            $this->invoiceHashIntegrityCount = $this->importCount(
                $invoiceLaunchEl,
                'sf:NumeroDeRegistrosFacturacionProcesadosSobreIntegridadHuellas'
            );
            $this->invoiceSignatureIntegrityChecked = $this->importBool(
                $invoiceLaunchEl,
                'sf:RealizadoProcesoSobreIntegridadFirmasRegFacturacion'
            );
            $this->invoiceSignatureIntegrityCount = $this->importCount(
                $invoiceLaunchEl,
                'sf:NumeroDeRegistrosFacturacionProcesadosSobreIntegridadFirmas'
            );
            $this->invoiceChainTraceabilityChecked = $this->importBool(
                $invoiceLaunchEl,
                'sf:RealizadoProcesoSobreTrazabilidadCadenaRegFacturacion'
            );
            $this->invoiceChainTraceabilityCount = $this->importCount(
                $invoiceLaunchEl,
                'sf:NumeroDeRegistrosFacturacionProcesadosSobreTrazabilidadCadena'
            );
            $this->invoiceDateTraceabilityChecked = $this->importBool(
                $invoiceLaunchEl,
                'sf:RealizadoProcesoSobreTrazabilidadFechasRegFacturacion'
            );
            $this->invoiceDateTraceabilityCount = $this->importCount(
                $invoiceLaunchEl,
                'sf:NumeroDeRegistrosFacturacionProcesadosSobreTrazabilidadFechas'
            );
        }

        // Invoice anomaly detected (type 04)
        $invoiceAnomaliaEl = $rEl->get('sf:DeteccionAnomaliasRegFacturacion');
        if ($invoiceAnomaliaEl !== null) {
            $rawType = $invoiceAnomaliaEl->get('sf:TipoAnomalia')?->asText();
            if ($rawType !== null) {
                $this->invoiceAnomalyType = AnomalyType::tryFrom($rawType);
            }
            $this->invoiceAnomalyDetails = $invoiceAnomaliaEl->get('sf:OtrosDatosAnomalia')?->asText();
        }

        // Event anomaly check (type 05)
        $eventLaunchEl = $rEl->get('sf:LanzamientoProcesoDeteccionAnomaliasRegEvento');
        if ($eventLaunchEl !== null) {
            $this->eventHashIntegrityChecked = $this->importBool(
                $eventLaunchEl,
                'sf:RealizadoProcesoSobreIntegridadHuellasRegEvento'
            );
            $this->eventHashIntegrityCount = $this->importCount(
                $eventLaunchEl,
                'sf:NumeroDeRegistrosEventoProcesadosSobreIntegridadHuellas'
            );
            $this->eventSignatureIntegrityChecked = $this->importBool(
                $eventLaunchEl,
                'sf:RealizadoProcesoSobreIntegridadFirmasRegEvento'
            );
            $this->eventSignatureIntegrityCount = $this->importCount(
                $eventLaunchEl,
                'sf:NumeroDeRegistrosEventoProcesadosSobreIntegridadFirmas'
            );
            $this->eventChainTraceabilityChecked = $this->importBool(
                $eventLaunchEl,
                'sf:RealizadoProcesoSobreTrazabilidadCadenaRegEvento'
            );
            $this->eventChainTraceabilityCount = $this->importCount(
                $eventLaunchEl,
                'sf:NumeroDeRegistrosEventoProcesadosSobreTrazabilidadCadena'
            );
            $this->eventDateTraceabilityChecked = $this->importBool(
                $eventLaunchEl,
                'sf:RealizadoProcesoSobreTrazabilidadFechasRegEvento'
            );
            $this->eventDateTraceabilityCount = $this->importCount(
                $eventLaunchEl,
                'sf:NumeroDeRegistrosEventoProcesadosSobreTrazabilidadFechas'
            );
        }

        // Event anomaly detected (type 06)
        $eventAnomaliaEl = $rEl->get('sf:DeteccionAnomaliasRegEvento');
        if ($eventAnomaliaEl !== null) {
            $rawType = $eventAnomaliaEl->get('sf:TipoAnomalia')?->asText();
            if ($rawType !== null) {
                $this->eventAnomalyType = AnomalyType::tryFrom($rawType);
            }
            $this->eventAnomalyDetails = $eventAnomaliaEl->get('sf:OtrosDatosAnomalia')?->asText();
        }
    }

    /**
     * Import a boolean S/N field, returning null if absent
     *
     * @param UXML   $parent Parent element
     * @param string $tag    Element tag name
     *
     * @return bool|null
     */
    private function importBool(UXML $parent, string $tag): ?bool {
        $val = $parent->get($tag)?->asText();
        if ($val === null) {
            return null;
        }
        return $val === 'S';
    }

    /**
     * Import an integer count field, returning null if absent
     *
     * @param UXML   $parent Parent element
     * @param string $tag    Element tag name
     *
     * @return int|null
     */
    private function importCount(UXML $parent, string $tag): ?int {
        $val = $parent->get($tag)?->asText();
        if ($val === null) {
            return null;
        }
        return (int) $val;
    }
}

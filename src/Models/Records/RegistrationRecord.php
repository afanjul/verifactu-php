<?php
namespace josemmo\Verifactu\Models\Records;

use DateTimeImmutable;
use josemmo\Verifactu\Exceptions\ImportException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use UXML\UXML;

/**
 * Registro de alta de una factura
 *
 * @field RegistroAlta
 */
class RegistrationRecord extends Record {
    /**
     * Referencia externa del registro de facturación
     *
     * @field RefExterna
     */
    #[Assert\Length(max: 60)]
    public ?string $externalRef = null;

    /**
     * Indicador de subsanación de un registro de facturación de alta previamente generado
     *
     * @field Subsanacion
     */
    #[Assert\NotNull]
    #[Assert\Type('boolean')]
    public bool $isCorrection = false;

    /**
     * Indicador de rechazo previo
     *
     * Para ser usado en la remisión de un nuevo registro de facturación de alta subsanado tras haber sido rechazado en
     * su remisión inmediatamente anterior.
     * Es decir, en el último envío que contenía ese registro de facturación de alta rechazado.
     *
     * @field RechazoPrevio
     */
    public PreviousRejectionType $isPriorRejection = PreviousRejectionType::N;

    /**
     * Nombre-razón social del obligado a expedir la factura
     *
     * @field NombreRazonEmisor
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public string $issuerName;

    /**
     * Especificación del tipo de factura
     *
     * @field TipoFactura
     */
    #[Assert\NotBlank]
    public InvoiceType $invoiceType;

    /**
     * Fecha en la que se realiza la operación
     *
     * NOTE: Time part will be ignored.
     *
     * @field FechaOperacion
     */
    public ?DateTimeImmutable $operationDate = null;

    /**
     * Descripción del objeto de la factura
     *
     * @field DescripcionOperacion
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    public string $description;

    /**
     * Especifica si la factura simplificada fue expedida según los artículos 72 y 73 del reglamento del IVA
     *
     * @field FacturaSimplificadaArt7273
     */
    public bool $isSimplifiedArt7273 = false;

    /**
     * Especifica si la factura fue expedida sin identificación del destinatario según el artículo 6.1.d del reglamento del IVA
     *
     * @field FacturaSinIdentifDestinatarioArt61d
     */
    public bool $withoutRecipientIdArt61d = false;

    /**
     * Especifica si la factura tiene un importe total igual o superior a 100.000.000 €
     *
     * @field Macrodato
     */
    public bool $isMacrodato = false;

    /**
     * Indicador de si la factura fue emitida por un tercero o por el destinatario
     *
     * @field EmitidaPorTerceroODestinatario
     */
    public ?ThirdPartyType $issuedByThirdParty = null;

    /**
     * Identificación del tercero o destinatario que emite la factura
     *
     * @field Tercero
     */
    #[Assert\Valid]
    public FiscalIdentifier|ForeignFiscalIdentifier|null $thirdParty = null;

    /**
     * Destinatarios de la factura
     *
     * @var array<FiscalIdentifier | ForeignFiscalIdentifier>
     *
     * @field Destinatarios
     */
    #[Assert\Valid]
    #[Assert\Count(max: 1000)]
    public array $recipients = [];

    /**
     * Especifica si la factura fue expedida mediante cupón
     *
     * @field Cupon
     */
    public bool $hasCoupon = false;

    /**
     * Tipo de factura rectificativa
     *
     * @field TipoRectificativa
     */
    public ?CorrectiveType $correctiveType = null;

    /**
     * Listado de facturas rectificadas
     *
     * @var InvoiceIdentifier[]
     *
     * @field FacturasRectificadas
     */
    public array $correctedInvoices = [];

    /**
     * Base imponible rectificada (para facturas rectificativas por sustitución)
     *
     * @field ImporteRectificacion/BaseRectificada
     */
    #[Assert\Regex(pattern: '/^-?\d{1,12}\.\d{2}$/')]
    public ?string $correctedBaseAmount = null;

    /**
     * Cuota repercutida o soportada rectificada (para facturas rectificativas por sustitución)
     *
     * @field ImporteRectificacion/CuotaRectificada
     */
    #[Assert\Regex(pattern: '/^-?\d{1,12}\.\d{2}$/')]
    public ?string $correctedTaxAmount = null;

    /**
     * Cuota del recargo de equivalencia rectificada (para facturas rectificativas por sustitución)
     *
     * @field ImporteRectificacion/CuotaRecargoRectificado
     */
    #[Assert\Regex(pattern: '/^-?\d{1,12}\.\d{2}$/')]
    public ?string $correctedSurchargeAmount = null;

    /**
     * Listado de facturas sustituidas
     *
     * @var InvoiceIdentifier[]
     *
     * @field FacturasSustituidas
     */
    public array $replacedInvoices = [];

    /**
     * Desglose de la factura
     *
     * @var BreakdownDetails[]
     *
     * @field Desglose
     */
    #[Assert\Valid]
    #[Assert\Count(min: 1, max: 12)]
    public array $breakdown = [];

    /**
     * Importe total de la cuota (sumatorio de la Cuota Repercutida y Cuota de Recargo de Equivalencia)
     *
     * @field CuotaTotal
     */
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^-?\d{1,12}\.\d{2}$/')]
    public string $totalTaxAmount;

    /**
     * Importe total de la factura
     *
     * @field ImporteTotal
     */
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^-?\d{1,12}\.\d{2}$/')]
    public string $totalAmount;

    /**
     * Número de registro del acuerdo de facturación
     *
     * @field NumRegistroAcuerdoFacturacion
     */
    #[Assert\Length(max: 15)]
    public ?string $billingAgreementNumber = null;

    /**
     * Identificador del acuerdo entre sistema informático y obligado tributario
     *
     * @field IdAcuerdoSistemaInformatico
     */
    #[Assert\Length(max: 16)]
    public ?string $systemAgreementId = null;

    /**
     * @inheritDoc
     */
    protected static function getRecordElementName(): string {
        return 'RegistroAlta';
    }

    /**
     * @inheritDoc
     */
    public function calculateHash(): string {
        // NOTE: Values should NOT be escaped as that what the AEAT says ¯\_(ツ)_/¯
        $payload  = 'IDEmisorFactura=' . trim($this->invoiceId->issuerId);
        $payload .= '&NumSerieFactura=' . trim($this->invoiceId->invoiceNumber);
        $payload .= '&FechaExpedicionFactura=' . $this->invoiceId->issueDate->format('d-m-Y');
        $payload .= '&TipoFactura=' . $this->invoiceType->value;
        $payload .= '&CuotaTotal=' . trim($this->totalTaxAmount);
        $payload .= '&ImporteTotal=' . trim($this->totalAmount);
        $payload .= '&Huella=' . ($this->previousHash ?? '');
        $payload .= '&FechaHoraHusoGenRegistro=' . $this->hashedAt->format('c');
        return strtoupper(hash('sha256', $payload));
    }

    #[Assert\Callback]
    final public function validatePriorRejection(ExecutionContextInterface $context): void {
        if ($this->isPriorRejection !== PreviousRejectionType::N && !$this->isCorrection) {
            $context->buildViolation('Record cannot be a prior rejection if it is not a correction')
                ->atPath('isPriorRejection')
                ->addViolation();
        }
    }

    #[Assert\Callback]
    final public function validateTotals(ExecutionContextInterface $context): void {
        if (!isset($this->breakdown) || !isset($this->totalTaxAmount) || !isset($this->totalAmount)) {
            return;
        }

        $expectedTotalBaseAmount = 0;
        $expectedTotalTaxAmount = 0;
        foreach ($this->breakdown as $details) {
            if (!isset($details->baseAmount) || !isset($details->taxAmount)) {
                return;
            }
            $expectedTotalBaseAmount += $details->baseAmount;
            $expectedTotalTaxAmount += $details->taxAmount;
            $expectedTotalTaxAmount += $details->surchargeAmount ?? 0;
        }

        $expectedTotalTaxAmount = number_format($expectedTotalTaxAmount, 2, '.', '');
        if ($this->totalTaxAmount !== $expectedTotalTaxAmount) {
            $context->buildViolation("Expected total tax amount of $expectedTotalTaxAmount, got {$this->totalTaxAmount}")
                ->atPath('totalTaxAmount')
                ->addViolation();
        }

        // Special regimes (C03, C05, C06, C08, C09) exempt from strict ImporteTotal validation
        $hasSpecialRegime = false;
        foreach ($this->breakdown as $details) {
            if (isset($details->regimeType) && in_array($details->regimeType, [
                RegimeType::C03,
                RegimeType::C05,
                RegimeType::C06,
                RegimeType::C08,
                RegimeType::C09,
            ], true)) {
                $hasSpecialRegime = true;
                break;
            }
        }
        if ($hasSpecialRegime) {
            return;
        }

        $isValidTotalAmount = false;
        $bestTotalAmount = number_format($expectedTotalBaseAmount + $expectedTotalTaxAmount, 2, '.', '');
        foreach ([0, -0.01, 0.01, -0.02, 0.02] as $tolerance) {
            $expectedTotalAmount = number_format($bestTotalAmount + $tolerance, 2, '.', '');
            if ($this->totalAmount === $expectedTotalAmount) {
                $isValidTotalAmount = true;
                break;
            }
        }
        if (!$isValidTotalAmount) {
            $context->buildViolation("Expected total amount of $bestTotalAmount, got {$this->totalAmount}")
                ->atPath('totalAmount')
                ->addViolation();
        }
    }

    #[Assert\Callback]
    final public function validateRecipients(ExecutionContextInterface $context): void {
        if (!isset($this->invoiceType)) {
            return;
        }

        $hasRecipients = count($this->recipients) > 0;
        if ($this->invoiceType === InvoiceType::Simplificada || $this->invoiceType === InvoiceType::R5) {
            if ($hasRecipients) {
                $context->buildViolation('This type of invoice cannot have recipients')
                    ->atPath('recipients')
                    ->addViolation();
            }
        } elseif (!$hasRecipients) {
            $context->buildViolation('This type of invoice requires at least one recipient')
                ->atPath('recipients')
                ->addViolation();
        }
    }

    #[Assert\Callback]
    final public function validateCorrectiveDetails(ExecutionContextInterface $context): void {
        if (!isset($this->invoiceType)) {
            return;
        }

        $isCorrective = in_array($this->invoiceType, [
            InvoiceType::R1,
            InvoiceType::R2,
            InvoiceType::R3,
            InvoiceType::R4,
            InvoiceType::R5,
        ], true);

        // Corrective type
        if ($isCorrective && $this->correctiveType === null) {
            $context->buildViolation('Missing type for corrective invoice')
                ->atPath('correctiveType')
                ->addViolation();
        } elseif (!$isCorrective && $this->correctiveType !== null) {
            $context->buildViolation('This type of invoice cannot have a corrective type')
                ->atPath('correctiveType')
                ->addViolation();
        }

        // Corrected invoices
        if (!$isCorrective && count($this->correctedInvoices) > 0) {
            $context->buildViolation('This type of invoice cannot have corrected invoices')
                ->atPath('correctedInvoices')
                ->addViolation();
        }

        // Corrected amounts
        if ($this->correctiveType === CorrectiveType::Substitution) {
            if ($this->correctedBaseAmount === null) {
                $context->buildViolation('Missing corrected base amount for corrective invoice by substitution')
                    ->atPath('correctedBaseAmount')
                    ->addViolation();
            }
            if ($this->correctedTaxAmount === null) {
                $context->buildViolation('Missing corrected tax amount for corrective invoice by substitution')
                    ->atPath('correctedTaxAmount')
                    ->addViolation();
            }
        } else {
            if ($this->correctedBaseAmount !== null) {
                $context->buildViolation('This invoice cannot have a corrected base amount')
                    ->atPath('correctedBaseAmount')
                    ->addViolation();
            }
            if ($this->correctedTaxAmount !== null) {
                $context->buildViolation('This invoice cannot have a corrected tax amount')
                    ->atPath('correctedTaxAmount')
                    ->addViolation();
            }
        }
    }

    #[Assert\Callback]
    final public function validateReplacedInvoices(ExecutionContextInterface $context): void {
        if (!isset($this->invoiceType)) {
            return;
        }

        if ($this->invoiceType !== InvoiceType::Sustitutiva && count($this->replacedInvoices) > 0) {
            $context->buildViolation('This type of invoice cannot have replaced invoices')
                ->atPath('replacedInvoices')
                ->addViolation();
        }
    }

    #[Assert\Callback]
    final public function validateMacrodato(ExecutionContextInterface $context): void {
        if (!isset($this->totalAmount)) {
            return;
        }
        $totalAmount = (float) $this->totalAmount;
        if ($totalAmount >= 100000000 && !$this->isMacrodato) {
            $context->buildViolation('Macrodato flag must be true when total amount is >= 100,000,000')
                ->atPath('isMacrodato')
                ->addViolation();
        }
    }

    #[Assert\Callback]
    final public function validateThirdParty(ExecutionContextInterface $context): void {
        if ($this->issuedByThirdParty !== null && $this->thirdParty === null) {
            $context->buildViolation('Third party details are required when issuedByThirdParty is set')
                ->atPath('thirdParty')
                ->addViolation();
        }
        if ($this->issuedByThirdParty === null && $this->thirdParty !== null) {
            $context->buildViolation('Third party type must be set when thirdParty is provided')
                ->atPath('issuedByThirdParty')
                ->addViolation();
        }
    }

    /**
     * @inheritDoc
     */
    protected function importCustomProperties(UXML $recordElement): void {
        // Invoice ID
        $idFacturaElement = $recordElement->get('sum1:IDFactura');
        if ($idFacturaElement === null) {
            throw new ImportException('Missing <sum1:IDFactura /> element');
        }
        $this->invoiceId = InvoiceIdentifier::fromXml($idFacturaElement);

        // External reference
        $this->externalRef = $recordElement->get('sum1:RefExterna')?->asText();

        // Issuer name
        $issuerName = $recordElement->get('sum1:NombreRazonEmisor')?->asText();
        if ($issuerName === null) {
            throw new ImportException('Missing <sum1:NombreRazonEmisor /> element');
        }
        $this->issuerName = $issuerName;

        // Flags
        $isCorrection = $recordElement->get('sum1:Subsanacion')?->asText() ?? 'N';
        $this->isCorrection = ($isCorrection === 'S');
        $rawPriorRejection = $recordElement->get('sum1:RechazoPrevio')?->asText() ?? 'N';
        $priorRejection = PreviousRejectionType::tryFrom($rawPriorRejection);
        if ($priorRejection === null) {
            throw new ImportException('Invalid value for <sum1:RechazoPrevio /> element');
        }
        $this->isPriorRejection = $priorRejection;

        // Invoice type
        $rawInvoiceType = $recordElement->get('sum1:TipoFactura')?->asText();
        if ($rawInvoiceType === null) {
            throw new ImportException('Missing <sum1:TipoFactura /> element');
        }
        $invoiceType = InvoiceType::tryFrom($rawInvoiceType);
        if ($invoiceType === null) {
            throw new ImportException('Invalid value for <sum1:TipoFactura /> element');
        }
        $this->invoiceType = $invoiceType;

        // Corrective details
        $rawCorrectiveType = $recordElement->get('sum1:TipoRectificativa')?->asText();
        if ($rawCorrectiveType !== null) {
            $correctiveType = CorrectiveType::tryFrom($rawCorrectiveType);
            if ($correctiveType === null) {
                throw new ImportException('Invalid value for <sum1:TipoRectificativa /> element');
            }
            $this->correctiveType = $correctiveType;
        }
        foreach ($recordElement->getAll('sum1:FacturasRectificadas/sum1:IDFacturaRectificada') as $facturaRectificadaElement) {
            $this->correctedInvoices[] = InvoiceIdentifier::fromXml($facturaRectificadaElement);
        }
        foreach ($recordElement->getAll('sum1:FacturasSustituidas/sum1:IDFacturaSustituida') as $facturaSustituidaElement) {
            $this->replacedInvoices[] = InvoiceIdentifier::fromXml($facturaSustituidaElement);
        }
        $this->correctedBaseAmount = $recordElement->get('sum1:ImporteRectificacion/sum1:BaseRectificada')?->asText();
        $this->correctedTaxAmount = $recordElement->get('sum1:ImporteRectificacion/sum1:CuotaRectificada')?->asText();
        $this->correctedSurchargeAmount = $recordElement->get('sum1:ImporteRectificacion/sum1:CuotaRecargoRectificado')?->asText();

        // Operation date
        $rawOperationDate = $recordElement->get('sum1:FechaOperacion')?->asText();
        if ($rawOperationDate !== null) {
            $operationDate = DateTimeImmutable::createFromFormat('d-m-Y', $rawOperationDate);
            if ($operationDate === false) {
                throw new ImportException('Invalid value for <sum1:FechaOperacion /> element');
            }
            $this->operationDate = $operationDate;
        }

        // Description
        $description = $recordElement->get('sum1:DescripcionOperacion')?->asText();
        if ($description === null) {
            throw new ImportException('Missing <sum1:DescripcionOperacion /> element');
        }
        $this->description = $description;

        // Optional boolean indicators
        $this->isSimplifiedArt7273 = ($recordElement->get('sum1:FacturaSimplificadaArt7273')?->asText() === 'S');
        $this->withoutRecipientIdArt61d = ($recordElement->get('sum1:FacturaSinIdentifDestinatarioArt61d')?->asText() === 'S');
        $this->isMacrodato = ($recordElement->get('sum1:Macrodato')?->asText() === 'S');

        // Third party / recipient issuer
        $rawIssuedByThirdParty = $recordElement->get('sum1:EmitidaPorTerceroODestinatario')?->asText();
        if ($rawIssuedByThirdParty !== null) {
            $issuedByThirdParty = ThirdPartyType::tryFrom($rawIssuedByThirdParty);
            if ($issuedByThirdParty === null) {
                throw new ImportException('Invalid value for <sum1:EmitidaPorTerceroODestinatario /> element');
            }
            $this->issuedByThirdParty = $issuedByThirdParty;
        }
        $terceroElement = $recordElement->get('sum1:Tercero');
        if ($terceroElement !== null) {
            $terceroName = $terceroElement->get('sum1:NombreRazon')?->asText();
            if ($terceroName === null) {
                throw new ImportException('Missing <sum1:NombreRazon /> from <sum1:Tercero /> element');
            }
            $terceroNif = $terceroElement->get('sum1:NIF')?->asText();
            if ($terceroNif !== null) {
                $this->thirdParty = new FiscalIdentifier($terceroName, $terceroNif);
            } else {
                $terceroCountry = $terceroElement->get('sum1:IDOtro/sum1:CodigoPais')?->asText();
                if ($terceroCountry === null) {
                    throw new ImportException('Missing <sum1:CodigoPais /> from <sum1:Tercero /> element');
                }
                $rawTerceroType = $terceroElement->get('sum1:IDOtro/sum1:IDType')?->asText();
                if ($rawTerceroType === null) {
                    throw new ImportException('Missing <sum1:IDType /> from <sum1:Tercero /> element');
                }
                $terceroType = ForeignIdType::tryFrom($rawTerceroType);
                if ($terceroType === null) {
                    throw new ImportException('Invalid value for <sum1:IDType /> from <sum1:Tercero /> element');
                }
                $terceroValue = $terceroElement->get('sum1:IDOtro/sum1:ID')?->asText();
                if ($terceroValue === null) {
                    throw new ImportException('Missing <sum1:ID /> from <sum1:Tercero /> element');
                }
                $this->thirdParty = new ForeignFiscalIdentifier($terceroName, $terceroCountry, $terceroType, $terceroValue);
            }
        }

        // Recipients
        foreach ($recordElement->getAll('sum1:Destinatarios/sum1:IDDestinatario') as $destinatarioElement) {
            $recipientName = $destinatarioElement->get('sum1:NombreRazon')?->asText();
            if ($recipientName === null) {
                throw new ImportException('Missing <sum1:NombreRazon /> from <sum1:IDDestinatario /> element');
            }

            // Fiscal identifier
            $recipientNif = $destinatarioElement->get('sum1:NIF')?->asText();
            if ($recipientNif !== null) {
                $this->recipients[] = new FiscalIdentifier($recipientName, $recipientNif);
                continue;
            }

            // Foreign fiscal identifier
            $recipientCountry = $destinatarioElement->get('sum1:IDOtro/sum1:CodigoPais')?->asText();
            if ($recipientCountry === null) {
                throw new ImportException('Missing <sum1:CodigoPais /> from <sum1:IDDestinatario /> element');
            }
            $rawRecipientType = $destinatarioElement->get('sum1:IDOtro/sum1:IDType')?->asText();
            if ($rawRecipientType === null) {
                throw new ImportException('Missing <sum1:IDType /> from <sum1:IDDestinatario /> element');
            }
            $recipientType = ForeignIdType::tryFrom($rawRecipientType);
            if ($recipientType === null) {
                throw new ImportException('Invalid value for <sum1:IDType /> from <sum1:IDDestinatario /> element');
            }
            $recipientValue = $destinatarioElement->get('sum1:IDOtro/sum1:ID')?->asText();
            if ($recipientValue === null) {
                throw new ImportException('Missing <sum1:ID /> from <sum1:IDDestinatario /> element');
            }
            $this->recipients[] = new ForeignFiscalIdentifier($recipientName, $recipientCountry, $recipientType, $recipientValue);
        }

        // Coupon
        $this->hasCoupon = ($recordElement->get('sum1:Cupon')?->asText() === 'S');

        // Breakdown
        foreach ($recordElement->getAll('sum1:Desglose/sum1:DetalleDesglose') as $detalleDesgloseElement) {
            $this->breakdown[] = BreakdownDetails::fromXml($detalleDesgloseElement);
        }

        // Total tax amount
        $totalTaxAmount = $recordElement->get('sum1:CuotaTotal')?->asText();
        if ($totalTaxAmount === null) {
            throw new ImportException('Missing <sum1:CuotaTotal /> element');
        }
        $this->totalTaxAmount = $totalTaxAmount;

        // Total amount
        $totalAmount = $recordElement->get('sum1:ImporteTotal')?->asText();
        if ($totalAmount === null) {
            throw new ImportException('Missing <sum1:ImporteTotal /> element');
        }
        $this->totalAmount = $totalAmount;

        // Billing agreement fields
        $this->billingAgreementNumber = $recordElement->get('sum1:NumRegistroAcuerdoFacturacion')?->asText();
        $this->systemAgreementId = $recordElement->get('sum1:IdAcuerdoSistemaInformatico')?->asText();
    }

    /**
     * @inheritDoc
     */
    protected function exportCustomProperties(UXML $recordElement): void {
        // Invoice ID
        $idFacturaElement = $recordElement->add('sum1:IDFactura');
        $this->invoiceId->export($idFacturaElement, false);

        // External reference
        if ($this->externalRef !== null) {
            $recordElement->add('sum1:RefExterna', $this->externalRef);
        }

        // Issuer name
        $recordElement->add('sum1:NombreRazonEmisor', $this->issuerName);

        // Flags
        $recordElement->add('sum1:Subsanacion', $this->isCorrection ? 'S' : 'N');
        if ($this->isPriorRejection !== PreviousRejectionType::N) {
            $recordElement->add('sum1:RechazoPrevio', $this->isPriorRejection->value);
        }

        // Invoice type
        $recordElement->add('sum1:TipoFactura', $this->invoiceType->value);

        // Corrective details
        if ($this->correctiveType !== null) {
            $recordElement->add('sum1:TipoRectificativa', $this->correctiveType->value);
        }
        if (count($this->correctedInvoices) > 0) {
            $facturasRectificadasElement = $recordElement->add('sum1:FacturasRectificadas');
            foreach ($this->correctedInvoices as $correctedInvoice) {
                $facturaRectificadaElement = $facturasRectificadasElement->add('sum1:IDFacturaRectificada');
                $correctedInvoice->export($facturaRectificadaElement, false);
            }
        }
        if (count($this->replacedInvoices) > 0) {
            $facturasSustituidasElement = $recordElement->add('sum1:FacturasSustituidas');
            foreach ($this->replacedInvoices as $replacedInvoice) {
                $facturaSustituidaElement = $facturasSustituidasElement->add('sum1:IDFacturaSustituida');
                $replacedInvoice->export($facturaSustituidaElement, false);
            }
        }
        if ($this->correctedBaseAmount !== null && $this->correctedTaxAmount !== null) {
            $importeRectificacionElement = $recordElement->add('sum1:ImporteRectificacion');
            $importeRectificacionElement->add('sum1:BaseRectificada', $this->correctedBaseAmount);
            $importeRectificacionElement->add('sum1:CuotaRectificada', $this->correctedTaxAmount);
            if ($this->correctedSurchargeAmount !== null) {
                $importeRectificacionElement->add('sum1:CuotaRecargoRectificado', $this->correctedSurchargeAmount);
            }
        }

        // Operation date
        if ($this->operationDate !== null) {
            $recordElement->add('sum1:FechaOperacion', $this->operationDate->format('d-m-Y'));
        }

        // Description
        $recordElement->add('sum1:DescripcionOperacion', $this->description);

        // Optional boolean indicators
        if ($this->isSimplifiedArt7273) {
            $recordElement->add('sum1:FacturaSimplificadaArt7273', 'S');
        }
        if ($this->withoutRecipientIdArt61d) {
            $recordElement->add('sum1:FacturaSinIdentifDestinatarioArt61d', 'S');
        }
        if ($this->isMacrodato) {
            $recordElement->add('sum1:Macrodato', 'S');
        }

        // Issuer type and third party
        if ($this->issuedByThirdParty !== null) {
            $recordElement->add('sum1:EmitidaPorTerceroODestinatario', $this->issuedByThirdParty->value);
        }
        if ($this->thirdParty !== null) {
            $terceroElement = $recordElement->add('sum1:Tercero');
            $terceroElement->add('sum1:NombreRazon', $this->thirdParty->name);
            if ($this->thirdParty instanceof FiscalIdentifier) {
                $terceroElement->add('sum1:NIF', $this->thirdParty->nif);
            } else {
                $idOtroElement = $terceroElement->add('sum1:IDOtro');
                $idOtroElement->add('sum1:CodigoPais', $this->thirdParty->country);
                $idOtroElement->add('sum1:IDType', $this->thirdParty->type->value);
                $idOtroElement->add('sum1:ID', $this->thirdParty->value);
            }
        }

        // Recipients
        if (count($this->recipients) > 0) {
            $destinatariosElement = $recordElement->add('sum1:Destinatarios');
            foreach ($this->recipients as $recipient) {
                $destinatarioElement = $destinatariosElement->add('sum1:IDDestinatario');
                $destinatarioElement->add('sum1:NombreRazon', $recipient->name);
                if ($recipient instanceof FiscalIdentifier) {
                    $destinatarioElement->add('sum1:NIF', $recipient->nif);
                } else {
                    $idOtroElement = $destinatarioElement->add('sum1:IDOtro');
                    $idOtroElement->add('sum1:CodigoPais', $recipient->country);
                    $idOtroElement->add('sum1:IDType', $recipient->type->value);
                    $idOtroElement->add('sum1:ID', $recipient->value);
                }
            }
        }

        // Coupon
        if ($this->hasCoupon) {
            $recordElement->add('sum1:Cupon', 'S');
        }

        // Breakdown
        $desgloseElement = $recordElement->add('sum1:Desglose');
        foreach ($this->breakdown as $breakdownDetails) {
            $breakdownDetails->export($desgloseElement);
        }

        // Totals
        $recordElement->add('sum1:CuotaTotal', $this->totalTaxAmount);
        $recordElement->add('sum1:ImporteTotal', $this->totalAmount);

        // Billing agreement fields
        if ($this->billingAgreementNumber !== null) {
            $recordElement->add('sum1:NumRegistroAcuerdoFacturacion', $this->billingAgreementNumber);
        }
        if ($this->systemAgreementId !== null) {
            $recordElement->add('sum1:IdAcuerdoSistemaInformatico', $this->systemAgreementId);
        }
    }
}

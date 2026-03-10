<?php
namespace josemmo\Verifactu\Models\Records;

use josemmo\Verifactu\Exceptions\ImportException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use UXML\UXML;

/**
 * Registro de anulación de una factura
 *
 * @field RegistroAnulacion
 */
class CancellationRecord extends Record {
    /**
     * Referencia externa del registro de anulación
     *
     * @field RefExterna
     */
    #[Assert\Length(max: 60)]
    public ?string $externalRef = null;

    /**
     * Indicador que especifica que se trata de la anulación de un registro que no existe en la AEAT o en el SIF.
     *
     * @field SinRegistroPrevio
     */
    #[Assert\NotNull]
    #[Assert\Type('boolean')]
    public bool $withoutPriorRecord = false;

    /**
     * Indicador de rechazo previo
     *
     * Para remitir un nuevo registro de facturación de anulación subsanado tras haber sido rechazado en su remisión
     * inmediatamente anterior.
     * Es decir, en el último envío que contenía ese registro de facturación de alta rechazado.
     *
     * @field RechazoPrevio
     */
    #[Assert\NotNull]
    #[Assert\Type('boolean')]
    public bool $isPriorRejection = false;

    /**
     * Indicador de quién generó el registro de anulación
     *
     * @field GeneradoPor
     */
    public ?GeneratedByType $generatedBy = null;

    /**
     * Identificación del generador del registro de anulación
     *
     * @field Generador
     */
    #[Assert\Valid]
    public FiscalIdentifier|ForeignFiscalIdentifier|null $generator = null;

    /**
     * @inheritDoc
     */
    protected static function getRecordElementName(): string {
        return 'RegistroAnulacion';
    }

    /**
     * @inheritDoc
     */
    public function calculateHash(): string {
        // NOTE: Values should NOT be escaped as that what the AEAT says ¯\_(ツ)_/¯
        $payload  = 'IDEmisorFacturaAnulada=' . trim($this->invoiceId->issuerId);
        $payload .= '&NumSerieFacturaAnulada=' . trim($this->invoiceId->invoiceNumber);
        $payload .= '&FechaExpedicionFacturaAnulada=' . $this->invoiceId->issueDate->format('d-m-Y');
        $payload .= '&Huella=' . ($this->previousHash ?? '');
        $payload .= '&FechaHoraHusoGenRegistro=' . $this->hashedAt->format('c');
        return strtoupper(hash('sha256', $payload));
    }

    #[Assert\Callback]
    final public function validateGenerator(ExecutionContextInterface $context): void {
        if ($this->generatedBy !== null && $this->generator === null) {
            $context->buildViolation('Generator details are required when generatedBy is set')
                ->atPath('generator')
                ->addViolation();
        }
        if ($this->generatedBy === null && $this->generator !== null) {
            $context->buildViolation('Generated-by type must be set when generator is provided')
                ->atPath('generatedBy')
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

        // Flags
        $withoutPriorRecord = $recordElement->get('sum1:SinRegistroPrevio')?->asText() ?? 'N';
        $isPriorRejection = $recordElement->get('sum1:RechazoPrevio')?->asText() ?? 'N';
        $this->withoutPriorRecord = ($withoutPriorRecord === 'S');
        $this->isPriorRejection = ($isPriorRejection === 'S');

        // Generator
        $rawGeneratedBy = $recordElement->get('sum1:GeneradoPor')?->asText();
        if ($rawGeneratedBy !== null) {
            $generatedBy = GeneratedByType::tryFrom($rawGeneratedBy);
            if ($generatedBy === null) {
                throw new ImportException('Invalid value for <sum1:GeneradoPor /> element');
            }
            $this->generatedBy = $generatedBy;
        }
        $generadorElement = $recordElement->get('sum1:Generador');
        if ($generadorElement !== null) {
            $generadorName = $generadorElement->get('sum1:NombreRazon')?->asText();
            if ($generadorName === null) {
                throw new ImportException('Missing <sum1:NombreRazon /> from <sum1:Generador /> element');
            }
            $generadorNif = $generadorElement->get('sum1:NIF')?->asText();
            if ($generadorNif !== null) {
                $this->generator = new FiscalIdentifier($generadorName, $generadorNif);
            } else {
                $generadorCountry = $generadorElement->get('sum1:IDOtro/sum1:CodigoPais')?->asText();
                if ($generadorCountry === null) {
                    throw new ImportException('Missing <sum1:CodigoPais /> from <sum1:Generador /> element');
                }
                $rawGeneradorType = $generadorElement->get('sum1:IDOtro/sum1:IDType')?->asText();
                if ($rawGeneradorType === null) {
                    throw new ImportException('Missing <sum1:IDType /> from <sum1:Generador /> element');
                }
                $generadorType = ForeignIdType::tryFrom($rawGeneradorType);
                if ($generadorType === null) {
                    throw new ImportException('Invalid value for <sum1:IDType /> from <sum1:Generador /> element');
                }
                $generadorValue = $generadorElement->get('sum1:IDOtro/sum1:ID')?->asText();
                if ($generadorValue === null) {
                    throw new ImportException('Missing <sum1:ID /> from <sum1:Generador /> element');
                }
                $this->generator = new ForeignFiscalIdentifier($generadorName, $generadorCountry, $generadorType, $generadorValue);
            }
        }
    }

    /**
     * @inheritDoc
     */
    protected function exportCustomProperties(UXML $recordElement): void {
        // Invoice ID
        $idFacturaElement = $recordElement->add('sum1:IDFactura');
        $this->invoiceId->export($idFacturaElement, true);

        // External reference
        if ($this->externalRef !== null) {
            $recordElement->add('sum1:RefExterna', $this->externalRef);
        }

        // Flags
        if ($this->withoutPriorRecord) {
            $recordElement->add('sum1:SinRegistroPrevio', 'S');
        }
        if ($this->isPriorRejection) {
            $recordElement->add('sum1:RechazoPrevio', 'S');
        }

        // Generator
        if ($this->generatedBy !== null) {
            $recordElement->add('sum1:GeneradoPor', $this->generatedBy->value);
        }
        if ($this->generator !== null) {
            $generadorElement = $recordElement->add('sum1:Generador');
            $generadorElement->add('sum1:NombreRazon', $this->generator->name);
            if ($this->generator instanceof FiscalIdentifier) {
                $generadorElement->add('sum1:NIF', $this->generator->nif);
            } else {
                $idOtroElement = $generadorElement->add('sum1:IDOtro');
                $idOtroElement->add('sum1:CodigoPais', $this->generator->country);
                $idOtroElement->add('sum1:IDType', $this->generator->type->value);
                $idOtroElement->add('sum1:ID', $this->generator->value);
            }
        }
    }
}

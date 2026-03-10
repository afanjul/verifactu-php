<?php
namespace josemmo\Verifactu\Models;

use josemmo\Verifactu\Exceptions\ImportException;
use josemmo\Verifactu\Models\Records\ForeignIdType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use UXML\UXML;

/**
 * Computer system
 *
 * @field SistemaInformatico
 */
class ComputerSystem extends Model {
    /**
     * Nombre-razón social de la persona o entidad productora
     *
     * @field NombreRazon
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public string $vendorName;

    /**
     * NIF de la persona o entidad productora
     *
     * @field NIF
     */
    public ?string $vendorNif = null;

    /**
     * Código del país del proveedor (solo cuando el proveedor no tiene NIF español)
     *
     * @field IDOtro/CodigoPais
     */
    #[Assert\Length(exactly: 2)]
    public ?string $vendorCountry = null;

    /**
     * Tipo de identificación del proveedor extranjero
     *
     * @field IDOtro/IDType
     */
    public ?ForeignIdType $vendorIdType = null;

    /**
     * Identificación del proveedor extranjero
     *
     * @field IDOtro/ID
     */
    #[Assert\Length(max: 20)]
    public ?string $vendorId = null;

    /**
     * Nombre dado por la persona o entidad productora a su sistema informático de facturación (SIF)
     *
     * @field NombreSistemaInformatico
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    public string $name;

    /**
     * Código identificativo dado por la persona o entidad productora a su sistema informático de facturación (SIF)
     *
     * @field IdSistemaInformatico
     */
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[A-Z0-9]{2}$/', message: 'IdSistemaInformatico must be exactly 2 uppercase letters (excluding Ñ) or digits')]
    public string $id;

    /**
     * Identificación de la versión del sistema informático de facturación (SIF)
     *
     * @field Version
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    public string $version;

    /**
     * Número de instalación del sistema informático de facturación (SIF) utilizado
     *
     * @field NumeroInstalacion
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $installationNumber;

    /**
     * Especifica si solo puede funcionar como "VERI*FACTU" o también puede funcionar como "no VERI*FACTU" (offline)
     *
     * @field TipoUsoPosibleSoloVerifactu
     */
    #[Assert\NotNull]
    #[Assert\Type('boolean')]
    public bool $onlySupportsVerifactu;

    /**
     * Especifica si permite llevar independientemente la facturación de varios obligados tributarios
     *
     * @field TipoUsoPosibleMultiOT
     */
    #[Assert\NotNull]
    #[Assert\Type('boolean')]
    public bool $supportsMultipleTaxpayers;

    /**
     * En el momento de la generación de este registro, está soportando la facturación de más de un obligado tributario
     *
     * @field IndicadorMultiplesOT
     */
    #[Assert\NotNull]
    #[Assert\Type('boolean')]
    public bool $hasMultipleTaxpayers;

    #[Assert\Callback]
    final public function validateVendorIdentifier(ExecutionContextInterface $context): void {
        $hasNif = $this->vendorNif !== null && $this->vendorNif !== '';
        $hasIdOtro = $this->vendorCountry !== null || $this->vendorIdType !== null || $this->vendorId !== null;

        if (!$hasNif && !$hasIdOtro) {
            $context->buildViolation('Either vendorNif or vendorCountry/vendorIdType/vendorId must be set')
                ->atPath('vendorNif')
                ->addViolation();
            return;
        }

        if ($hasNif && $hasIdOtro) {
            $context->buildViolation('Cannot set both vendorNif and foreign vendor identifier (IDOtro)')
                ->atPath('vendorNif')
                ->addViolation();
            return;
        }

        if ($hasNif && strlen($this->vendorNif ?? '') !== 9) {
            $context->buildViolation('vendorNif must be exactly 9 characters')
                ->atPath('vendorNif')
                ->addViolation();
        }

        if ($hasIdOtro) {
            if ($this->vendorCountry === null) {
                $context->buildViolation('vendorCountry is required for foreign vendor identifier')
                    ->atPath('vendorCountry')
                    ->addViolation();
            }
            if ($this->vendorIdType === null) {
                $context->buildViolation('vendorIdType is required for foreign vendor identifier')
                    ->atPath('vendorIdType')
                    ->addViolation();
            }
            if ($this->vendorId === null) {
                $context->buildViolation('vendorId is required for foreign vendor identifier')
                    ->atPath('vendorId')
                    ->addViolation();
            }
        }
    }

    /**
     * Import instance from XML element
     *
     * @param UXML $xml XML element
     *
     * @return self New computer system instance
     *
     * @throws ImportException if failed to parse XML
     */
    public static function fromXml(UXML $xml): self {
        $model = new self();

        // Vendor name
        $vendorName = $xml->get('sum1:NombreRazon')?->asText();
        if ($vendorName === null) {
            throw new ImportException('Missing <sum1:NombreRazon /> element');
        }
        $model->vendorName = $vendorName;

        // Vendor NIF (or IDOtro for foreign vendors)
        $vendorNif = $xml->get('sum1:NIF')?->asText();
        if ($vendorNif !== null) {
            $model->vendorNif = $vendorNif;
        } else {
            $model->vendorCountry = $xml->get('sum1:IDOtro/sum1:CodigoPais')?->asText();
            $rawVendorIdType = $xml->get('sum1:IDOtro/sum1:IDType')?->asText();
            if ($rawVendorIdType !== null) {
                $model->vendorIdType = ForeignIdType::tryFrom($rawVendorIdType);
            }
            $model->vendorId = $xml->get('sum1:IDOtro/sum1:ID')?->asText();
        }

        // Name
        $name = $xml->get('sum1:NombreSistemaInformatico')?->asText();
        if ($name === null) {
            throw new ImportException('Missing <sum1:NombreSistemaInformatico /> element');
        }
        $model->name = $name;

        // ID
        $id = $xml->get('sum1:IdSistemaInformatico')?->asText();
        if ($id === null) {
            throw new ImportException('Missing <sum1:IdSistemaInformatico /> element');
        }
        $model->id = $id;

        // Version
        $version = $xml->get('sum1:Version')?->asText();
        if ($version === null) {
            throw new ImportException('Missing <sum1:Version /> element');
        }
        $model->version = $version;

        // Installation number
        $installationNumber = $xml->get('sum1:NumeroInstalacion')?->asText();
        if ($installationNumber === null) {
            throw new ImportException('Missing <sum1:NumeroInstalacion /> element');
        }
        $model->installationNumber = $installationNumber;

        // Flags
        $onlySupportsVerifactu = $xml->get('sum1:TipoUsoPosibleSoloVerifactu')?->asText() ?? 'N';
        $supportsMultipleTaxpayers = $xml->get('sum1:TipoUsoPosibleMultiOT')?->asText() ?? 'N';
        $hasMultipleTaxpayers = $xml->get('sum1:IndicadorMultiplesOT')?->asText() ?? 'N';
        $model->onlySupportsVerifactu = ($onlySupportsVerifactu === 'S');
        $model->supportsMultipleTaxpayers = ($supportsMultipleTaxpayers === 'S');
        $model->hasMultipleTaxpayers = ($hasMultipleTaxpayers === 'S');

        return $model;
    }

    /**
     * Export model to XML
     *
     * @param UXML $xml XML parent element
     */
    public function export(UXML $xml): void {
        $element = $xml->add('sum1:SistemaInformatico');
        $element->add('sum1:NombreRazon', $this->vendorName);
        if ($this->vendorNif !== null) {
            $element->add('sum1:NIF', $this->vendorNif);
        } else {
            $idOtroElement = $element->add('sum1:IDOtro');
            $idOtroElement->add('sum1:CodigoPais', $this->vendorCountry);
            $idOtroElement->add('sum1:IDType', $this->vendorIdType?->value);
            $idOtroElement->add('sum1:ID', $this->vendorId);
        }
        $element->add('sum1:NombreSistemaInformatico', $this->name);
        $element->add('sum1:IdSistemaInformatico', $this->id);
        $element->add('sum1:Version', $this->version);
        $element->add('sum1:NumeroInstalacion', $this->installationNumber);
        $element->add('sum1:TipoUsoPosibleSoloVerifactu', $this->onlySupportsVerifactu ? 'S' : 'N');
        $element->add('sum1:TipoUsoPosibleMultiOT', $this->supportsMultipleTaxpayers ? 'S' : 'N');
        $element->add('sum1:IndicadorMultiplesOT', $this->hasMultipleTaxpayers ? 'S' : 'N');
    }
}

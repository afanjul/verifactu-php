<?php
namespace josemmo\Verifactu\Models\Responses;

use josemmo\Verifactu\Models\Model;
use josemmo\Verifactu\Models\Records\InvoiceIdentifier;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Response for a record submission
 *
 * @field RespuestaLinea
 */
class ResponseItem extends Model {
    /**
     * Indicador de subsanación
     *
     * @field Subsanacion
     */
    #[Assert\NotNull]
    #[Assert\Type('boolean')]
    public bool $isCorrection = false;

    /**
     * ID de factura
     *
     * @field IDFactura
     */
    #[Assert\NotBlank]
    #[Assert\Valid]
    public InvoiceIdentifier $invoiceId;

    /**
     * Tipo de registro de operación
     *
     * @field TipoOperacion
     */
    #[Assert\NotBlank]
    public RecordType $recordType;

    /**
     * Estado del envío del registro
     *
     * @field EstadoRegistro
     */
    #[Assert\NotBlank]
    public ItemStatus $status;

    /**
     * Código de error
     *
     * @field CodigoErrorRegistro
     */
    public ?string $errorCode = null;

    /**
     * Descripción del error
     *
     * @field DescripcionErrorRegistro
     */
    public ?string $errorDescription = null;

    /**
     * ID de petición del registro duplicado
     *
     * @field RegistroDuplicado/IdPeticionRegistroDuplicado
     */
    public ?string $duplicateRequestId = null;

    /**
     * Estado del registro duplicado
     *
     * @field RegistroDuplicado/EstadoRegistroDuplicado
     */
    public ?DuplicateRecordStatus $duplicateStatus = null;

    /**
     * Código de error del registro duplicado
     *
     * @field RegistroDuplicado/CodigoErrorRegistro
     */
    public ?string $duplicateErrorCode = null;

    /**
     * Descripción del error del registro duplicado
     *
     * @field RegistroDuplicado/DescripcionErrorRegistro
     */
    public ?string $duplicateErrorDescription = null;
}

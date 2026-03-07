<?php
namespace josemmo\Verifactu\Models\Responses;

use DateTimeImmutable;
use josemmo\Verifactu\Models\Model;
use josemmo\Verifactu\Models\Records\InvoiceIdentifier;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Registro individual devuelto en la respuesta de consulta
 *
 * @field RegistroRespuestaConsultaFactuSistemaFacturacion
 */
class QueryResponseItem extends Model {
    /**
     * Identificador de la factura
     *
     * @field IDFactura
     */
    #[Assert\NotBlank]
    #[Assert\Valid]
    public InvoiceIdentifier $invoiceId;

    /**
     * Estado actual del registro en el sistema de la AEAT
     *
     * @field EstadoRegistro/EstadoRegistro
     */
    #[Assert\NotBlank]
    public QueryRecordStatus $status;

    /**
     * Timestamp de la última modificación del registro
     *
     * @field EstadoRegistro/TimestampUltimaModificacion
     */
    public ?DateTimeImmutable $lastModifiedAt = null;

    /**
     * Código del error del registro, en su caso
     *
     * @field EstadoRegistro/CodigoErrorRegistro
     */
    public ?string $errorCode = null;

    /**
     * Descripción del error del registro, en su caso
     *
     * @field EstadoRegistro/DescripcionErrorRegistro
     */
    public ?string $errorDescription = null;
}

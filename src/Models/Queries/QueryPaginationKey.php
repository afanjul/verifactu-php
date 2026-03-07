<?php
namespace josemmo\Verifactu\Models\Queries;

use DateTimeImmutable;
use josemmo\Verifactu\Models\Model;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Clave de paginación para consulta de registros de facturación
 *
 * Permite continuar una consulta que ha devuelto más de 10.000 registros.
 * Se obtiene de la respuesta anterior y se envía en la siguiente petición.
 *
 * @field FiltroConsulta/ClavePaginacion
 */
class QueryPaginationKey extends Model {
    /**
     * Class constructor
     *
     * @param string|null            $issuerId      NIF del obligado a expedir la última factura consultada
     * @param string|null            $invoiceNumber Nº Serie+Nº Factura del último registro consultado
     * @param DateTimeImmutable|null $issueDate     Fecha de expedición del último registro consultado
     */
    public function __construct(
        ?string $issuerId = null,
        ?string $invoiceNumber = null,
        ?DateTimeImmutable $issueDate = null,
    ) {
        if ($issuerId !== null) {
            $this->issuerId = $issuerId;
        }
        if ($invoiceNumber !== null) {
            $this->invoiceNumber = $invoiceNumber;
        }
        if ($issueDate !== null) {
            $this->issueDate = $issueDate;
        }
    }

    /**
     * NIF del obligado a expedir la factura (última consultada)
     *
     * @field IDEmisorFactura
     */
    #[Assert\NotBlank]
    #[Assert\Length(exactly: 9)]
    public string $issuerId;

    /**
     * Nº Serie+Nº Factura que identifica el último registro consultado
     *
     * @field NumSerieFactura
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 60)]
    public string $invoiceNumber;

    /**
     * Fecha de expedición del último registro consultado
     *
     * NOTE: Time part will be ignored.
     *
     * @field FechaExpedicionFactura
     */
    #[Assert\NotBlank]
    public DateTimeImmutable $issueDate;
}

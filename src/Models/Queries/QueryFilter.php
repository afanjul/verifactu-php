<?php
namespace josemmo\Verifactu\Models\Queries;

use DateTimeImmutable;
use josemmo\Verifactu\Models\Model;
use josemmo\Verifactu\Models\Records\FiscalIdentifier;
use josemmo\Verifactu\Models\Records\ForeignFiscalIdentifier;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Filtros de consulta de registros de facturación
 *
 * Permite filtrar los registros presentados en remisión voluntaria por ejercicio,
 * periodo y otros criterios opcionales.
 *
 * NOTE: PeriodoImputacion (year + period) es obligatorio.
 * NOTE: FechaExpedicionFactura y RangoFechaExpedicion son mutuamente excluyentes.
 *
 * @field FiltroConsulta
 */
class QueryFilter extends Model {
    /**
     * Año de la fecha de operación a consultar (de fecha operación o expedición)
     *
     * @field PeriodoImputacion/Ejercicio
     */
    #[Assert\NotBlank]
    #[Assert\Range(min: 2024, max: 9999)]
    public int $year;

    /**
     * Mes de la fecha de operación a consultar (01-12)
     *
     * @field PeriodoImputacion/Periodo
     */
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^(0[1-9]|1[0-2])$/')]
    public string $period;

    /**
     * Nº Serie+Nº Factura que identifica el registro a consultar
     *
     * @field NumSerieFactura
     */
    #[Assert\Length(max: 60)]
    public ?string $invoiceNumber = null;

    /**
     * Contraparte del NIF de la cabecera (con NIF español)
     *
     * Si la consulta la realiza el ObligadoEmision, este campo es el Destinatario.
     * Si la consulta la realiza el Destinatario, este campo es el ObligadoEmision.
     * Usar este campo O $foreignCounterpart, no ambos.
     *
     * @field Contraparte con NIF
     */
    #[Assert\Valid]
    public ?FiscalIdentifier $counterpart = null;

    /**
     * Contraparte del NIF de la cabecera (con identificación extranjera)
     *
     * Usar este campo O $counterpart, no ambos.
     *
     * @field Contraparte con IDOtro
     */
    #[Assert\Valid]
    public ?ForeignFiscalIdentifier $foreignCounterpart = null;

    /**
     * Fecha de expedición exacta del registro de facturación a filtrar
     *
     * Usar este campo O ($issueDateFrom + $issueDateTo), no ambos.
     *
     * @field FechaExpedicionFactura/FechaExpedicionFactura
     */
    public ?DateTimeImmutable $exactIssueDate = null;

    /**
     * Fecha de inicio del rango de expedición a consultar
     *
     * Usar junto con $issueDateTo. Mutuamente excluyente con $exactIssueDate.
     *
     * @field RangoFechaExpedicion/Desde
     */
    public ?DateTimeImmutable $issueDateFrom = null;

    /**
     * Fecha de fin del rango de expedición a consultar
     *
     * Usar junto con $issueDateFrom. Mutuamente excluyente con $exactIssueDate.
     *
     * @field RangoFechaExpedicion/Hasta
     */
    public ?DateTimeImmutable $issueDateTo = null;

    /**
     * Referencia externa libre del registro de facturación
     *
     * @field RefExterna
     */
    #[Assert\Length(max: 60)]
    public ?string $externalRef = null;

    /**
     * Clave de paginación para consultas con más de 10.000 registros
     *
     * Se obtiene de la respuesta anterior cuando IndicadorPaginacion = 'S'.
     *
     * @field ClavePaginacion
     */
    #[Assert\Valid]
    public ?QueryPaginationKey $paginationKey = null;

    #[Assert\Callback]
    final public function validateIssueDateFields(ExecutionContextInterface $context): void {
        $hasExact = $this->exactIssueDate !== null;
        $hasRange = $this->issueDateFrom !== null || $this->issueDateTo !== null;

        if ($hasExact && $hasRange) {
            $context->buildViolation('Fields "exactIssueDate" and "issueDateFrom"/"issueDateTo" are mutually exclusive')
                ->atPath('exactIssueDate')
                ->addViolation();
        }
    }

    #[Assert\Callback]
    final public function validateCounterpartFields(ExecutionContextInterface $context): void {
        if ($this->counterpart !== null && $this->foreignCounterpart !== null) {
            $context->buildViolation('Fields "counterpart" and "foreignCounterpart" are mutually exclusive')
                ->atPath('counterpart')
                ->addViolation();
        }
    }
}

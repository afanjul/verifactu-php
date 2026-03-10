<?php
namespace josemmo\Verifactu\Models\Responses;

use DateTimeImmutable;
use InvalidArgumentException;
use josemmo\Verifactu\Exceptions\AeatException;
use josemmo\Verifactu\Models\Model;
use josemmo\Verifactu\Models\Queries\QueryPaginationKey;
use josemmo\Verifactu\Models\Records\InvoiceIdentifier;
use josemmo\Verifactu\Models\Records\Record;
use josemmo\Verifactu\Services\AeatClient;
use Symfony\Component\Validator\Constraints as Assert;
use UXML\UXML;

/**
 * Response from AEAT query service (ConsultaFactuSistemaFacturacion)
 *
 * @field RespuestaConsultaFactuSistemaFacturacion
 */
class QueryResponse extends Model {
    /** Response XML namespace for query responses */
    public const NS = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/RespuestaConsultaLR.xsd';

    /**
     * Create new instance from XML response
     *
     * @param UXML $xml Raw XML response
     *
     * @return QueryResponse Parsed response
     *
     * @throws AeatException if server returned an error or failed to parse response
     */
    public static function from(UXML $xml): self {
        $nsEnv = AeatClient::NS_SOAPENV;
        $nsRcl = self::NS;
        $nsTik = Record::NS;
        $instance = new self();

        // Handle server errors
        $faultElement = $xml->get("{{$nsEnv}}Body/{{$nsEnv}}Fault/faultstring");
        if ($faultElement !== null) {
            throw new AeatException($faultElement->asText());
        }

        // Get root XML element
        $rootXml = $xml->get("{{$nsEnv}}Body/{{$nsRcl}}RespuestaConsultaFactuSistemaFacturacion");
        if ($rootXml === null) {
            throw new AeatException('Missing <RespuestaConsultaFactuSistemaFacturacion /> element from response');
        }

        // Parse result
        $resultElement = $rootXml->get("{{$nsRcl}}ResultadoConsulta");
        if ($resultElement !== null) {
            $instance->result = QueryResult::from($resultElement->asText());
        }

        // Parse pagination indicator
        $paginationElement = $rootXml->get("{{$nsRcl}}IndicadorPaginacion");
        if ($paginationElement !== null) {
            $instance->hasMorePages = ($paginationElement->asText() === 'S');
        }

        // Parse period
        $yearElement = $rootXml->get("{{$nsRcl}}PeriodoImputacion/{{$nsRcl}}Ejercicio");
        if ($yearElement !== null) {
            $instance->year = (int) $yearElement->asText();
        }

        $periodElement = $rootXml->get("{{$nsRcl}}PeriodoImputacion/{{$nsRcl}}Periodo");
        if ($periodElement !== null) {
            $instance->period = $periodElement->asText();
        }

        // Parse pagination key (only present when hasMorePages = true)
        $paginationKeyElement = $rootXml->get("{{$nsRcl}}ClavePaginacion");
        if ($paginationKeyElement !== null) {
            $issuerIdEl = $paginationKeyElement->get("{{$nsTik}}IDEmisorFactura");
            $invoiceNumberEl = $paginationKeyElement->get("{{$nsTik}}NumSerieFactura");
            $issueDateEl = $paginationKeyElement->get("{{$nsTik}}FechaExpedicionFactura");

            if ($issuerIdEl !== null && $invoiceNumberEl !== null && $issueDateEl !== null) {
                $paginationKey = new QueryPaginationKey();
                $paginationKey->issuerId = $issuerIdEl->asText();
                $paginationKey->invoiceNumber = $invoiceNumberEl->asText();
                $issueDate = DateTimeImmutable::createFromFormat('d-m-Y', $issueDateEl->asText());
                if ($issueDate === false) {
                    throw new AeatException('Invalid pagination key issue date: ' . $issueDateEl->asText());
                }
                $paginationKey->issueDate = $issueDate->setTime(0, 0, 0, 0);
                $instance->paginationKey = $paginationKey;
            }
        }

        // Parse items
        foreach ($rootXml->getAll("{{$nsRcl}}RegistroRespuestaConsultaFactuSistemaFacturacion") as $itemElement) {
            $item = new QueryResponseItem();
            $item->invoiceId = new InvoiceIdentifier();

            // Parse invoice ID
            $idFacturaEl = $itemElement->get("{{$nsRcl}}IDFactura");
            if ($idFacturaEl !== null) {
                $issuerIdEl = $idFacturaEl->get("{{$nsTik}}IDEmisorFactura");
                if ($issuerIdEl !== null) {
                    $item->invoiceId->issuerId = $issuerIdEl->asText();
                }

                $invoiceNumberEl = $idFacturaEl->get("{{$nsTik}}NumSerieFactura");
                if ($invoiceNumberEl !== null) {
                    $item->invoiceId->invoiceNumber = $invoiceNumberEl->asText();
                }

                $issueDateEl = $idFacturaEl->get("{{$nsTik}}FechaExpedicionFactura");
                if ($issueDateEl !== null) {
                    $issueDate = DateTimeImmutable::createFromFormat('d-m-Y', $issueDateEl->asText());
                    if ($issueDate === false) {
                        throw new AeatException('Invalid invoice issue date: ' . $issueDateEl->asText());
                    }
                    $item->invoiceId->issueDate = $issueDate->setTime(0, 0, 0, 0);
                }
            }

            // Parse record status block
            $estadoEl = $itemElement->get("{{$nsRcl}}EstadoRegistro");
            if ($estadoEl !== null) {
                $statusEl = $estadoEl->get("{{$nsRcl}}EstadoRegistro");
                if ($statusEl !== null) {
                    $item->status = QueryRecordStatus::from($statusEl->asText());
                }

                $timestampEl = $estadoEl->get("{{$nsRcl}}TimestampUltimaModificacion");
                if ($timestampEl !== null) {
                    try {
                        $lastModified = new DateTimeImmutable($timestampEl->asText());
                        $item->lastModifiedAt = $lastModified;
                    } catch (InvalidArgumentException) {
                        // Non-critical, skip
                    }
                }

                $errorCodeEl = $estadoEl->get("{{$nsRcl}}CodigoErrorRegistro");
                if ($errorCodeEl !== null) {
                    $item->errorCode = $errorCodeEl->asText();
                }

                $errorDescEl = $estadoEl->get("{{$nsRcl}}DescripcionErrorRegistro");
                if ($errorDescEl !== null) {
                    $item->errorDescription = $errorDescEl->asText();
                }
            }

            $instance->items[] = $item;
        }

        // Validate and return
        $instance->validate();
        return $instance;
    }

    /**
     * Resultado global de la consulta
     *
     * @field ResultadoConsulta
     */
    #[Assert\NotBlank]
    public ?QueryResult $result = null;

    /**
     * Indica si existen más registros (consulta paginada)
     *
     * Si es true, usar paginationKey para obtener la siguiente página.
     *
     * @field IndicadorPaginacion
     */
    #[Assert\NotNull]
    #[Assert\Type('boolean')]
    public bool $hasMorePages = false;

    /**
     * Ejercicio consultado
     *
     * @field PeriodoImputacion/Ejercicio
     */
    public ?int $year = null;

    /**
     * Periodo consultado
     *
     * @field PeriodoImputacion/Periodo
     */
    public ?string $period = null;

    /**
     * Clave de paginación para continuar la consulta
     *
     * Solo presente si hasMorePages = true.
     *
     * @field ClavePaginacion
     */
    #[Assert\Valid]
    public ?QueryPaginationKey $paginationKey = null;

    /**
     * Registros de facturación devueltos por la consulta
     *
     * @var QueryResponseItem[]
     *
     * @field RegistroRespuestaConsultaFactuSistemaFacturacion
     */
    #[Assert\Valid]
    public array $items = [];
}

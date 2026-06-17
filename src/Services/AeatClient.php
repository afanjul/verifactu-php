<?php
namespace josemmo\Verifactu\Services;

use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use InvalidArgumentException;
use josemmo\Verifactu\Exceptions\AeatException;
use josemmo\Verifactu\Models\ComputerSystem;
use josemmo\Verifactu\Models\Queries\QueryFilter;
use josemmo\Verifactu\Models\Records\CancellationRecord;
use josemmo\Verifactu\Models\Records\FiscalIdentifier;
use josemmo\Verifactu\Models\Records\Record;
use josemmo\Verifactu\Models\Records\RegistrationRecord;
use josemmo\Verifactu\Models\Responses\AeatRequest;
use josemmo\Verifactu\Models\Responses\AeatResponse;
use josemmo\Verifactu\Models\Responses\AeatSubmissionResult;
use josemmo\Verifactu\Models\Responses\QueryResponse;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use SensitiveParameter;
use Throwable;
use UXML\UXML;

/**
 * Main consumer-facing service for communicating with the AEAT VERI*FACTU SOAP endpoints.
 *
 * Typical usage flow:
 * 1. Build and validate a `ComputerSystem` and the relevant record or query model.
 * 2. Instantiate this client with the taxpayer identity.
 * 3. Configure certificate and environment.
 * 4. Call `send()` or `query()` and wait on the returned promise.
 */
class AeatClient {
    /** SOAP envelope XML namespace */
    public const NS_SOAPENV = 'http://schemas.xmlsoap.org/soap/envelope/';
    /** Submission XML namespace (SuministroLR) */
    public const NS_AEAT = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd';
    /** Query XML namespace (ConsultaLR) */
    public const NS_AEAT_CONSULTA = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/ConsultaLR.xsd';
    /** Current schema version used for IDVersion fields */
    public const SCHEMA_VERSION = '1.0';
    /** Default connect timeout (seconds) for the internally created HTTP client */
    public const DEFAULT_CONNECT_TIMEOUT = 10;
    /** Default total timeout (seconds) for the internally created HTTP client */
    public const DEFAULT_TIMEOUT = 60;

    private readonly ComputerSystem $system;
    private readonly FiscalIdentifier $taxpayer;
    private readonly Client $client;
    private ?string $certificatePath = null;
    private ?string $certificatePassword = null;
    private ?FiscalIdentifier $representative = null;
    private ?DateTimeImmutable $voluntaryRemissionEndDate = null;
    private bool $isVoluntaryRemissionAffectedByIncident = false;
    private ?string $requirementReference = null;
    private bool $isLastRequirementSubmission = false;
    private bool $isProduction = true;
    private bool $isEntitySeal = false;

    /**
     * Class constructor.
     *
     * When `$httpClient` is omitted, a new Guzzle client is built with the
     * given `$connectTimeout` and `$timeout` so that the library never blocks
     * indefinitely on AEAT traffic. When `$httpClient` is provided, those
     * timeout arguments are ignored and the injected client's own options
     * prevail (this keeps backwards compatibility for callers that already
     * build their own Guzzle client).
     *
     * @param ComputerSystem   $system         Computer system details
     * @param FiscalIdentifier $taxpayer       Taxpayer details (party that issues the invoices)
     * @param Client|null      $httpClient     Custom HTTP client, leave empty to create a new one
     * @param int              $connectTimeout Seconds to wait while establishing the TCP connection,
     *                                         only used when `$httpClient` is not provided.
     *                                         Use `0` to disable. Defaults to a 10s ceiling.
     * @param int              $timeout        Seconds to wait for a full response, only used when
     *                                         `$httpClient` is not provided. Use `0` to disable.
     *                                         Defaults to a 60s ceiling.
     */
    public function __construct(
        ComputerSystem $system,
        FiscalIdentifier $taxpayer,
        ?Client $httpClient = null,
        int $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT,
        int $timeout = self::DEFAULT_TIMEOUT,
    ) {
        $this->system = $system;
        $this->taxpayer = $taxpayer;
        $this->client = $httpClient ?? new Client([
            'connect_timeout' => $connectTimeout,
            'timeout'         => $timeout,
        ]);
    }

    /**
     * Set the client certificate used for AEAT communication.
     *
     * NOTE: The certificate path must have the ".p12" extension to be recognized as a PFX bundle.
     *
     * @param string      $certificatePath     Path to encrypted PEM certificate or PKCS#12 (PFX) bundle
     * @param string|null $certificatePassword Certificate password or `null` for none
     *
     * @return $this This instance
     */
    public function setCertificate(
        #[SensitiveParameter] string $certificatePath,
        #[SensitiveParameter] ?string $certificatePassword = null,
    ): static {
        $this->certificatePath = $certificatePath;
        $this->certificatePassword = $certificatePassword;
        return $this;
    }

    /**
     * Set the representative that sends on behalf of the taxpayer.
     *
     * NOTE: Requires the represented fiscal entity to fill the "GENERALLEY58" form at AEAT.
     *
     * @param FiscalIdentifier|null $representative Representative details (party that sends the invoices)
     *
     * @return $this This instance
     */
    public function setRepresentative(?FiscalIdentifier $representative): static {
        $this->representative = $representative;
        return $this;
    }

    /**
     * Set the voluntary-remission end date to be included in future submissions.
     *
     * This value affects subsequent `send()` calls. It does not trigger a
     * standalone request on its own.
     *
     * @param DateTimeImmutable|null $endDate              End date (time part will be ignored) or `null` to clear
     * @param bool                   $isAffectedByIncident Whether voluntary remission was at some point affected by a technical incident
     *
     * @return $this This instance
     */
    public function setVoluntaryRemissionEndDate(?DateTimeImmutable $endDate, bool $isAffectedByIncident = false): static {
        $this->voluntaryRemissionEndDate = $endDate;
        $this->isVoluntaryRemissionAffectedByIncident = $isAffectedByIncident;
        return $this;
    }

    /**
     * Set the AEAT requirement reference for requirement-based submissions.
     *
     * Mandatory in case a of a non-voluntary remission upon request by the AEAT ("remisión por requerimiento").
     * Otherwise must be unset.
     *
     * @param string|null $requirementReference        Requirement reference or `null` to clear
     * @param boolean     $isLastRequirementSubmission Whether there are no more records to submit after this remission
     *
     * @return $this This instance
     */
    public function setRequirementReference(?string $requirementReference, bool $isLastRequirementSubmission = false): static {
        $this->requirementReference = $requirementReference;
        $this->isLastRequirementSubmission = $isLastRequirementSubmission;
        return $this;
    }

    /**
     * Set the target AEAT environment.
     *
     * @param bool $production Pass `true` for production, `false` for testing
     *
     * @return $this This instance
     */
    public function setProduction(bool $production): static {
        $this->isProduction = $production;
        return $this;
    }

    /**
     * Select whether the configured certificate is an entity-seal certificate.
     *
     * @param bool $entitySeal Pass `true` for entity seal certificate, `false` for regular certificate
     *
     * @return $this This instance
     */
    public function setEntitySeal(bool $entitySeal): static {
        $this->isEntitySeal = $entitySeal;
        return $this;
    }

    /**
     * Sleep for the wait time requested by AEAT in a previous submission response.
     *
     * The AEAT server may require the SIF to wait a certain number of seconds
     * before sending the next submission. Ignoring this may cause rejections.
     *
     * @param AeatResponse $response Response from a previous send() call
     *
     * @return $this This instance
     */
    public function waitIfNeeded(AeatResponse $response): static {
        if ($response->waitSeconds !== null && $response->waitSeconds > 0) {
            sleep($response->waitSeconds);
        }
        return $this;
    }

    /**
     * Send a batch of invoice records to AEAT.
     *
     * Records should be fully populated and validated before calling this
     * method. The accepted batch size is 1 to 1000 records.
     *
     * @param (RegistrationRecord|CancellationRecord)[] $records Invoicing records
     *
     * @return PromiseInterface<AeatSubmissionResult> Response from service
     *
     * @throws AeatException if AEAT server returned an error or request sending failed
     */
    public function send(array $records): PromiseInterface { /** @phpstan-ignore generics.notGeneric */
        if (count($records) < 1 || count($records) > 1000) {
            throw new InvalidArgumentException('Records count must be between 1 and 1000');
        }

        // Build initial request
        $xml = UXML::newInstance('soapenv:Envelope', null, [
            'xmlns:soapenv' => self::NS_SOAPENV,
            'xmlns:sum' => self::NS_AEAT,
            'xmlns:sum1' => Record::NS,
        ]);
        $xml->add('soapenv:Header');
        $baseElement = $xml->add('soapenv:Body')->add('sum:RegFactuSistemaFacturacion');

        // Add header
        $cabeceraElement = $baseElement->add('sum:Cabecera');
        $obligadoEmisionElement = $cabeceraElement->add('sum1:ObligadoEmision');
        $obligadoEmisionElement->add('sum1:NombreRazon', $this->taxpayer->name);
        $obligadoEmisionElement->add('sum1:NIF', $this->taxpayer->nif);
        if ($this->representative !== null) {
            $representanteElement = $cabeceraElement->add('sum1:Representante');
            $representanteElement->add('sum1:NombreRazon', $this->representative->name);
            $representanteElement->add('sum1:NIF', $this->representative->nif);
        }
        if ($this->voluntaryRemissionEndDate !== null) {
            $remisionVoluntariaElement = $cabeceraElement->add('sum1:RemisionVoluntaria');
            $remisionVoluntariaElement->add('sum1:FechaFinVeriFactu', $this->voluntaryRemissionEndDate->format('d-m-Y'));
            $remisionVoluntariaElement->add('sum1:Incidencia', $this->isVoluntaryRemissionAffectedByIncident ? 'S' : 'N');
        }
        if ($this->requirementReference !== null) {
            $remisionRequerimientoElement = $cabeceraElement->add('sum1:RemisionRequerimiento');
            $remisionRequerimientoElement->add('sum1:RefRequerimiento', $this->requirementReference);
            $remisionRequerimientoElement->add('sum1:FinRequerimiento', $this->isLastRequirementSubmission ? 'S' : 'N');
        }

        // Add registration records
        foreach ($records as $record) {
            $record->export($baseElement->add('sum:RegistroFactura'), $this->system);
        }

        // Send request
        $requestXml = $xml->asXML();
        $options = [
            'base_uri' => $this->getBaseUri(),
            'http_errors' => false,
            'headers' => [
                'Content-Type' => 'text/xml',
                'User-Agent' => "Mozilla/5.0 (compatible; {$this->system->name}/{$this->system->version})",
            ],
            'body' => $requestXml,
        ];
        if ($this->certificatePath !== null) {
            $options['cert'] = ($this->certificatePassword === null) ?
                $this->certificatePath :
                [$this->certificatePath, $this->certificatePassword];
        }
        try {
            $responsePromise = $this->client->postAsync('/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP', $options);
        } catch (Throwable $e) {
            throw new AeatException($e->getMessage(), (int) $e->getCode(), $e, requestXml: $requestXml);
        }

        // Parse and return response
        return $responsePromise
            ->then(fn (ResponseInterface $response): string => $response->getBody()->getContents())
            ->then(function (string $responseXml) use ($requestXml): AeatSubmissionResult {
                try {
                    return new AeatSubmissionResult(
                        new AeatRequest($requestXml),
                        AeatResponse::fromXml($responseXml),
                    );
                } catch (AeatException $e) {
                    throw new AeatException(
                        $e->getMessage(),
                        $e->getCode(),
                        $e,
                        requestXml: $requestXml,
                        responseXml: $responseXml,
                    );
                }
            }, fn (Throwable $e) => throw new AeatException(
                $e->getMessage(),
                (int) $e->getCode(),
                $e,
                requestXml: $requestXml,
            ));
    }

    /**
     * Query previously submitted invoice records in voluntary-remission mode.
     *
     * The `QueryFilter` should be fully populated and validated before use.
     *
     * @param QueryFilter $filter             Query filter parameters
     * @param bool        $showIssuerName     Include NombreRazonEmisor field in response (increases response time for recipient queries)
     * @param bool        $showComputerSystem Include SistemaInformatico block in response (must be false for recipient queries)
     *
     * @return PromiseInterface Response from service
     *
     * @throws AeatException            if AEAT server returned an error
     * @throws ClientExceptionInterface if request sending failed
     */
    public function query(
        QueryFilter $filter,
        bool $showIssuerName = false,
        bool $showComputerSystem = false,
    ): PromiseInterface {
        // Build initial request
        $xml = UXML::newInstance('soapenv:Envelope', null, [
            'xmlns:soapenv' => self::NS_SOAPENV,
            'xmlns:con' => self::NS_AEAT_CONSULTA,
            'xmlns:sum1' => Record::NS,
        ]);
        $xml->add('soapenv:Header');
        $baseElement = $xml->add('soapenv:Body')->add('con:ConsultaFactuSistemaFacturacion');

        // Add header
        $cabeceraElement = $baseElement->add('con:Cabecera');
        $cabeceraElement->add('sum1:IDVersion', self::SCHEMA_VERSION);
        $obligadoEmisionElement = $cabeceraElement->add('sum1:ObligadoEmision');
        $obligadoEmisionElement->add('sum1:NombreRazon', $this->taxpayer->name);
        $obligadoEmisionElement->add('sum1:NIF', $this->taxpayer->nif);
        if ($this->representative !== null) {
            $cabeceraElement->add('sum1:IndicadorRepresentante', 'S');
        }

        // Add filter
        $filtroElement = $baseElement->add('con:FiltroConsulta');
        $periodoElement = $filtroElement->add('con:PeriodoImputacion');
        $periodoElement->add('sum1:Ejercicio', (string) $filter->year);
        $periodoElement->add('sum1:Periodo', $filter->period);

        if ($filter->invoiceNumber !== null) {
            $filtroElement->add('con:NumSerieFactura', $filter->invoiceNumber);
        }

        if ($filter->counterpart !== null) {
            $contraparteElement = $filtroElement->add('con:Contraparte');
            $contraparteElement->add('sum1:NombreRazon', $filter->counterpart->name);
            $contraparteElement->add('sum1:NIF', $filter->counterpart->nif);
        } elseif ($filter->foreignCounterpart !== null) {
            $fc = $filter->foreignCounterpart;
            $contraparteElement = $filtroElement->add('con:Contraparte');
            $contraparteElement->add('sum1:NombreRazon', $fc->name);
            $idOtroElement = $contraparteElement->add('sum1:IDOtro');
            $idOtroElement->add('sum1:CodigoPais', $fc->country);
            $idOtroElement->add('sum1:IDType', $fc->type->value);
            $idOtroElement->add('sum1:ID', $fc->value);
        }

        if ($filter->exactIssueDate !== null) {
            $fechaExpedicionElement = $filtroElement->add('con:FechaExpedicionFactura');
            $fechaExpedicionElement->add('sum1:FechaExpedicionFactura', $filter->exactIssueDate->format('d-m-Y'));
        } elseif ($filter->issueDateFrom !== null || $filter->issueDateTo !== null) {
            $fechaExpedicionElement = $filtroElement->add('con:FechaExpedicionFactura');
            $rangoElement = $fechaExpedicionElement->add('sum1:RangoFechaExpedicion');
            if ($filter->issueDateFrom !== null) {
                $rangoElement->add('sum1:Desde', $filter->issueDateFrom->format('d-m-Y'));
            }
            if ($filter->issueDateTo !== null) {
                $rangoElement->add('sum1:Hasta', $filter->issueDateTo->format('d-m-Y'));
            }
        }

        if ($filter->externalRef !== null) {
            $filtroElement->add('con:RefExterna', $filter->externalRef);
        }

        if ($filter->paginationKey !== null) {
            $pk = $filter->paginationKey;
            $claveElement = $filtroElement->add('con:ClavePaginacion');
            $claveElement->add('sum1:IDEmisorFactura', $pk->issuerId);
            $claveElement->add('sum1:NumSerieFactura', $pk->invoiceNumber);
            $claveElement->add('sum1:FechaExpedicionFactura', $pk->issueDate->format('d-m-Y'));
        }

        // Add optional response flags
        if ($showIssuerName || $showComputerSystem) {
            $datosAdicionalesElement = $baseElement->add('con:DatosAdicionalesRespuesta');
            if ($showIssuerName) {
                $datosAdicionalesElement->add('con:MostrarNombreRazonEmisor', 'S');
            }
            if ($showComputerSystem) {
                $datosAdicionalesElement->add('con:MostrarSistemaInformatico', 'S');
            }
        }

        // Send request
        $options = [
            'base_uri' => $this->getBaseUri(),
            'http_errors' => false,
            'headers' => [
                'Content-Type' => 'text/xml',
                'User-Agent' => "Mozilla/5.0 (compatible; {$this->system->name}/{$this->system->version})",
            ],
            'body' => $xml->asXML(),
        ];
        if ($this->certificatePath !== null) {
            $options['cert'] = ($this->certificatePassword === null) ?
                $this->certificatePath :
                [$this->certificatePath, $this->certificatePassword];
        }
        $responsePromise = $this->client->postAsync('/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP', $options);

        // Parse and return response
        return $responsePromise
            ->then(fn (ResponseInterface $response): string => $response->getBody()->getContents())
            ->then(function (string $response): UXML {
                try {
                    return UXML::fromString($response);
                } catch (InvalidArgumentException $e) {
                    throw new AeatException('Failed to parse XML response', previous: $e);
                }
            })
            ->then(fn (UXML $xml): QueryResponse => QueryResponse::from($xml));
    }

    /**
     * Get base URI of web service
     *
     * @return string Base URI
     */
    private function getBaseUri(): string {
        if ($this->isEntitySeal) {
            return $this->isProduction ? 'https://www10.agenciatributaria.gob.es' : 'https://prewww10.aeat.es';
        }
        return $this->isProduction ? 'https://www1.agenciatributaria.gob.es' : 'https://prewww1.aeat.es';
    }
}

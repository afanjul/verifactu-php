<?php
namespace josemmo\Verifactu\Tests\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use josemmo\Verifactu\Exceptions\AeatException;
use josemmo\Verifactu\Models\ComputerSystem;
use josemmo\Verifactu\Models\Queries\QueryFilter;
use josemmo\Verifactu\Models\Records\FiscalIdentifier;
use josemmo\Verifactu\Models\Responses\QueryResponse;
use josemmo\Verifactu\Models\Responses\QueryResult;
use josemmo\Verifactu\Services\AeatClient;
use PHPUnit\Framework\TestCase;

final class AeatClientQueryTest extends TestCase {
    /**
     * Get mocked AEAT client
     *
     * @param Response $response Mocked response
     *
     * @return AeatClient AEAT client instance
     */
    private function getMockedClient(Response $response): AeatClient {
        $mock = new MockHandler([$response]);
        $httpClient = new Client([
            'handler' => HandlerStack::create($mock),
        ]);

        $system = new ComputerSystem();
        $system->vendorName = 'Perico de los Palotes, S.A.';
        $system->vendorNif = 'A00000000';
        $system->name = 'Test SIF';
        $system->id = 'XX';
        $system->version = '0.0.1';
        $system->installationNumber = 'ABC0123';
        $system->onlySupportsVerifactu = true;
        $system->supportsMultipleTaxpayers = true;
        $system->hasMultipleTaxpayers = false;
        $system->validate();

        $taxpayer = new FiscalIdentifier('Perico de los Palotes, S.A.', 'A00000000');

        return new AeatClient($system, $taxpayer, $httpClient);
    }

    /**
     * Get a minimal valid query filter
     *
     * @return QueryFilter
     */
    private function getFilter(): QueryFilter {
        $filter = new QueryFilter();
        $filter->year = 2025;
        $filter->period = '10';
        return $filter;
    }

    public function testQueryThrowsExceptionForMalformedXmlResponse(): void {
        $this->expectException(AeatException::class);
        $this->expectExceptionMessage('Failed to parse XML response');
        $client = $this->getMockedClient(new Response(200, [], '<element>Malformed XML</notClosingElement>'));
        $client->query($this->getFilter())->wait();
    }

    public function testQueryThrowsExceptionForUnexpectedXmlResponse(): void {
        $this->expectException(AeatException::class);
        $this->expectExceptionMessage('Missing <RespuestaConsultaFactuSistemaFacturacion />');
        $client = $this->getMockedClient(new Response(401, [], '<html><body>Unauthorized</body></html>'));
        $client->query($this->getFilter())->wait();
    }

    public function testQueryReturnsQueryResponse(): void {
        $responseBody = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <env:Envelope
            xmlns:env="http://schemas.xmlsoap.org/soap/envelope/"
            xmlns:rcl="https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/RespuestaConsultaLR.xsd"
            xmlns:tik="https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd">
            <env:Header/>
            <env:Body>
                <rcl:RespuestaConsultaFactuSistemaFacturacion
                    xmlns:rcl="https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/RespuestaConsultaLR.xsd"
                    xmlns:tik="https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd">
                    <rcl:Cabecera>
                        <rcl:IDVersion>1.0</rcl:IDVersion>
                        <rcl:ObligadoEmision>
                            <tik:NombreRazon>Perico de los Palotes, S.A.</tik:NombreRazon>
                            <tik:NIF>A00000000</tik:NIF>
                        </rcl:ObligadoEmision>
                    </rcl:Cabecera>
                    <rcl:PeriodoImputacion>
                        <rcl:Ejercicio>2025</rcl:Ejercicio>
                        <rcl:Periodo>10</rcl:Periodo>
                    </rcl:PeriodoImputacion>
                    <rcl:IndicadorPaginacion>N</rcl:IndicadorPaginacion>
                    <rcl:ResultadoConsulta>SinDatos</rcl:ResultadoConsulta>
                </rcl:RespuestaConsultaFactuSistemaFacturacion>
            </env:Body>
        </env:Envelope>
        XML;

        $client = $this->getMockedClient(new Response(200, [], $responseBody));
        $response = $client->query($this->getFilter())->wait();
        $this->assertInstanceOf(QueryResponse::class, $response);

        $this->assertEquals(QueryResult::WithoutData, $response->result);
        $this->assertFalse($response->hasMorePages);
        $this->assertEquals(2025, $response->year);
        $this->assertEquals('10', $response->period);
        $this->assertCount(0, $response->items);
    }
}

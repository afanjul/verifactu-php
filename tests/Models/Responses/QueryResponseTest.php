<?php
namespace josemmo\Verifactu\Tests\Models\Responses;

use josemmo\Verifactu\Exceptions\AeatException;
use josemmo\Verifactu\Models\Responses\QueryRecordStatus;
use josemmo\Verifactu\Models\Responses\QueryResponse;
use josemmo\Verifactu\Models\Responses\QueryResult;
use PHPUnit\Framework\TestCase;
use UXML\UXML;

final class QueryResponseTest extends TestCase {
    private function buildEnvelope(string $body): UXML {
        return UXML::fromString(<<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <env:Envelope
            xmlns:env="http://schemas.xmlsoap.org/soap/envelope/"
            xmlns:rcl="https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/RespuestaConsultaLR.xsd"
            xmlns:tik="https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd">
            <env:Header/>
            <env:Body>
                {$body}
            </env:Body>
        </env:Envelope>
        XML);
    }

    public function testParsesQueryResponseWithData(): void {
        $xml = $this->buildEnvelope(<<<XML
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
            <rcl:ResultadoConsulta>ConDatos</rcl:ResultadoConsulta>
            <rcl:RegistroRespuestaConsultaFactuSistemaFacturacion>
                <rcl:IDFactura>
                    <tik:IDEmisorFactura>A00000000</tik:IDEmisorFactura>
                    <tik:NumSerieFactura>TEST-202510-001</tik:NumSerieFactura>
                    <tik:FechaExpedicionFactura>01-10-2025</tik:FechaExpedicionFactura>
                </rcl:IDFactura>
                <rcl:DatosRegistroFacturacion>
                    <rcl:DescripcionOperacion>Venta de productos</rcl:DescripcionOperacion>
                </rcl:DatosRegistroFacturacion>
                <rcl:EstadoRegistro>
                    <rcl:TimestampUltimaModificacion>2025-10-15T10:30:00+02:00</rcl:TimestampUltimaModificacion>
                    <rcl:EstadoRegistro>Correcto</rcl:EstadoRegistro>
                </rcl:EstadoRegistro>
            </rcl:RegistroRespuestaConsultaFactuSistemaFacturacion>
            <rcl:RegistroRespuestaConsultaFactuSistemaFacturacion>
                <rcl:IDFactura>
                    <tik:IDEmisorFactura>A00000000</tik:IDEmisorFactura>
                    <tik:NumSerieFactura>TEST-202510-002</tik:NumSerieFactura>
                    <tik:FechaExpedicionFactura>05-10-2025</tik:FechaExpedicionFactura>
                </rcl:IDFactura>
                <rcl:DatosRegistroFacturacion>
                    <rcl:DescripcionOperacion>Servicio de consultoría</rcl:DescripcionOperacion>
                </rcl:DatosRegistroFacturacion>
                <rcl:EstadoRegistro>
                    <rcl:TimestampUltimaModificacion>2025-10-16T08:00:00+02:00</rcl:TimestampUltimaModificacion>
                    <rcl:EstadoRegistro>AceptadoConErrores</rcl:EstadoRegistro>
                    <rcl:CodigoErrorRegistro>3006</rcl:CodigoErrorRegistro>
                    <rcl:DescripcionErrorRegistro>Error en la huella del registro.</rcl:DescripcionErrorRegistro>
                </rcl:EstadoRegistro>
            </rcl:RegistroRespuestaConsultaFactuSistemaFacturacion>
        </rcl:RespuestaConsultaFactuSistemaFacturacion>
        XML);

        $response = QueryResponse::from($xml);

        $this->assertEquals(QueryResult::WithData, $response->result);
        $this->assertFalse($response->hasMorePages);
        $this->assertEquals(2025, $response->year);
        $this->assertEquals('10', $response->period);
        $this->assertNull($response->paginationKey);
        $this->assertCount(2, $response->items);

        // First item
        $this->assertEquals('A00000000', $response->items[0]->invoiceId->issuerId);
        $this->assertEquals('TEST-202510-001', $response->items[0]->invoiceId->invoiceNumber);
        $this->assertEquals('2025-10-01', $response->items[0]->invoiceId->issueDate->format('Y-m-d'));
        $this->assertEquals(QueryRecordStatus::Correct, $response->items[0]->status);
        $this->assertNotNull($response->items[0]->lastModifiedAt);
        $this->assertNull($response->items[0]->errorCode);
        $this->assertNull($response->items[0]->errorDescription);

        // Second item (with errors)
        $this->assertEquals('TEST-202510-002', $response->items[1]->invoiceId->invoiceNumber);
        $this->assertEquals(QueryRecordStatus::AcceptedWithErrors, $response->items[1]->status);
        $this->assertEquals('3006', $response->items[1]->errorCode);
        $this->assertEquals('Error en la huella del registro.', $response->items[1]->errorDescription);
    }

    public function testParsesQueryResponseWithoutData(): void {
        $xml = $this->buildEnvelope(<<<XML
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
                <rcl:Ejercicio>2024</rcl:Ejercicio>
                <rcl:Periodo>01</rcl:Periodo>
            </rcl:PeriodoImputacion>
            <rcl:IndicadorPaginacion>N</rcl:IndicadorPaginacion>
            <rcl:ResultadoConsulta>SinDatos</rcl:ResultadoConsulta>
        </rcl:RespuestaConsultaFactuSistemaFacturacion>
        XML);

        $response = QueryResponse::from($xml);

        $this->assertEquals(QueryResult::WithoutData, $response->result);
        $this->assertFalse($response->hasMorePages);
        $this->assertEquals(2024, $response->year);
        $this->assertEquals('01', $response->period);
        $this->assertCount(0, $response->items);
        $this->assertNull($response->paginationKey);
    }

    public function testParsesPaginatedQueryResponse(): void {
        $xml = $this->buildEnvelope(<<<XML
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
                <rcl:Periodo>06</rcl:Periodo>
            </rcl:PeriodoImputacion>
            <rcl:IndicadorPaginacion>S</rcl:IndicadorPaginacion>
            <rcl:ResultadoConsulta>ConDatos</rcl:ResultadoConsulta>
            <rcl:RegistroRespuestaConsultaFactuSistemaFacturacion>
                <rcl:IDFactura>
                    <tik:IDEmisorFactura>A00000000</tik:IDEmisorFactura>
                    <tik:NumSerieFactura>TEST-202506-999</tik:NumSerieFactura>
                    <tik:FechaExpedicionFactura>30-06-2025</tik:FechaExpedicionFactura>
                </rcl:IDFactura>
                <rcl:DatosRegistroFacturacion/>
                <rcl:EstadoRegistro>
                    <rcl:TimestampUltimaModificacion>2025-07-01T09:00:00+02:00</rcl:TimestampUltimaModificacion>
                    <rcl:EstadoRegistro>Anulado</rcl:EstadoRegistro>
                </rcl:EstadoRegistro>
            </rcl:RegistroRespuestaConsultaFactuSistemaFacturacion>
            <rcl:ClavePaginacion>
                <tik:IDEmisorFactura>A00000000</tik:IDEmisorFactura>
                <tik:NumSerieFactura>TEST-202506-999</tik:NumSerieFactura>
                <tik:FechaExpedicionFactura>30-06-2025</tik:FechaExpedicionFactura>
            </rcl:ClavePaginacion>
        </rcl:RespuestaConsultaFactuSistemaFacturacion>
        XML);

        $response = QueryResponse::from($xml);

        $this->assertEquals(QueryResult::WithData, $response->result);
        $this->assertTrue($response->hasMorePages);
        $this->assertCount(1, $response->items);
        $this->assertEquals(QueryRecordStatus::Cancelled, $response->items[0]->status);

        // Pagination key
        $this->assertNotNull($response->paginationKey);
        $this->assertEquals('A00000000', $response->paginationKey->issuerId);
        $this->assertEquals('TEST-202506-999', $response->paginationKey->invoiceNumber);
        $this->assertEquals('2025-06-30', $response->paginationKey->issueDate->format('Y-m-d'));
    }

    public function testHandlesSoapFaultOnQuery(): void {
        $xml = UXML::fromString(<<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <env:Envelope xmlns:env="http://schemas.xmlsoap.org/soap/envelope/">
            <env:Body>
                <env:Fault>
                    <faultcode>env:Client</faultcode>
                    <faultstring>Codigo[4104].El NIF del titular en la cabecera no está identificado.</faultstring>
                </env:Fault>
            </env:Body>
        </env:Envelope>
        XML);

        $this->expectException(AeatException::class);
        $this->expectExceptionMessage('Codigo[4104]');
        QueryResponse::from($xml);
    }

    public function testThrowsExceptionForMissingRootElement(): void {
        $xml = UXML::fromString(<<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <env:Envelope xmlns:env="http://schemas.xmlsoap.org/soap/envelope/">
            <env:Body>
                <html><body>Unauthorized</body></html>
            </env:Body>
        </env:Envelope>
        XML);

        $this->expectException(AeatException::class);
        $this->expectExceptionMessage('Missing <RespuestaConsultaFactuSistemaFacturacion />');
        QueryResponse::from($xml);
    }
}

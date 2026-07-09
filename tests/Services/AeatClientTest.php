<?php
namespace josemmo\Verifactu\Tests\Services;

use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use josemmo\Verifactu\Exceptions\AeatException;
use josemmo\Verifactu\Models\ComputerSystem;
use josemmo\Verifactu\Models\Records\CancellationRecord;
use josemmo\Verifactu\Models\Records\FiscalIdentifier;
use josemmo\Verifactu\Models\Records\InvoiceIdentifier;
use josemmo\Verifactu\Models\Responses\AeatSubmissionResult;
use josemmo\Verifactu\Models\Responses\ResponseStatus;
use josemmo\Verifactu\Services\AeatClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use ReflectionObject;

final class AeatClientTest extends TestCase {
    private MockHandler $mockHandler;

    /**
     * Build a valid `ComputerSystem` for tests.
     */
    private function buildValidSystem(): ComputerSystem {
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
        return $system;
    }

    /**
     * Build a valid taxpayer for tests.
     */
    private function buildValidTaxpayer(): FiscalIdentifier {
        return new FiscalIdentifier('Perico de los Palotes, S.A.', 'A00000000');
    }

    /**
     * Read the internally created Guzzle client of an `AeatClient` via reflection.
     */
    private function getInternalHttpClient(AeatClient $client): Client {
        $property = (new ReflectionObject($client))->getProperty('client');
        /** @var Client $inner */
        $inner = $property->getValue($client);
        return $inner;
    }

    /**
     * Get mocked AEAT client
     *
     * @param Response|ClientExceptionInterface $response Mocked response
     *
     * @return AeatClient AEAT client instance
     */
    private function getMockedClient(Response|ClientExceptionInterface $response): AeatClient {
        // Create HTTP client mock
        $mock = new MockHandler([$response]);
        $this->mockHandler = $mock;
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        // Build AEAT client
        $client = new AeatClient($this->buildValidSystem(), $this->buildValidTaxpayer(), $httpClient);

        return $client;
    }

    /**
     * Get mocked record
     *
     * @return CancellationRecord Record instance
     */
    private function getMockedRecord(): CancellationRecord {
        $record = new CancellationRecord();
        $record->invoiceId = new InvoiceIdentifier('89890001K', 'TEST123', new DateTimeImmutable('2025-12-10'));
        $record->previousInvoiceId = new InvoiceIdentifier('89890001K', 'TEST122', new DateTimeImmutable('2025-12-08'));
        $record->previousHash = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';
        $record->hashedAt = new DateTimeImmutable();
        $record->hash = $record->calculateHash();
        $record->validate();
        return $record;
    }

    private function getSuccessfulResponseXml(): string {
        return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <env:Envelope xmlns:env="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tikR="https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/RespuestaSuministro.xsd" xmlns:tik="https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd">
            <env:Header/>
            <env:Body>
                <tikR:RespuestaRegFactuSistemaFacturacion>
                    <tikR:CSV>CSV-123</tikR:CSV>
                    <tikR:DatosPresentacion>
                        <tik:NIFPresentador>A00000000</tik:NIFPresentador>
                        <tik:TimestampPresentacion>2025-10-13T12:34:56+02:00</tik:TimestampPresentacion>
                    </tikR:DatosPresentacion>
                    <tikR:TiempoEsperaEnvio>60</tikR:TiempoEsperaEnvio>
                    <tikR:EstadoEnvio>Correcto</tikR:EstadoEnvio>
                    <tikR:RespuestaLinea>
                        <tikR:IDFactura>
                            <tik:IDEmisorFactura>89890001K</tik:IDEmisorFactura>
                            <tik:NumSerieFactura>TEST123</tik:NumSerieFactura>
                            <tik:FechaExpedicionFactura>10-12-2025</tik:FechaExpedicionFactura>
                        </tikR:IDFactura>
                        <tikR:Operacion>
                            <tik:TipoOperacion>Anulacion</tik:TipoOperacion>
                            <tik:Subsanacion>N</tik:Subsanacion>
                        </tikR:Operacion>
                        <tikR:EstadoRegistro>Correcto</tikR:EstadoRegistro>
                    </tikR:RespuestaLinea>
                </tikR:RespuestaRegFactuSistemaFacturacion>
            </env:Body>
        </env:Envelope>
        XML;
    }

    public function testValidatesBatchSize(): void {
        $client = $this->getMockedClient(new Response(200, [], '<ok/>'));

        // Empty batch should fail
        try {
            $client->send([])->wait();
            $this->fail('Did not throw for empty batch');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('between 1 and 1000', $e->getMessage());
        }
    }

    public function testSendReturnsSubmissionResultAndUsesRequestXmlAsBody(): void {
        $responseXml = $this->getSuccessfulResponseXml();
        $client = $this->getMockedClient(new Response(200, [], $responseXml));
        $record = $this->getMockedRecord();

        $result = $client->send([$record])->wait();

        $this->assertInstanceOf(AeatSubmissionResult::class, $result);
        $this->assertStringContainsString('<soapenv:Envelope', $result->request->xml);
        $this->assertSame($responseXml, $result->response->xml);
        $this->assertSame(ResponseStatus::Correct, $result->response->status);
        $this->assertSame($result->request->xml, (string) $this->mockHandler->getLastRequest()?->getBody());
    }

    public function testThrowsExceptionForMalformedXmlResponse(): void {
        $responseXml = '<element>Malformed XML</notClosingElement>';
        $client = $this->getMockedClient(new Response(200, [], '<element>Malformed XML</notClosingElement>'));
        $record = $this->getMockedRecord();

        try {
            $client->send([$record])->wait();
            $this->fail('Did not throw for malformed XML response');
        } catch (AeatException $e) {
            $this->assertStringContainsString('Failed to parse XML response', $e->getMessage());
            $this->assertNotNull($e->requestXml);
            $this->assertSame($responseXml, $e->responseXml);
        }
    }

    public function testThrowsExceptionForUnexpectedXmlResponse(): void {
        $responseXml = '<html><body>Unauthorized</body></html>';
        $client = $this->getMockedClient(new Response(401, [], $responseXml));
        $record = $this->getMockedRecord();

        try {
            $client->send([$record])->wait();
            $this->fail('Did not throw for unexpected XML response');
        } catch (AeatException $e) {
            $this->assertStringContainsString('Missing <tikR:RespuestaRegFactuSistemaFacturacion /> element from response', $e->getMessage());
            $this->assertNotNull($e->requestXml);
            $this->assertSame($responseXml, $e->responseXml);
        }
    }

    public function testThrowsExceptionForSoapFaultWithPayloads(): void {
        $responseXml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <env:Envelope xmlns:env="http://schemas.xmlsoap.org/soap/envelope/">
            <env:Body>
                <env:Fault>
                    <faultcode>env:Server</faultcode>
                    <faultstring>Codigo[20009].Error interno en el servidor</faultstring>
                </env:Fault>
            </env:Body>
        </env:Envelope>
        XML;
        $client = $this->getMockedClient(new Response(500, [], $responseXml));
        $record = $this->getMockedRecord();

        try {
            $client->send([$record])->wait();
            $this->fail('Did not throw for SOAP fault response');
        } catch (AeatException $e) {
            $this->assertStringContainsString('Codigo[20009].Error interno en el servidor', $e->getMessage());
            $this->assertNotNull($e->requestXml);
            $this->assertSame($responseXml, $e->responseXml);
            $this->assertSame(20009, $e->aeatErrorCode);
        }
    }

    public function testThrowsExceptionOnConnectionError(): void {
        $client = $this->getMockedClient(new ConnectException('Exception message', new Request('GET', 'test')));
        $record = $this->getMockedRecord();

        try {
            $client->send([$record])->wait();
            $this->fail('Did not throw for connection error');
        } catch (AeatException $e) {
            $this->assertStringContainsString('Exception message', $e->getMessage());
            $this->assertNotNull($e->requestXml);
            $this->assertNull($e->responseXml);
            $this->assertInstanceOf(ConnectException::class, $e->getPrevious());
        }
    }

    public function testInternalClientUsesDefaultTimeouts(): void {
        $client = new AeatClient($this->buildValidSystem(), $this->buildValidTaxpayer());
        $inner = $this->getInternalHttpClient($client);

        $this->assertSame(AeatClient::DEFAULT_CONNECT_TIMEOUT, $inner->getConfig('connect_timeout'));
        $this->assertSame(AeatClient::DEFAULT_TIMEOUT, $inner->getConfig('timeout'));
    }

    public function testConstructorAppliesCustomTimeoutsToInternalClient(): void {
        $client = new AeatClient(
            $this->buildValidSystem(),
            $this->buildValidTaxpayer(),
            null,
            5,
            30,
        );
        $inner = $this->getInternalHttpClient($client);

        $this->assertSame(5, $inner->getConfig('connect_timeout'));
        $this->assertSame(30, $inner->getConfig('timeout'));
    }

    public function testZeroTimeoutsAreForwardedToInternalClient(): void {
        $client = new AeatClient(
            $this->buildValidSystem(),
            $this->buildValidTaxpayer(),
            null,
            0,
            0,
        );
        $inner = $this->getInternalHttpClient($client);

        // `0` is Guzzle's "no timeout" sentinel; the library forwards it verbatim
        // so callers that genuinely want an unbounded wait can still opt in.
        $this->assertSame(0, $inner->getConfig('connect_timeout'));
        $this->assertSame(0, $inner->getConfig('timeout'));
    }

    public function testInjectedHttpClientIsPreservedAndTimeoutArgsIgnored(): void {
        $injected = new Client([
            'connect_timeout' => 7,
            'timeout'         => 90,
        ]);

        $client = new AeatClient(
            $this->buildValidSystem(),
            $this->buildValidTaxpayer(),
            $injected,
            5,
            30,
        );

        // The injected client must be used as-is: its options prevail and the
        // timeout constructor arguments must not override them.
        $this->assertSame($injected, $this->getInternalHttpClient($client));
        $this->assertSame(7, $injected->getConfig('connect_timeout'));
        $this->assertSame(90, $injected->getConfig('timeout'));
    }
}

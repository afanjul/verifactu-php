<?php
namespace josemmo\Verifactu\Tests\Models\Exceptions;

use josemmo\Verifactu\Exceptions\AeatException;
use PHPUnit\Framework\TestCase;

final class AeatExceptionTest extends TestCase {
    public function testParsesStructuredCodeFromFaultString(): void {
        $e = AeatException::fromFaultString(
            'Codigo[4104].El NIF del titular en la cabecera no está identificado.',
            requestXml: '<req/>',
            responseXml: '<resp/>',
        );
        $this->assertSame(4104, $e->aeatErrorCode);
        $this->assertSame('Codigo[4104].El NIF del titular en la cabecera no está identificado.', $e->getMessage());
        $this->assertSame('<req/>', $e->requestXml);
        $this->assertSame('<resp/>', $e->responseXml);
    }

    public function testReturnsNullCodeWhenFaultStringHasNoPrefix(): void {
        $e = AeatException::fromFaultString('Unexpected server error');
        $this->assertNull($e->aeatErrorCode);
    }
}

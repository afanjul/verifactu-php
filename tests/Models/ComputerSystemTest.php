<?php
namespace josemmo\Verifactu\Tests\Models;

use josemmo\Verifactu\Models\ComputerSystem;
use josemmo\Verifactu\Models\Records\Record;
use josemmo\Verifactu\Tests\TestUtils;
use PHPUnit\Framework\TestCase;
use UXML\UXML;

final class ComputerSystemTest extends TestCase {
    public function testImportsAndExportsModel(): void {
        // Import model
        $modelXml = TestUtils::getXmlFile(__DIR__ . '/computer-system.xml');
        $computerSystem = ComputerSystem::fromXml($modelXml);

        // Export model
        $exportedXml = UXML::newInstance('container', null, ['xmlns:sum1' => Record::NS]);
        $computerSystem->export($exportedXml);
        $this->assertXmlStringEqualsXmlString($modelXml, $exportedXml->get('sum1:SistemaInformatico')?->asXML() ?? '');
    }

    public function testValidatesIdSistemaInformatico(): void {
        $base = ComputerSystem::fromXml(TestUtils::getXmlFile(__DIR__ . '/computer-system.xml'));

        // Two uppercase alphanumeric chars → valid
        $base->id = 'TS';
        $base->validate();
        $this->assertSame('TS', $base->id); // validation passed without exception

        // One character → invalid (spec requires always exactly 2 positions)
        $base->id = 'T';
        try {
            $base->validate();
            $this->fail('Did not throw for single-character id');
        } catch (\josemmo\Verifactu\Exceptions\InvalidModelException) {
            // expected
        }

        // Lowercase → invalid
        $base->id = 'ts';
        try {
            $base->validate();
            $this->fail('Did not throw for lowercase id');
        } catch (\josemmo\Verifactu\Exceptions\InvalidModelException) {
            // expected
        }

        // Three chars → invalid
        $base->id = 'TSX';
        try {
            $base->validate();
            $this->fail('Did not throw for three-character id');
        } catch (\josemmo\Verifactu\Exceptions\InvalidModelException) {
            // expected
        }
    }
}

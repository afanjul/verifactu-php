<?php
namespace josemmo\Verifactu\Tests\Models\Records;

use josemmo\Verifactu\Exceptions\InvalidModelException;
use josemmo\Verifactu\Models\Records\BreakdownDetails;
use josemmo\Verifactu\Models\Records\OperationType;
use josemmo\Verifactu\Models\Records\RegimeType;
use josemmo\Verifactu\Models\Records\TaxType;
use PHPUnit\Framework\TestCase;

final class BreakdownDetailsTest extends TestCase {
    public function testValidatesTaxAmount(): void {
        $details = new BreakdownDetails();
        $details->taxType = TaxType::IVA;
        $details->regimeType = RegimeType::C01;
        $details->operationType = OperationType::Subject;
        $details->baseAmount = '11.22';
        $details->taxRate = '10.00';
        $details->taxAmount = '1.12';

        // Should pass validation
        $details->validate();

        // Wrong tax amount
        $details->taxAmount = '99.99';
        try {
            $details->validate();
            $this->fail('Did not throw exception for invalid tax amount');
        } catch (InvalidModelException $e) {
            $this->assertStringContainsString('Expected amount of 1.12, got 99.99', $e->getMessage());
        }

        // Acceptable tax amount differences
        $details->taxAmount = '1.11';
        $details->validate();
        $details->taxAmount = '1.13';
        $details->validate();
    }

    public function testValidatesSurchargeAmount(): void {
        $details = new BreakdownDetails();
        $details->taxType = TaxType::IVA;
        $details->regimeType = RegimeType::C01;
        $details->operationType = OperationType::Subject;
        $details->baseAmount = '33.22';
        $details->taxRate = '21.00';
        $details->taxAmount = '6.98';
        $details->surchargeRate = '5.20';
        $details->surchargeAmount = '1.73';

        // Should pass validation
        $details->validate();

        // Wrong tax amount
        $details->taxAmount = '99.99';
        $details->surchargeAmount = '12.34';
        try {
            $details->validate();
            $this->fail('Did not throw exception for invalid tax amount');
        } catch (InvalidModelException $e) {
            $this->assertStringContainsString('Expected amount of 6.98, got 99.99', $e->getMessage());
            $this->assertStringContainsString('Expected amount of 1.73, got 12.34', $e->getMessage());
        }

        // Acceptable tax amount differences
        $details->taxAmount = '6.97';
        $details->surchargeAmount = '1.74';
        $details->validate();
        $details->taxAmount = '6.99';
        $details->surchargeAmount = '1.73';
        $details->validate();
    }

    public function testValidatesOperationAndRegimeType(): void {
        $details = new BreakdownDetails();
        $details->taxType = TaxType::IVA;
        $details->baseAmount = '100.00';

        // Missing tax details for subject operations
        $details->regimeType = RegimeType::C01;
        $details->operationType = OperationType::Subject;
        try {
            $details->validate();
            $this->fail('Did not throw exception for missing tax rate and amount');
        } catch (InvalidModelException $e) {
            $this->assertStringContainsString('Tax rate must be defined for subject operation types', $e->getMessage());
            $this->assertStringContainsString('Tax amount must be defined for subject operation types', $e->getMessage());
        }

        // Surcharge is not tied to the regime key (AEAT removed 15.6.9 in v1.1.2):
        // a supplier under C01 charges it, a retailer under C18 issues without it
        $details->taxRate = '21.00';
        $details->taxAmount = '21.00';
        $details->regimeType = RegimeType::C18;
        $details->validate();
        $details->regimeType = RegimeType::C01;
        $details->surchargeRate = '5.20';
        $details->surchargeAmount = '5.20';
        $details->validate();

        // Surcharge rate and amount go together (AEAT 1284)
        $details->surchargeAmount = null;
        try {
            $details->validate();
            $this->fail('Did not throw exception for surcharge rate without amount');
        } catch (InvalidModelException $e) {
            $this->assertStringContainsString('Surcharge rate and amount must be defined together', $e->getMessage());
        }

        // Surcharge only for S1 operations (AEAT 1281)
        $details->surchargeAmount = '5.20';
        $details->operationType = OperationType::PassiveSubject;
        $details->taxRate = '0.00';
        $details->taxAmount = '0.00';
        try {
            $details->validate();
            $this->fail('Did not throw exception for surcharge on S2 operation');
        } catch (InvalidModelException $e) {
            $this->assertStringContainsString('Surcharge can only be defined for S1 operation type', $e->getMessage());
        }
        $details->taxRate = '21.00';
        $details->taxAmount = '21.00';

        // Extra tax details for exempt operations
        $details->operationType = OperationType::ExemptByArticle20;
        try {
            $details->validate();
            $this->fail('Did not throw exception for extra tax rate and amount');
        } catch (InvalidModelException $e) {
            $this->assertStringContainsString('Tax rate cannot be defined for non-subject or exempt operation types', $e->getMessage());
            $this->assertStringContainsString('Tax amount cannot be defined for non-subject or exempt operation types', $e->getMessage());
        }

        // Should pass validation for exempt operations
        $details->taxRate = null;
        $details->taxAmount = null;
        $details->surchargeRate = null;
        $details->surchargeAmount = null;
        $details->operationType = OperationType::ExemptByOther;
        $details->validate();
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\QuotingRules;

use App\Domain\Currency\Currency;
use App\Domain\QuotingRules\QuotingRulesService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * QuotingRulesService Unit Tests
 * 
 * Tests business logic for currency rate calculations and validation.
 * Covers all quoting rules and edge cases.
 */
class QuotingRulesServiceTest extends TestCase
{
    private QuotingRulesService $service;
    private Currency $eur;
    private Currency $usd;
    private Currency $czk;
    private Currency $idr;
    private Currency $brl;
    private DateTimeImmutable $testDate;

    protected function setUp(): void
    {
        $this->service = new QuotingRulesService();
        $this->eur = Currency::fromCode('EUR');
        $this->usd = Currency::fromCode('USD');
        $this->czk = Currency::fromCode('CZK');
        $this->idr = Currency::fromCode('IDR');
        $this->brl = Currency::fromCode('BRL');
        $this->testDate = new DateTimeImmutable('2024-01-15');
    }

    /**
     * Test: EUR and USD buy rate calculation
     */
    public function testEurAndUsdBuyRateCalculation(): void
    {
        // Given: EUR and USD with mid rates
        $eurMidRate = 4.5000;
        $usdMidRate = 4.0000;
        
        // When: Calculating buy rates
        $eurBuyRate = $this->service->calculateBuyRate($this->eur, $eurMidRate);
        $usdBuyRate = $this->service->calculateBuyRate($this->usd, $usdMidRate);
        
        // Then: Buy rates are mid - 0.15
        $this->assertEquals(4.3500, $eurBuyRate); // 4.5000 - 0.15
        $this->assertEquals(3.8500, $usdBuyRate); // 4.0000 - 0.15
    }

    /**
     * Test: CZK, IDR, BRL have no buy rates
     */
    public function testOtherCurrenciesHaveNoBuyRates(): void
    {
        // Given: CZK, IDR, BRL with mid rates
        $midRate = 1.0000;
        
        // When: Calculating buy rates
        $czkBuyRate = $this->service->calculateBuyRate($this->czk, $midRate);
        $idrBuyRate = $this->service->calculateBuyRate($this->idr, $midRate);
        $brlBuyRate = $this->service->calculateBuyRate($this->brl, $midRate);
        
        // Then: All buy rates are null
        $this->assertNull($czkBuyRate);
        $this->assertNull($idrBuyRate);
        $this->assertNull($brlBuyRate);
    }

    /**
     * Test: EUR and USD sell rate calculation
     */
    public function testEurAndUsdSellRateCalculation(): void
    {
        // Given: EUR and USD with mid rates
        $eurMidRate = 4.5000;
        $usdMidRate = 4.0000;
        
        // When: Calculating sell rates
        $eurSellRate = $this->service->calculateSellRate($this->eur, $eurMidRate);
        $usdSellRate = $this->service->calculateSellRate($this->usd, $usdMidRate);
        
        // Then: Sell rates are mid + 0.11
        $this->assertEquals(4.6100, $eurSellRate); // 4.5000 + 0.11
        $this->assertEquals(4.1100, $usdSellRate); // 4.0000 + 0.11
    }

    /**
     * Test: CZK, IDR, BRL sell rate calculation
     */
    public function testOtherCurrenciesSellRateCalculation(): void
    {
        // Given: CZK, IDR, BRL with mid rates
        $czkMidRate = 0.1850;
        $idrMidRate = 0.0003;
        $brlMidRate = 0.8500;
        
        // When: Calculating sell rates
        $czkSellRate = $this->service->calculateSellRate($this->czk, $czkMidRate);
        $idrSellRate = $this->service->calculateSellRate($this->idr, $idrMidRate);
        $brlSellRate = $this->service->calculateSellRate($this->brl, $brlMidRate);
        
        // Then: Sell rates are mid + 0.20
        $this->assertEquals(0.3850, $czkSellRate); // 0.1850 + 0.20
        $this->assertEquals(0.2003, $idrSellRate); // 0.0003 + 0.20
        $this->assertEquals(1.0500, $brlSellRate); // 0.8500 + 0.20
    }

    /**
     * Test: Create rate from mid rate
     */
    public function testCreateRateFromMidRate(): void
    {
        // Given: EUR with mid rate
        $midRate = 4.5000;
        
        // When: Creating rate from mid rate
        $rate = $this->service->createRateFromMid($this->eur, $this->testDate, $midRate);
        
        // Then: Rate is created with correct calculations
        $this->assertEquals($this->eur, $rate->getCurrency());
        $this->assertEquals($this->testDate, $rate->getDate());
        $this->assertEquals(4.5000, $rate->getMidRate());
        $this->assertEquals(4.3500, $rate->getBuyRate());
        $this->assertEquals(4.6100, $rate->getSellRate());
    }

    /**
     * Test: Get profit margins
     */
    public function testGetProfitMargins(): void
    {
        // When: Getting profit margins
        $eurBuyMargin = $this->service->getBuyProfitMargin($this->eur);
        $eurSellMargin = $this->service->getSellProfitMargin($this->eur);
        $czkBuyMargin = $this->service->getBuyProfitMargin($this->czk);
        $czkSellMargin = $this->service->getSellProfitMargin($this->czk);
        
        // Then: Margins are correct
        $this->assertEquals(-0.15, $eurBuyMargin);
        $this->assertEquals(0.11, $eurSellMargin);
        $this->assertNull($czkBuyMargin);
        $this->assertEquals(0.20, $czkSellMargin);
    }

    /**
     * Test: Supports buying check
     */
    public function testSupportsBuyingCheck(): void
    {
        // When: Checking buying support
        $eurSupports = $this->service->supportsBuying($this->eur);
        $usdSupports = $this->service->supportsBuying($this->usd);
        $czkSupports = $this->service->supportsBuying($this->czk);
        
        // Then: Only EUR and USD support buying
        $this->assertTrue($eurSupports);
        $this->assertTrue($usdSupports);
        $this->assertFalse($czkSupports);
    }

    /**
     * Test: Get currency rules
     */
    public function testGetCurrencyRules(): void
    {
        // When: Getting EUR rules
        $eurRules = $this->service->getCurrencyRules($this->eur);
        
        // Then: Rules are correct
        $expected = [
            'code' => 'EUR',
            'supportsBuying' => true,
            'buyMargin' => -0.15,
            'sellMargin' => 0.11,
        ];
        
        $this->assertEquals($expected, $eurRules);
    }

    /**
     * Test: Get all currency rules
     */
    public function testGetAllCurrencyRules(): void
    {
        // When: Getting all currency rules
        $allRules = $this->service->getAllCurrencyRules();
        
        // Then: All currencies are included
        $this->assertCount(5, $allRules);
        $this->assertArrayHasKey('EUR', $allRules);
        $this->assertArrayHasKey('USD', $allRules);
        $this->assertArrayHasKey('CZK', $allRules);
        $this->assertArrayHasKey('IDR', $allRules);
        $this->assertArrayHasKey('BRL', $allRules);
        
        // And: EUR rules are correct
        $this->assertTrue($allRules['EUR']['supportsBuying']);
        $this->assertEquals(-0.15, $allRules['EUR']['buyMargin']);
        
        // And: CZK rules are correct
        $this->assertFalse($allRules['CZK']['supportsBuying']);
        $this->assertNull($allRules['CZK']['buyMargin']);
        $this->assertEquals(0.20, $allRules['CZK']['sellMargin']);
    }

    /**
     * Test: Mid rate validation
     */
    public function testMidRateValidation(): void
    {
        // Test valid rates
        $this->assertTrue($this->service->validateMidRate($this->eur, 4.5000));
        $this->assertTrue($this->service->validateMidRate($this->usd, 4.0000));
        $this->assertTrue($this->service->validateMidRate($this->czk, 0.1850));
        $this->assertTrue($this->service->validateMidRate($this->idr, 0.0005));
        $this->assertTrue($this->service->validateMidRate($this->brl, 0.8500));
        
        // Test invalid rates (too low/high)
        $this->assertFalse($this->service->validateMidRate($this->eur, 0.0)); // Zero
        $this->assertFalse($this->service->validateMidRate($this->eur, -1.0)); // Negative
        $this->assertFalse($this->service->validateMidRate($this->eur, 10.0)); // Too high for EUR
        $this->assertFalse($this->service->validateMidRate($this->czk, 1.0)); // Too high for CZK
    }

    /**
     * Test: Edge case - very small numbers
     */
    public function testEdgeCaseVerySmallNumbers(): void
    {
        // Given: Very small IDR rate
        $verySmallRate = 0.0001;
        
        // When: Calculating rates
        $buyRate = $this->service->calculateBuyRate($this->idr, $verySmallRate);
        $sellRate = $this->service->calculateSellRate($this->idr, $verySmallRate);
        
        // Then: Calculations work correctly
        $this->assertNull($buyRate); // IDR doesn't support buying
        $this->assertEquals(0.2001, $sellRate); // 0.0001 + 0.20
    }

    /**
     * Test: Edge case - large numbers
     */
    public function testEdgeCaseLargeNumbers(): void
    {
        // Given: Large EUR rate
        $largeRate = 5.9999;
        
        // When: Calculating rates
        $buyRate = $this->service->calculateBuyRate($this->eur, $largeRate);
        $sellRate = $this->service->calculateSellRate($this->eur, $largeRate);
        
        // Then: Calculations work correctly
        $this->assertEqualsWithDelta(5.8499, $buyRate, 0.0001); // 5.9999 - 0.15
        $this->assertEqualsWithDelta(6.1099, $sellRate, 0.0001); // 5.9999 + 0.11
    }
}

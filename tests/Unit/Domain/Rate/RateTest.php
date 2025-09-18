<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Rate;

use App\Domain\Currency\Currency;
use App\Domain\Rate\Rate;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Rate Unit Tests
 * 
 * Tests Rate entity business logic, calculations, and validation.
 * Covers rate creation, updates, and conversions.
 */
class RateTest extends TestCase
{
    private Currency $eur;
    private Currency $czk;
    private DateTimeImmutable $testDate;

    protected function setUp(): void
    {
        $this->eur = Currency::fromCode('EUR');
        $this->czk = Currency::fromCode('CZK');
        $this->testDate = new DateTimeImmutable('2024-01-15');
    }

    /**
     * Test: Rate can be created with auto-calculated buy/sell rates
     */
    public function testCanCreateRateWithAutoCalculatedRates(): void
    {
        // Given: EUR with mid rate 4.5000
        $midRate = 4.5000;
        
        // When: Creating rate from mid rate
        $rate = Rate::fromMidRate($this->eur, $this->testDate, $midRate);
        
        // Then: Rates are calculated correctly
        $this->assertEquals($this->eur, $rate->getCurrency());
        $this->assertEquals($this->testDate, $rate->getDate());
        $this->assertEquals(4.5000, $rate->getMidRate());
        $this->assertEquals(4.3500, $rate->getBuyRate()); // 4.5000 - 0.15
        $this->assertEquals(4.6100, $rate->getSellRate()); // 4.5000 + 0.11
    }

    /**
     * Test: CZK rate has no buy rate (only sell)
     */
    public function testCzkRateHasNoBuyRate(): void
    {
        // Given: CZK with mid rate 0.1850
        $midRate = 0.1850;
        
        // When: Creating CZK rate
        $rate = Rate::fromMidRate($this->czk, $this->testDate, $midRate);
        
        // Then: No buy rate, only sell rate
        $this->assertEquals(0.1850, $rate->getMidRate());
        $this->assertNull($rate->getBuyRate()); // CZK doesn't support buying
        $this->assertEquals(0.3850, $rate->getSellRate()); // 0.1850 + 0.20
    }

    /**
     * Test: Rate can be created with explicit buy/sell rates
     */
    public function testCanCreateRateWithExplicitRates(): void
    {
        // Given: Explicit rates
        $midRate = 4.5000;
        $buyRate = 4.3000; // Custom buy rate
        $sellRate = 4.7000; // Custom sell rate
        
        // When: Creating rate with explicit rates
        $rate = new Rate($this->eur, $this->testDate, $midRate, $buyRate, $sellRate);
        
        // Then: Uses provided rates (not calculated)
        $this->assertEquals(4.5000, $rate->getMidRate());
        $this->assertEquals(4.3000, $rate->getBuyRate());
        $this->assertEquals(4.7000, $rate->getSellRate());
    }

    /**
     * Test: Rate mid rate can be updated
     */
    public function testCanUpdateMidRate(): void
    {
        // Given: Rate with initial mid rate
        $rate = Rate::fromMidRate($this->eur, $this->testDate, 4.5000);
        
        // When: Updating mid rate
        $rate->updateMidRate(4.6000);
        
        // Then: All rates are recalculated
        $this->assertEquals(4.6000, $rate->getMidRate());
        $this->assertEqualsWithDelta(4.4500, $rate->getBuyRate(), 0.0001); // 4.6000 - 0.15
        $this->assertEqualsWithDelta(4.7100, $rate->getSellRate(), 0.0001); // 4.6000 + 0.11
    }

    /**
     * Test: Negative mid rate throws exception
     */
    public function testNegativeMidRateThrowsException(): void
    {
        // Expect: InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Mid rate must be positive');
        
        // When: Creating rate with negative mid rate
        Rate::fromMidRate($this->eur, $this->testDate, -1.0);
    }

    /**
     * Test: Zero mid rate throws exception
     */
    public function testZeroMidRateThrowsException(): void
    {
        // Expect: InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);
        
        // When: Creating rate with zero mid rate
        Rate::fromMidRate($this->eur, $this->testDate, 0.0);
    }

    /**
     * Test: Rate ID is generated correctly
     */
    public function testRateIdIsGeneratedCorrectly(): void
    {
        // Given: Rate
        $rate = Rate::fromMidRate($this->eur, $this->testDate, 4.5000);
        
        // Then: ID is currency_date format
        $expectedId = 'EUR_2024-01-15';
        $this->assertEquals($expectedId, $rate->getId());
    }

    /**
     * Test: Rate equality works correctly
     */
    public function testRateEquality(): void
    {
        // Given: Two rates with same currency and date
        $rate1 = Rate::fromMidRate($this->eur, $this->testDate, 4.5000);
        $rate2 = Rate::fromMidRate($this->eur, $this->testDate, 4.6000); // Different mid rate
        $rate3 = Rate::fromMidRate($this->czk, $this->testDate, 0.1850); // Different currency
        
        // Then: Same currency+date are equal (regardless of rates)
        $this->assertTrue($rate1->equals($rate2));
        
        // And: Different currency is not equal
        $this->assertFalse($rate1->equals($rate3));
    }

    /**
     * Test: Rate converts to array correctly
     */
    public function testRateConvertsToArrayCorrectly(): void
    {
        // Given: EUR rate
        $rate = Rate::fromMidRate($this->eur, $this->testDate, 4.5678);
        
        // When: Converting to array
        $array = $rate->toArray();
        
        // Then: Array has correct structure and rounded values
        $expected = [
            'code' => 'EUR',
            'date' => '2024-01-15',
            'mid' => 4.5678,
            'buy' => 4.4178, // 4.5678 - 0.15, rounded to 4 decimals
            'sell' => 4.6778, // 4.5678 + 0.11, rounded to 4 decimals
        ];
        
        $this->assertEquals($expected, $array);
    }

    /**
     * Test: CZK rate array has null buy rate
     */
    public function testCzkRateArrayHasNullBuyRate(): void
    {
        // Given: CZK rate
        $rate = Rate::fromMidRate($this->czk, $this->testDate, 0.1850);
        
        // When: Converting to array
        $array = $rate->toArray();
        
        // Then: Buy rate is null
        $this->assertNull($array['buy']);
        $this->assertEquals(0.3850, $array['sell']);
    }

    /**
     * Test: Rate handles precision correctly
     */
    public function testRateHandlesPrecisionCorrectly(): void
    {
        // Given: Rate with many decimal places
        $rate = Rate::fromMidRate($this->eur, $this->testDate, 4.123456789);
        
        // When: Converting to array
        $array = $rate->toArray();
        
        // Then: Values are rounded to 4 decimal places
        $this->assertEquals(4.1235, $array['mid']); // Rounded from 4.123456789
        $this->assertEquals(3.9735, $array['buy']); // 4.123456789 - 0.15, rounded
        $this->assertEquals(4.2335, $array['sell']); // 4.123456789 + 0.11, rounded
    }
}

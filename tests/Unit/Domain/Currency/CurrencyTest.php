<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Currency;

use App\Domain\Currency\Currency;
use App\Domain\Currency\CurrencyCode;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Currency Unit Tests
 * 
 * Tests Currency value object and CurrencyCode enum business logic.
 * Covers all business rules for supported currencies.
 */
class CurrencyTest extends TestCase
{
    /**
     * Test: Currency can be created from valid code
     */
    public function testCanCreateCurrencyFromValidCode(): void
    {
        // Given: Valid currency code
        $code = 'EUR';
        
        // When: Creating currency from code
        $currency = Currency::fromCode($code);
        
        // Then: Currency is created correctly
        $this->assertEquals('EUR', $currency->getCode());
        $this->assertEquals(CurrencyCode::EUR, $currency->getCurrencyCode());
    }

    /**
     * Test: Currency creation is case insensitive
     */
    public function testCurrencyCreationIsCaseInsensitive(): void
    {
        // Given: Lowercase currency code
        $currency1 = Currency::fromCode('eur');
        $currency2 = Currency::fromCode('EUR');
        $currency3 = Currency::fromCode('Eur');
        
        // Then: All create the same currency
        $this->assertTrue($currency1->equals($currency2));
        $this->assertTrue($currency2->equals($currency3));
        $this->assertEquals('EUR', $currency1->getCode());
    }

    /**
     * Test: Invalid currency code throws exception
     */
    public function testInvalidCurrencyCodeThrowsException(): void
    {
        // Expect: InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported currency code: XYZ');
        
        // When: Creating currency with invalid code
        Currency::fromCode('XYZ');
    }

    /**
     * Test: EUR and USD support buying
     */
    public function testEurAndUsdSupportBuying(): void
    {
        // Given: EUR and USD currencies
        $eur = Currency::fromCode('EUR');
        $usd = Currency::fromCode('USD');
        
        // Then: Both support buying
        $this->assertTrue($eur->supportsBuying());
        $this->assertTrue($usd->supportsBuying());
        
        // And: Have correct buy margins
        $this->assertEquals(-0.15, $eur->getBuyMargin());
        $this->assertEquals(-0.15, $usd->getBuyMargin());
    }

    /**
     * Test: CZK, IDR, BRL do not support buying
     */
    public function testOtherCurrenciesDoNotSupportBuying(): void
    {
        // Given: CZK, IDR, BRL currencies
        $czk = Currency::fromCode('CZK');
        $idr = Currency::fromCode('IDR');
        $brl = Currency::fromCode('BRL');
        
        // Then: None support buying
        $this->assertFalse($czk->supportsBuying());
        $this->assertFalse($idr->supportsBuying());
        $this->assertFalse($brl->supportsBuying());
        
        // And: Buy margin is null
        $this->assertNull($czk->getBuyMargin());
        $this->assertNull($idr->getBuyMargin());
        $this->assertNull($brl->getBuyMargin());
    }

    /**
     * Test: Sell margins are correct for all currencies
     */
    public function testSellMarginsAreCorrect(): void
    {
        // Given: All supported currencies
        $eur = Currency::fromCode('EUR');
        $usd = Currency::fromCode('USD');
        $czk = Currency::fromCode('CZK');
        $idr = Currency::fromCode('IDR');
        $brl = Currency::fromCode('BRL');
        
        // Then: EUR and USD have 0.11 margin
        $this->assertEquals(0.11, $eur->getSellMargin());
        $this->assertEquals(0.11, $usd->getSellMargin());
        
        // And: Others have 0.20 margin
        $this->assertEquals(0.20, $czk->getSellMargin());
        $this->assertEquals(0.20, $idr->getSellMargin());
        $this->assertEquals(0.20, $brl->getSellMargin());
    }

    /**
     * Test: Currency equality works correctly
     */
    public function testCurrencyEquality(): void
    {
        // Given: Two EUR currencies
        $eur1 = Currency::fromCode('EUR');
        $eur2 = Currency::fromCode('EUR');
        $usd = Currency::fromCode('USD');
        
        // Then: Same currencies are equal
        $this->assertTrue($eur1->equals($eur2));
        
        // And: Different currencies are not equal
        $this->assertFalse($eur1->equals($usd));
    }

    /**
     * Test: Currency string representation
     */
    public function testCurrencyStringRepresentation(): void
    {
        // Given: Currency
        $eur = Currency::fromCode('EUR');
        
        // Then: String representation is the code
        $this->assertEquals('EUR', (string) $eur);
    }

    /**
     * Test: All supported currency codes are available
     */
    public function testAllSupportedCurrencyCodesAreAvailable(): void
    {
        // Given: Expected currency codes
        $expectedCodes = ['EUR', 'USD', 'CZK', 'IDR', 'BRL'];
        
        // When: Getting all codes
        $actualCodes = CurrencyCode::getAllCodes();
        
        // Then: All expected codes are present
        $this->assertEquals($expectedCodes, $actualCodes);
        $this->assertCount(5, $actualCodes);
    }
}

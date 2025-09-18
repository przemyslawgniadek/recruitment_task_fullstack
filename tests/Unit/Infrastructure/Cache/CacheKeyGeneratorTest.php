<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Cache;

use App\Domain\Currency\Currency;
use App\Infrastructure\Cache\CacheKeyGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CacheKeyGenerator
 * 
 * Tests cache key generation, validation, parsing, and utility methods.
 * Ensures consistent key format and proper error handling.
 */
final class CacheKeyGeneratorTest extends TestCase
{
    private CacheKeyGenerator $keyGenerator;

    protected function setUp(): void
    {
        $this->keyGenerator = new CacheKeyGenerator();
    }

    public function testForSingleRate(): void
    {
        $currency = Currency::fromCode('EUR');
        $date = new DateTimeImmutable('2024-01-15');
        
        $key = $this->keyGenerator->forSingleRate($currency, $date);
        
        $this->assertSame('fx_rate.single.EUR.2024-01-15', $key);
    }

    public function testForAllRates(): void
    {
        $date = new DateTimeImmutable('2024-01-15');
        
        $key = $this->keyGenerator->forAllRates($date);
        
        $this->assertSame('fx_rate.all.ALL.2024-01-15', $key);
    }

    public function testForHistoricalRates(): void
    {
        $currency = Currency::fromCode('USD');
        $startDate = new DateTimeImmutable('2024-01-01');
        $endDate = new DateTimeImmutable('2024-01-15');
        
        $key = $this->keyGenerator->forHistoricalRates($currency, $startDate, $endDate);
        
        $this->assertSame('fx_rate.history.USD.2024-01-01_2024-01-15', $key);
    }

    public function testForNbpTable(): void
    {
        $date = new DateTimeImmutable('2024-01-15');
        
        $key = $this->keyGenerator->forNbpTable('A', $date);
        
        $this->assertSame('nbp_api.table.A.2024-01-15', $key);
    }

    public function testForNbpTableNormalizesCase(): void
    {
        $date = new DateTimeImmutable('2024-01-15');
        
        $key = $this->keyGenerator->forNbpTable('a', $date);
        
        $this->assertSame('nbp_api.table.A.2024-01-15', $key);
    }

    public function testForNbpHistoricalRates(): void
    {
        $startDate = new DateTimeImmutable('2024-01-01');
        $endDate = new DateTimeImmutable('2024-01-15');
        
        $key = $this->keyGenerator->forNbpHistoricalRates('EUR', $startDate, $endDate);
        
        $this->assertSame('nbp_api.rates.EUR.2024-01-01_2024-01-15', $key);
    }

    public function testForNbpHistoricalRatesNormalizesCase(): void
    {
        $startDate = new DateTimeImmutable('2024-01-01');
        $endDate = new DateTimeImmutable('2024-01-15');
        
        $key = $this->keyGenerator->forNbpHistoricalRates('eur', $startDate, $endDate);
        
        $this->assertSame('nbp_api.rates.EUR.2024-01-01_2024-01-15', $key);
    }

    public function testForSupportedCurrencies(): void
    {
        $key = $this->keyGenerator->forSupportedCurrencies();
        
        $this->assertSame('app_meta.currencies.list.static', $key);
    }

    public function testForAppConfig(): void
    {
        $key = $this->keyGenerator->forAppConfig('quoting_rules');
        
        $this->assertSame('app_meta.config.quoting_rules.static', $key);
    }

    public function testForPattern(): void
    {
        $key = $this->keyGenerator->forPattern('fx_rate', 'single', 'EUR', '*');
        
        $this->assertSame('fx_rate.single.EUR.*', $key);
    }

    public function testForPatternWithWildcards(): void
    {
        $key = $this->keyGenerator->forPattern('fx_rate', '*', '*', '2024-01-15');
        
        $this->assertSame('fx_rate.*.*.2024-01-15', $key);
    }

    public function testParseKey(): void
    {
        $cacheKey = 'fx_rate.single.EUR.2024-01-15';
        
        $components = $this->keyGenerator->parseKey($cacheKey);
        
        $expected = [
            'prefix' => 'fx_rate',
            'type' => 'single',
            'identifier' => 'EUR',
            'date_or_range' => '2024-01-15'
        ];
        
        $this->assertSame($expected, $components);
    }

    public function testParseKeyWithInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid cache key format: invalid.key. Expected format: prefix.type.identifier.date_or_range');
        
        $this->keyGenerator->parseKey('invalid.key');
    }

    public function testBelongsToPrefix(): void
    {
        $this->assertTrue($this->keyGenerator->belongsToPrefix('fx_rate.single.EUR.2024-01-15', 'fx_rate'));
        $this->assertFalse($this->keyGenerator->belongsToPrefix('nbp_api.table.A.2024-01-15', 'fx_rate'));
    }

    public function testIsExchangeRateKey(): void
    {
        $this->assertTrue($this->keyGenerator->isExchangeRateKey('fx_rate.single.EUR.2024-01-15'));
        $this->assertTrue($this->keyGenerator->isExchangeRateKey('fx_rate.all.ALL.2024-01-15'));
        $this->assertTrue($this->keyGenerator->isExchangeRateKey('fx_rate.history.USD.2024-01-01_2024-01-15'));
        $this->assertFalse($this->keyGenerator->isExchangeRateKey('nbp_api.table.A.2024-01-15'));
        $this->assertFalse($this->keyGenerator->isExchangeRateKey('app_meta.currencies.list.static'));
    }

    public function testIsNbpApiKey(): void
    {
        $this->assertTrue($this->keyGenerator->isNbpApiKey('nbp_api.table.A.2024-01-15'));
        $this->assertTrue($this->keyGenerator->isNbpApiKey('nbp_api.rates.EUR.2024-01-01_2024-01-15'));
        $this->assertFalse($this->keyGenerator->isNbpApiKey('fx_rate.single.EUR.2024-01-15'));
        $this->assertFalse($this->keyGenerator->isNbpApiKey('app_meta.currencies.list.static'));
    }

    public function testIsAppMetadataKey(): void
    {
        $this->assertTrue($this->keyGenerator->isAppMetadataKey('app_meta.currencies.list.static'));
        $this->assertTrue($this->keyGenerator->isAppMetadataKey('app_meta.config.quoting_rules.static'));
        $this->assertFalse($this->keyGenerator->isAppMetadataKey('fx_rate.single.EUR.2024-01-15'));
        $this->assertFalse($this->keyGenerator->isAppMetadataKey('nbp_api.table.A.2024-01-15'));
    }

    public function testGetAllPrefixes(): void
    {
        $prefixes = $this->keyGenerator->getAllPrefixes();
        
        $expected = ['fx_rate', 'nbp_api', 'app_meta'];
        $this->assertSame($expected, $prefixes);
    }

    public function testValidateKeyComponentWithEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cache key component "prefix" cannot be empty');
        
        $this->keyGenerator->forPattern('', 'type', 'identifier', 'date');
    }

    public function testValidateKeyComponentWithInvalidCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cache key component "type" contains invalid characters: invalid@type');
        
        $this->keyGenerator->forPattern('prefix', 'invalid@type', 'identifier', 'date');
    }

    public function testValidKeyComponentsWithAllowedCharacters(): void
    {
        // Should not throw exception
        $key = $this->keyGenerator->forPattern('fx_rate', 'single-type', 'EUR_USD', '2024-01-15');
        
        $this->assertSame('fx_rate.single-type.EUR_USD.2024-01-15', $key);
    }

    public function testValidKeyComponentsWithWildcard(): void
    {
        // Wildcards should be allowed
        $key = $this->keyGenerator->forPattern('fx_rate', '*', 'EUR', '*');
        
        $this->assertSame('fx_rate.*.EUR.*', $key);
    }

    /**
     * Test that different currencies generate different keys
     */
    public function testDifferentCurrenciesGenerateDifferentKeys(): void
    {
        $date = new DateTimeImmutable('2024-01-15');
        $eur = Currency::fromCode('EUR');
        $usd = Currency::fromCode('USD');
        
        $eurKey = $this->keyGenerator->forSingleRate($eur, $date);
        $usdKey = $this->keyGenerator->forSingleRate($usd, $date);
        
        $this->assertNotSame($eurKey, $usdKey);
        $this->assertSame('fx_rate.single.EUR.2024-01-15', $eurKey);
        $this->assertSame('fx_rate.single.USD.2024-01-15', $usdKey);
    }

    /**
     * Test that different dates generate different keys
     */
    public function testDifferentDatesGenerateDifferentKeys(): void
    {
        $currency = Currency::fromCode('EUR');
        $date1 = new DateTimeImmutable('2024-01-15');
        $date2 = new DateTimeImmutable('2024-01-16');
        
        $key1 = $this->keyGenerator->forSingleRate($currency, $date1);
        $key2 = $this->keyGenerator->forSingleRate($currency, $date2);
        
        $this->assertNotSame($key1, $key2);
        $this->assertSame('fx_rate.single.EUR.2024-01-15', $key1);
        $this->assertSame('fx_rate.single.EUR.2024-01-16', $key2);
    }

    /**
     * Test key consistency - same inputs should always generate same key
     */
    public function testKeyConsistency(): void
    {
        $currency = Currency::fromCode('EUR');
        $date = new DateTimeImmutable('2024-01-15');
        
        $key1 = $this->keyGenerator->forSingleRate($currency, $date);
        $key2 = $this->keyGenerator->forSingleRate($currency, $date);
        
        $this->assertSame($key1, $key2);
    }

    /**
     * Test that historical rates with swapped dates generate different keys
     */
    public function testHistoricalRatesDateOrderMatters(): void
    {
        $currency = Currency::fromCode('EUR');
        $date1 = new DateTimeImmutable('2024-01-01');
        $date2 = new DateTimeImmutable('2024-01-15');
        
        $key1 = $this->keyGenerator->forHistoricalRates($currency, $date1, $date2);
        $key2 = $this->keyGenerator->forHistoricalRates($currency, $date2, $date1);
        
        $this->assertNotSame($key1, $key2);
        $this->assertSame('fx_rate.history.EUR.2024-01-01_2024-01-15', $key1);
        $this->assertSame('fx_rate.history.EUR.2024-01-15_2024-01-01', $key2);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Nbp;

use App\Domain\Currency\Currency;
use App\Domain\QuotingRules\QuotingRulesService;
use App\Infrastructure\Exception\NbpApiException;
use App\Infrastructure\Nbp\NbpHttpClient;
use App\Infrastructure\Nbp\NbpRateRepository;
use App\Infrastructure\Nbp\NbpTableResponse;
use App\Infrastructure\Nbp\NbpTableEntry;
use App\Infrastructure\Nbp\NbpSingleRateResponse;
use App\Infrastructure\Nbp\NbpRateEntry;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * NbpRateRepository Unit Tests
 * 
 * Tests Repository implementation with mocked NBP HTTP client.
 * Covers business logic, error handling, and fallback mechanisms.
 */
class NbpRateRepositoryTest extends TestCase
{
    private NbpRateRepository $repository;
    private MockObject $mockNbpClient;
    private QuotingRulesService $quotingRules;
    private Currency $eur;
    private Currency $czk;
    private DateTimeImmutable $testDate;

    protected function setUp(): void
    {
        $this->mockNbpClient = $this->createMock(NbpHttpClient::class);
        $this->quotingRules = new QuotingRulesService();
        $this->repository = new NbpRateRepository($this->mockNbpClient, $this->quotingRules);
        
        $this->eur = Currency::fromCode('EUR');
        $this->czk = Currency::fromCode('CZK');
        $this->testDate = new DateTimeImmutable('2024-01-15');
    }

    /**
     * Test: Find rate by currency and date - success
     */
    public function testFindByCurrencyAndDateSuccess(): void
    {
        // Given: Mock NBP response with EUR rate
        $mockTableResponse = $this->createMockTableResponse([
            ['currency' => 'euro', 'code' => 'EUR', 'mid' => 4.5000]
        ]);
        
        $this->mockNbpClient
            ->expects($this->once())
            ->method('getExchangeRatesTable')
            ->with($this->testDate)
            ->willReturn($mockTableResponse);
        
        // When: Finding EUR rate
        $rate = $this->repository->findByCurrencyAndDate($this->eur, $this->testDate);
        
        // Then: Rate is found and calculated correctly
        $this->assertNotNull($rate);
        $this->assertEquals($this->eur, $rate->getCurrency());
        $this->assertEquals($this->testDate, $rate->getDate());
        $this->assertEquals(4.5000, $rate->getMidRate());
        $this->assertEquals(4.3500, $rate->getBuyRate()); // 4.5000 - 0.15
        $this->assertEquals(4.6100, $rate->getSellRate()); // 4.5000 + 0.11
    }

    /**
     * Test: Find rate by currency and date - currency not found
     */
    public function testFindByCurrencyAndDateCurrencyNotFound(): void
    {
        // Given: Mock NBP response without EUR
        $mockTableResponse = $this->createMockTableResponse([
            ['currency' => 'dolar amerykański', 'code' => 'USD', 'mid' => 4.0000]
        ]);
        
        $this->mockNbpClient
            ->expects($this->once())
            ->method('getExchangeRatesTable')
            ->with($this->testDate)
            ->willReturn($mockTableResponse);
        
        // When: Finding EUR rate (not in response)
        $rate = $this->repository->findByCurrencyAndDate($this->eur, $this->testDate);
        
        // Then: Rate is not found
        $this->assertNull($rate);
    }

    /**
     * Test: Find rate with NBP API error - no fallback (simplified)
     */
    public function testFindByCurrencyAndDateWithNbpError(): void
    {
        // Given: NBP API throws "no data" exception
        $this->mockNbpClient
            ->expects($this->once())
            ->method('getExchangeRatesTable')
            ->with($this->testDate)
            ->willThrowException(NbpApiException::noDataAvailable('2024-01-15'));
        
        // When: Finding EUR rate
        // Then: Exception should be thrown (no fallback in this test)
        $this->expectException(NbpApiException::class);
        $this->repository->findByCurrencyAndDate($this->eur, $this->testDate);
    }

    /**
     * Test: Find all rates by date - success
     */
    public function testFindAllByDateSuccess(): void
    {
        // Given: Mock NBP response with multiple currencies
        $mockTableResponse = $this->createMockTableResponse([
            ['currency' => 'euro', 'code' => 'EUR', 'mid' => 4.5000],
            ['currency' => 'dolar amerykański', 'code' => 'USD', 'mid' => 4.0000],
            ['currency' => 'korona czeska', 'code' => 'CZK', 'mid' => 0.1850],
            ['currency' => 'rupia indonezyjska', 'code' => 'IDR', 'mid' => 0.0003],
            ['currency' => 'real brazylijski', 'code' => 'BRL', 'mid' => 0.8500],
        ]);
        
        $this->mockNbpClient
            ->expects($this->once())
            ->method('getExchangeRatesTable')
            ->with($this->testDate)
            ->willReturn($mockTableResponse);
        
        // When: Finding all rates
        $rates = $this->repository->findAllByDate($this->testDate);
        
        // Then: All supported currencies are returned
        $this->assertCount(5, $rates);
        $this->assertArrayHasKey('EUR', $rates);
        $this->assertArrayHasKey('USD', $rates);
        $this->assertArrayHasKey('CZK', $rates);
        $this->assertArrayHasKey('IDR', $rates);
        $this->assertArrayHasKey('BRL', $rates);
        
        // And: EUR rate is calculated correctly
        $eurRate = $rates['EUR'];
        $this->assertEquals(4.5000, $eurRate->getMidRate());
        $this->assertEquals(4.3500, $eurRate->getBuyRate());
        
        // And: CZK rate has no buy rate
        $czkRate = $rates['CZK'];
        $this->assertEquals(0.1850, $czkRate->getMidRate());
        $this->assertNull($czkRate->getBuyRate());
        $this->assertEquals(0.3850, $czkRate->getSellRate()); // 0.1850 + 0.20
    }

    /**
     * Test: Find historical rates - success
     */
    public function testFindHistoricalRatesSuccess(): void
    {
        // Given: Mock NBP historical response
        $startDate = new DateTimeImmutable('2024-01-10');
        $endDate = new DateTimeImmutable('2024-01-15');
        
        $mockResponse = $this->createMockSingleRateResponse('EUR', [
            ['no' => '010/A/NBP/2024', 'effectiveDate' => '2024-01-10', 'mid' => 4.4000],
            ['no' => '011/A/NBP/2024', 'effectiveDate' => '2024-01-11', 'mid' => 4.4500],
            ['no' => '012/A/NBP/2024', 'effectiveDate' => '2024-01-12', 'mid' => 4.5000],
        ]);
        
        $this->mockNbpClient
            ->expects($this->once())
            ->method('getHistoricalRates')
            ->with('EUR', $startDate, $endDate)
            ->willReturn($mockResponse);
        
        // When: Finding historical rates
        $rates = $this->repository->findHistoricalRates($this->eur, $startDate, $endDate);
        
        // Then: Rates are returned in correct order (newest first)
        $this->assertCount(3, $rates);
        $this->assertEquals(4.5000, $rates[0]->getMidRate()); // 2024-01-12 (newest)
        $this->assertEquals(4.4500, $rates[1]->getMidRate()); // 2024-01-11
        $this->assertEquals(4.4000, $rates[2]->getMidRate()); // 2024-01-10 (oldest)
    }

    /**
     * Test: Save rate throws exception (read-only repository)
     */
    public function testSaveRateThrowsException(): void
    {
        // Given: Any rate
        $rate = $this->quotingRules->createRateFromMid($this->eur, $this->testDate, 4.5000);
        
        // Expect: BadMethodCallException
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Cannot save rates to NBP API');
        
        // When: Trying to save rate
        $this->repository->save($rate);
    }

    /**
     * Test: Exists method - rate exists
     */
    public function testExistsRateExists(): void
    {
        // Given: Mock NBP response with EUR rate
        $mockTableResponse = $this->createMockTableResponse([
            ['currency' => 'euro', 'code' => 'EUR', 'mid' => 4.5000]
        ]);
        
        $this->mockNbpClient
            ->expects($this->once())
            ->method('getExchangeRatesTable')
            ->with($this->testDate)
            ->willReturn($mockTableResponse);
        
        // When: Checking if rate exists
        $exists = $this->repository->exists($this->eur, $this->testDate);
        
        // Then: Rate exists
        $this->assertTrue($exists);
    }

    /**
     * Test: Exists method - rate does not exist
     */
    public function testExistsRateDoesNotExist(): void
    {
        // Given: NBP API throws exception
        $this->mockNbpClient
            ->expects($this->once())
            ->method('getExchangeRatesTable')
            ->with($this->testDate)
            ->willThrowException(NbpApiException::noDataAvailable('2024-01-15'));
        
        // When: Checking if rate exists
        $exists = $this->repository->exists($this->eur, $this->testDate);
        
        // Then: Rate does not exist
        $this->assertFalse($exists);
    }

    /**
     * Create mock NBP table response
     * 
     * @param array<array{currency: string, code: string, mid: float}> $rates
     */
    private function createMockTableResponse(array $rates): NbpTableResponse
    {
        $mockEntries = array_map(
            fn(array $rate) => new NbpTableEntry($rate['currency'], $rate['code'], $rate['mid']),
            $rates
        );
        
        return new NbpTableResponse(
            'A',
            '010/A/NBP/2024',
            $this->testDate,
            $mockEntries
        );
    }

    /**
     * Create mock NBP single rate response
     * 
     * @param string $currencyCode
     * @param array<array{no: string, effectiveDate: string, mid: float}> $rates
     */
    private function createMockSingleRateResponse(string $currencyCode, array $rates): NbpSingleRateResponse
    {
        $mockEntries = array_map(
            fn(array $rate) => new NbpRateEntry(
                $rate['no'],
                new DateTimeImmutable($rate['effectiveDate']),
                $rate['mid']
            ),
            $rates
        );
        
        return new NbpSingleRateResponse(
            'A',
            'euro',
            $currencyCode,
            $mockEntries
        );
    }
}

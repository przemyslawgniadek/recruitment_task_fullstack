<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Cache;

use App\Domain\Currency\Currency;
use App\Domain\Rate\Rate;
use App\Domain\Rate\RateRepository;
use App\Infrastructure\Cache\CacheWarmer;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for CacheWarmer
 * 
 * Tests cache warming strategies, error handling, and statistics collection.
 * Uses mocked repository to simulate different scenarios.
 */
final class CacheWarmerTest extends TestCase
{
    private RateRepository $repository;
    private LoggerInterface $logger;
    private CacheWarmer $cacheWarmer;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(RateRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        
        $this->cacheWarmer = new CacheWarmer($this->repository, $this->logger);
    }

    public function testWarmCurrentRatesSuccess(): void
    {
        $date = new DateTimeImmutable('2024-01-15');
        $rates = [
            'EUR' => new Rate(Currency::fromCode('EUR'), $date, 4.5000, 4.3500, 4.6100),
            'USD' => new Rate(Currency::fromCode('USD'), $date, 3.8000, 3.6500, 3.9100)
        ];
        
        $this->repository
            ->expects($this->once())
            ->method('findAllByDate')
            ->with($date)
            ->willReturn($rates);
        
        $this->logger
            ->expects($this->exactly(2))
            ->method('info')
            ->withConsecutive(
                ['Starting cache warming for current rates', ['date' => '2024-01-15']],
                ['Successfully warmed all current rates', ['date' => '2024-01-15', 'count' => 2]]
            );
        
        $result = $this->cacheWarmer->warmCurrentRates($date);
        
        $expected = [
            'all_rates' => [
                'status' => 'success',
                'date' => '2024-01-15',
                'count' => 2,
                'currencies' => ['EUR', 'USD']
            ]
        ];
        
        $this->assertSame($expected, $result);
    }

    public function testWarmCurrentRatesNoData(): void
    {
        $date = new DateTimeImmutable('2024-01-15');
        
        $this->repository
            ->expects($this->once())
            ->method('findAllByDate')
            ->with($date)
            ->willReturn([]);
        
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('No current rates available for warming', ['date' => '2024-01-15']);
        
        $result = $this->cacheWarmer->warmCurrentRates($date);
        
        $expected = [
            'all_rates' => [
                'status' => 'no_data',
                'date' => '2024-01-15',
                'message' => 'No rates available for date'
            ]
        ];
        
        $this->assertSame($expected, $result);
    }

    public function testWarmCurrentRatesError(): void
    {
        $date = new DateTimeImmutable('2024-01-15');
        $exception = new \RuntimeException('NBP API error');
        
        $this->repository
            ->expects($this->once())
            ->method('findAllByDate')
            ->with($date)
            ->willThrowException($exception);
        
        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Failed to warm current rates', [
                'date' => '2024-01-15',
                'error' => 'NBP API error'
            ]);
        
        $result = $this->cacheWarmer->warmCurrentRates($date);
        
        $expected = [
            'all_rates' => [
                'status' => 'error',
                'date' => '2024-01-15',
                'error' => 'NBP API error'
            ]
        ];
        
        $this->assertSame($expected, $result);
    }

    public function testWarmCurrentRatesUsesTodayByDefault(): void
    {
        $today = new DateTimeImmutable();
        
        $this->repository
            ->expects($this->once())
            ->method('findAllByDate')
            ->with($this->callback(function (DateTimeImmutable $date) use ($today) {
                return $date->format('Y-m-d') === $today->format('Y-m-d');
            }))
            ->willReturn([]);
        
        $this->cacheWarmer->warmCurrentRates();
    }

    public function testWarmHistoricalRatesSuccess(): void
    {
        $currency = Currency::fromCode('EUR');
        $endDate = new DateTimeImmutable('2024-01-15');
        $startDate = $endDate->modify('-14 days');
        $rates = [
            new Rate($currency, $endDate, 4.5000, 4.3500, 4.6100),
            new Rate($currency, $endDate->modify('-1 day'), 4.4800, 4.3300, 4.5900)
        ];
        
        $this->repository
            ->expects($this->once())
            ->method('findHistoricalRates')
            ->with($currency, $startDate, $endDate)
            ->willReturn($rates);
        
        $this->logger
            ->expects($this->exactly(2))
            ->method('info')
            ->withConsecutive(
                ['Starting cache warming for historical rates', [
                    'currency' => 'EUR',
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => '2024-01-15',
                    'days' => 14
                ]],
                ['Successfully warmed historical rates', $this->isType('array')]
            );
        
        $result = $this->cacheWarmer->warmHistoricalRates($currency, 14, $endDate);
        
        $this->assertSame('success', $result['status']);
        $this->assertSame('EUR', $result['currency']);
        $this->assertSame($startDate->format('Y-m-d'), $result['start_date']);
        $this->assertSame('2024-01-15', $result['end_date']);
        $this->assertSame(14, $result['requested_days']);
        $this->assertSame(2, $result['actual_count']);
        $this->assertSame(1, $result['attempt']);
    }

    public function testWarmHistoricalRatesNoData(): void
    {
        $currency = Currency::fromCode('EUR');
        $endDate = new DateTimeImmutable('2024-01-15');
        $startDate = $endDate->modify('-14 days');
        
        $this->repository
            ->expects($this->once())
            ->method('findHistoricalRates')
            ->with($currency, $startDate, $endDate)
            ->willReturn([]);
        
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('No historical rates available for warming', $this->isType('array'));
        
        $result = $this->cacheWarmer->warmHistoricalRates($currency, 14, $endDate);
        
        $this->assertSame('no_data', $result['status']);
        $this->assertSame('No historical rates available for period', $result['message']);
    }

    public function testWarmHistoricalRatesWithRetry(): void
    {
        $currency = Currency::fromCode('EUR');
        $endDate = new DateTimeImmutable('2024-01-15');
        $startDate = $endDate->modify('-14 days');
        $rates = [new Rate($currency, $endDate, 4.5000, 4.3500, 4.6100)];
        
        $this->repository
            ->expects($this->exactly(2))
            ->method('findHistoricalRates')
            ->with($currency, $startDate, $endDate)
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new \RuntimeException('Temporary error')),
                $rates
            );
        
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('Failed to warm historical rates', $this->isType('array'));
        
        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');
        
        $result = $this->cacheWarmer->warmHistoricalRates($currency, 14, $endDate);
        
        $this->assertSame('success', $result['status']);
        $this->assertSame(2, $result['attempt']);
    }

    public function testWarmHistoricalRatesMaxRetriesExceeded(): void
    {
        $currency = Currency::fromCode('EUR');
        $endDate = new DateTimeImmutable('2024-01-15');
        $startDate = $endDate->modify('-14 days');
        $exception = new \RuntimeException('Persistent error');
        
        $this->repository
            ->expects($this->exactly(3)) // MAX_RETRY_ATTEMPTS = 3
            ->method('findHistoricalRates')
            ->with($currency, $startDate, $endDate)
            ->willThrowException($exception);
        
        $this->logger
            ->expects($this->exactly(3))
            ->method('warning')
            ->with('Failed to warm historical rates', $this->isType('array'));
        
        $result = $this->cacheWarmer->warmHistoricalRates($currency, 14, $endDate);
        
        $this->assertSame('error', $result['status']);
        $this->assertSame('Persistent error', $result['error']);
        $this->assertSame(3, $result['attempts']);
    }

    public function testWarmAllCurrenciesSuccess(): void
    {
        $date = new DateTimeImmutable('2024-01-15');
        
        // Mock current rates warming
        $currentRates = [
            'EUR' => new Rate(Currency::fromCode('EUR'), $date, 4.5000, 4.3500, 4.6100),
            'USD' => new Rate(Currency::fromCode('USD'), $date, 3.8000, 3.6500, 3.9100)
        ];
        
        $this->repository
            ->expects($this->once())
            ->method('findAllByDate')
            ->with($date)
            ->willReturn($currentRates);
        
        // Mock historical rates warming for each currency
        $historicalRates = [
            new Rate(Currency::fromCode('EUR'), $date, 4.5000, 4.3500, 4.6100)
        ];
        
        $this->repository
            ->expects($this->exactly(5)) // 5 supported currencies
            ->method('findHistoricalRates')
            ->willReturn($historicalRates);
        
        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');
        
        $result = $this->cacheWarmer->warmAllCurrencies(14, $date);
        
        $this->assertArrayHasKey('current', $result);
        $this->assertArrayHasKey('historical', $result);
        $this->assertArrayHasKey('summary', $result);
        
        $summary = $result['summary'];
        $this->assertIsFloat($summary['duration_seconds']);
        $this->assertSame(6, $summary['operations_total']); // 1 current + 5 historical
        $this->assertSame(6, $summary['operations_success']);
        $this->assertSame(0, $summary['operations_error']);
        $this->assertSame(100.0, $summary['success_rate']);
        $this->assertSame(5, $summary['currencies_processed']);
        $this->assertSame(14, $summary['history_days']);
    }

    public function testWarmBusinessDaysFallbackSuccess(): void
    {
        $date = new DateTimeImmutable('2024-01-15'); // Monday
        
        // Mock rates for some fallback dates
        $this->repository
            ->expects($this->exactly(7)) // 7 days back
            ->method('findAllByDate')
            ->willReturnOnConsecutiveCalls(
                ['EUR' => new Rate(Currency::fromCode('EUR'), $date->modify('-1 day'), 4.5000, 4.3500, 4.6100)], // Sunday - has data
                [], // Saturday - no data
                ['EUR' => new Rate(Currency::fromCode('EUR'), $date->modify('-3 days'), 4.4800, 4.3300, 4.5900)], // Friday - has data
                [], // Thursday - no data
                [], // Wednesday - no data
                [], // Tuesday - no data
                []  // Monday - no data
            );
        
        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Starting business days fallback warming', ['reference_date' => '2024-01-15']);
        
        $result = $this->cacheWarmer->warmBusinessDaysFallback($date);
        
        $this->assertCount(7, $result);
        
        // Check that we have results for all 7 days
        foreach ($result as $dateKey => $dayResult) {
            $this->assertArrayHasKey('status', $dayResult);
            $this->assertArrayHasKey('date', $dayResult);
            $this->assertArrayHasKey('day_name', $dayResult);
            $this->assertArrayHasKey('count', $dayResult);
            $this->assertArrayHasKey('days_back', $dayResult);
        }
    }

    public function testGetWarmingStatusSuccess(): void
    {
        $today = new DateTimeImmutable();
        $rates = [
            'EUR' => new Rate(Currency::fromCode('EUR'), $today, 4.5000, 4.3500, 4.6100),
            'USD' => new Rate(Currency::fromCode('USD'), $today, 3.8000, 3.6500, 3.9100)
        ];
        
        $this->repository
            ->expects($this->once())
            ->method('findAllByDate')
            ->with($this->callback(function (DateTimeImmutable $date) use ($today) {
                return $date->format('Y-m-d') === $today->format('Y-m-d');
            }))
            ->willReturn($rates);
        
        $result = $this->cacheWarmer->getWarmingStatus();
        
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('current_rates', $result);
        $this->assertArrayHasKey('supported_currencies', $result);
        
        $currentRates = $result['current_rates'];
        $this->assertTrue($currentRates['available']);
        $this->assertSame(2, $currentRates['count']);
        $this->assertSame(['EUR', 'USD'], $currentRates['currencies']);
        
        $supportedCurrencies = $result['supported_currencies'];
        $this->assertCount(5, $supportedCurrencies); // EUR, USD, CZK, IDR, BRL
        
        // Check EUR currency details
        $eurCurrencies = array_filter($supportedCurrencies, fn($c) => $c['code'] === 'EUR');
        $eurCurrency = array_values($eurCurrencies)[0];
        $this->assertTrue($eurCurrency['supports_buying']);
        $this->assertSame(-0.15, $eurCurrency['buy_margin']); // Buy margin is negative (discount)
        $this->assertSame(0.11, $eurCurrency['sell_margin']);
        
        // Check CZK currency details (no buying support)
        $czkCurrencies = array_filter($supportedCurrencies, fn($c) => $c['code'] === 'CZK');
        $czkCurrency = array_values($czkCurrencies)[0];
        $this->assertFalse($czkCurrency['supports_buying']);
        $this->assertNull($czkCurrency['buy_margin']);
        $this->assertSame(0.2, $czkCurrency['sell_margin']);
    }

    public function testGetWarmingStatusError(): void
    {
        $exception = new \RuntimeException('Repository error');
        
        $this->repository
            ->expects($this->once())
            ->method('findAllByDate')
            ->willThrowException($exception);
        
        $result = $this->cacheWarmer->getWarmingStatus();
        
        $currentRates = $result['current_rates'];
        $this->assertFalse($currentRates['available']);
        $this->assertSame('Repository error', $currentRates['error']);
        
        // Supported currencies should still be available
        $this->assertArrayHasKey('supported_currencies', $result);
        $this->assertCount(5, $result['supported_currencies']);
    }

    public function testWarmHistoricalRatesUsesTodayByDefault(): void
    {
        $currency = Currency::fromCode('EUR');
        $today = new DateTimeImmutable();
        $expectedStartDate = $today->modify('-14 days');
        
        $this->repository
            ->expects($this->once())
            ->method('findHistoricalRates')
            ->with(
                $currency,
                $this->callback(function (DateTimeImmutable $date) use ($expectedStartDate) {
                    return $date->format('Y-m-d') === $expectedStartDate->format('Y-m-d');
                }),
                $this->callback(function (DateTimeImmutable $date) use ($today) {
                    return $date->format('Y-m-d') === $today->format('Y-m-d');
                })
            )
            ->willReturn([]);
        
        $this->cacheWarmer->warmHistoricalRates($currency);
    }

    public function testWarmAllCurrenciesUsesTodayByDefault(): void
    {
        $today = new DateTimeImmutable();
        
        $this->repository
            ->expects($this->once())
            ->method('findAllByDate')
            ->with($this->callback(function (DateTimeImmutable $date) use ($today) {
                return $date->format('Y-m-d') === $today->format('Y-m-d');
            }))
            ->willReturn([]);
        
        // Mock historical rates calls
        $this->repository
            ->expects($this->exactly(5))
            ->method('findHistoricalRates')
            ->willReturn([]);
        
        $this->cacheWarmer->warmAllCurrencies();
    }

    public function testWarmBusinessDaysFallbackUsesTodayByDefault(): void
    {
        $today = new DateTimeImmutable();
        
        // Should call findAllByDate for 7 previous days
        $this->repository
            ->expects($this->exactly(7))
            ->method('findAllByDate')
            ->willReturn([]);
        
        $this->cacheWarmer->warmBusinessDaysFallback();
    }
}

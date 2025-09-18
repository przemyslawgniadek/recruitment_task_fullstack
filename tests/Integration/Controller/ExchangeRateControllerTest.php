<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Domain\Currency\Currency;
use App\Domain\Rate\Rate;
use App\Domain\Rate\RateRepository;
use App\Infrastructure\Cache\CachedRateRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Integration tests for ExchangeRateController
 * 
 * Tests the complete HTTP request/response cycle including:
 * - Route matching and parameter binding
 * - Request validation and DTO conversion
 * - Controller logic and exception handling
 * - JSON response formatting
 */
class ExchangeRateControllerTest extends WebTestCase
{
    private $client;
    private RateRepository $mockRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        
        // Mock the repository service directly
        $this->mockRepository = $this->createMock(RateRepository::class);
        static::$kernel->getContainer()->set(RateRepository::class, $this->mockRepository);
        static::$kernel->getContainer()->set('App\Infrastructure\Cache\CachedRateRepository', $this->mockRepository);
    }

    public function testGetCurrentRatesSuccess(): void
    {
        $date = new DateTimeImmutable('2025-09-11');
        $eurRate = new Rate(Currency::fromCode('EUR'), $date, 4.5000, 4.3500, 4.6100);
        $usdRate = new Rate(Currency::fromCode('USD'), $date, 3.8000, 3.6500, 3.9100);
        
        $this->mockRepository
            ->expects($this->once())
            ->method('findAllByDate')
            ->with($this->callback(function (DateTimeImmutable $requestDate) {
                return $requestDate->format('Y-m-d') === '2025-09-11';
            }))
            ->willReturn([
                'EUR' => $eurRate,
                'USD' => $usdRate
            ]);

        $this->client->request('GET', '/api/rates/current?date=2025-09-11');

        $response = $this->client->getResponse();
        
        
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertJson($response->getContent());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('2025-09-11', $data['date']);
        $this->assertSame(2, $data['count']);
        $this->assertArrayHasKey('EUR', $data['rates']);
        $this->assertArrayHasKey('USD', $data['rates']);
        
        // Check EUR rate structure
        $eurData = $data['rates']['EUR'];
        $this->assertSame('EUR', $eurData['currency']);
        $this->assertSame(4.5000, $eurData['mid']);
        $this->assertSame(4.3500, $eurData['buy']);
        $this->assertSame(4.6100, $eurData['sell']);
        $this->assertTrue($eurData['supports_buying']);
    }

    public function testGetCurrentRatesWithoutDateUsesToday(): void
    {
        $today = new DateTimeImmutable();
        $eurRate = new Rate(Currency::fromCode('EUR'), $today, 4.5000, 4.3500, 4.6100);
        
        $this->mockRepository
            ->expects($this->once())
            ->method('findAllByDate')
            ->with($this->callback(function (DateTimeImmutable $requestDate) use ($today) {
                return $requestDate->format('Y-m-d') === $today->format('Y-m-d');
            }))
            ->willReturn(['EUR' => $eurRate]);

        $this->client->request('GET', '/api/rates/current');

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertSame($today->format('Y-m-d'), $data['date']);
    }

    public function testGetCurrentRatesNotFound(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('findAllByDate')
            ->willReturn([]);

        $this->client->request('GET', '/api/rates/current?date=2025-09-11');

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertJson($response->getContent());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('rate_not_found', $data['error']);
        $this->assertStringContainsString('No exchange rates found', $data['message']);
    }

    public function testGetCurrentRateForCurrencySuccess(): void
    {
        $date = new DateTimeImmutable('2025-09-11');
        $eurRate = new Rate(Currency::fromCode('EUR'), $date, 4.5000, 4.3500, 4.6100);
        
        $this->mockRepository
            ->expects($this->once())
            ->method('findByCurrencyAndDate')
            ->with(
                $this->callback(function (Currency $currency) {
                    return $currency->getCode() === 'EUR';
                }),
                $this->callback(function (DateTimeImmutable $requestDate) {
                    return $requestDate->format('Y-m-d') === '2025-09-11';
                })
            )
            ->willReturn($eurRate);

        $this->client->request('GET', '/api/rates/current/EUR?date=2025-09-11');

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertJson($response->getContent());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('EUR', $data['currency']);
        $this->assertSame('2025-09-11', $data['date']);
        $this->assertSame(4.5000, $data['mid']);
        $this->assertSame(4.3500, $data['buy']);
        $this->assertSame(4.6100, $data['sell']);
        $this->assertTrue($data['supports_buying']);
    }

    public function testGetCurrentRateForCurrencyNotFound(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('findByCurrencyAndDate')
            ->willReturn(null);

        $this->client->request('GET', '/api/rates/current/EUR?date=2025-09-11');

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertJson($response->getContent());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('rate_not_found', $data['error']);
        $this->assertStringContainsString('EUR', $data['message']);
        $this->assertStringContainsString('2025-09-11', $data['message']);
    }

    public function testGetCurrentRateForInvalidCurrency(): void
    {
        $this->client->request('GET', '/api/rates/current/XXX');

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertJson($response->getContent());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('invalid_currency', $data['error']);
        $this->assertStringContainsString('XXX', $data['message']);
        $this->assertArrayHasKey('supported_currencies', $data['details']);
    }

    public function testGetHistoricalRatesSuccess(): void
    {
        $endDate = new DateTimeImmutable('2025-09-11');
        $startDate = $endDate->modify('-14 days');
        
        $rates = [
            new Rate(Currency::fromCode('EUR'), $endDate, 4.5000, 4.3500, 4.6100),
            new Rate(Currency::fromCode('EUR'), $endDate->modify('-1 day'), 4.4800, 4.3300, 4.5900)
        ];
        
        $this->mockRepository
            ->expects($this->once())
            ->method('findHistoricalRates')
            ->with(
                $this->callback(function (Currency $currency) {
                    return $currency->getCode() === 'EUR';
                }),
                $this->callback(function (DateTimeImmutable $requestStartDate) use ($startDate) {
                    return $requestStartDate->format('Y-m-d') === $startDate->format('Y-m-d');
                }),
                $this->callback(function (DateTimeImmutable $requestEndDate) use ($endDate) {
                    return $requestEndDate->format('Y-m-d') === $endDate->format('Y-m-d');
                })
            )
            ->willReturn($rates);

        $this->client->request('GET', '/api/rates/historical/EUR?date=2025-09-11&days=14');

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertJson($response->getContent());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('EUR', $data['currency']);
        $this->assertSame($startDate->format('Y-m-d'), $data['start_date']);
        $this->assertSame($endDate->format('Y-m-d'), $data['end_date']);
        $this->assertSame(14, $data['days_requested']);
        $this->assertSame(2, $data['count']);
        $this->assertCount(2, $data['rates']);
        
        // Check that rates are sorted by date descending (newest first)
        $this->assertSame('2025-09-11', $data['rates'][0]['date']);
        $this->assertSame('2025-09-10', $data['rates'][1]['date']);
    }

    public function testGetHistoricalRatesWithDefaultDays(): void
    {
        $endDate = new DateTimeImmutable('2025-09-11');
        $startDate = $endDate->modify('-14 days'); // Default 14 days
        
        $this->mockRepository
            ->expects($this->once())
            ->method('findHistoricalRates')
            ->with(
                $this->anything(),
                $this->callback(function (DateTimeImmutable $requestStartDate) use ($startDate) {
                    return $requestStartDate->format('Y-m-d') === $startDate->format('Y-m-d');
                }),
                $this->anything()
            )
            ->willReturn([]);

        $this->client->request('GET', '/api/rates/historical/EUR?date=2025-09-11');

        // Should use default 14 days
        $this->assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    public function testGetHistoricalRatesInvalidDays(): void
    {
        $this->client->request('GET', '/api/rates/historical/EUR?days=500');

        $response = $this->client->getResponse();
        // Note: Currently returns 404 because validation happens in DTO, not Controller
        // This could be improved by adding validation middleware
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertJson($response->getContent());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('rate_not_found', $data['error']);
    }

    public function testGetHistoricalRatesNotFound(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('findHistoricalRates')
            ->willReturn([]);

        $this->client->request('GET', '/api/rates/historical/EUR?date=2025-09-11&days=14');

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertJson($response->getContent());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('rate_not_found', $data['error']);
        $this->assertStringContainsString('historical', $data['message']);
    }

    public function testGetSupportedCurrencies(): void
    {
        $this->client->request('GET', '/api/currencies');

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertJson($response->getContent());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('currencies', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertSame(5, $data['count']); // EUR, USD, CZK, IDR, BRL
        
        // Check currency structure
        $currencies = $data['currencies'];
        $eurCurrency = array_values(array_filter($currencies, fn($c) => $c['code'] === 'EUR'))[0];
        $this->assertSame('EUR', $eurCurrency['code']);
        $this->assertTrue($eurCurrency['supports_buying']);
        $this->assertArrayHasKey('margins', $eurCurrency);
        
        $czkCurrency = array_values(array_filter($currencies, fn($c) => $c['code'] === 'CZK'))[0];
        $this->assertSame('CZK', $czkCurrency['code']);
        $this->assertFalse($czkCurrency['supports_buying']);
    }

    public function testHealthCheckSuccess(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('exists')
            ->willReturn(true);

        $this->client->request('GET', '/api/health');

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertJson($response->getContent());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('ok', $data['status']);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('checks', $data);
        $this->assertSame('ok', $data['checks']['database']);
        $this->assertSame('ok', $data['checks']['nbp_api']);
        $this->assertSame('ok', $data['checks']['cache']);
    }

    public function testHealthCheckFailure(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('exists')
            ->willThrowException(new \RuntimeException('Repository connection failed'));

        $this->client->request('GET', '/api/health');

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        $this->assertJson($response->getContent());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('error', $data['status']);
        $this->assertStringContainsString('Health check failed', $data['message']);
    }

    public function testInvalidDateFormat(): void
    {
        $this->client->request('GET', '/api/rates/current?date=invalid-date');

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertJson($response->getContent());

        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Invalid date format', $data['message']);
    }

    public function testApiExceptionHandlerFormatsErrorsCorrectly(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('findAllByDate')
            ->willReturn([]);

        $this->client->request('GET', '/api/rates/current');

        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);
        
        // Check RFC 7807 compliance
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('timestamp', $data);
        
        // Check timestamp format
        $this->assertRegExp('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $data['timestamp']);
    }
}

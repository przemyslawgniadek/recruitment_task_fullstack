<?php

declare(strict_types=1);

namespace App\Infrastructure\Nbp;

use App\Infrastructure\Exception\NbpApiException;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

/**
 * NBP HTTP Client
 * 
 * HTTP client for communicating with NBP (National Bank of Poland) API.
 * Handles all HTTP requests, response parsing, and error handling.
 * 
 * NBP API Documentation: https://api.nbp.pl/
 */
class NbpHttpClient
{
    private const BASE_URL = 'https://api.nbp.pl/api';
    private const TABLE_A = 'A'; // Average exchange rates table
    private const FORMAT_JSON = 'json';
    
    private Client $httpClient;

    public function __construct(?Client $httpClient = null)
    {
        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => self::BASE_URL,
            'timeout' => 10.0,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Telemedi-FX-Desk/1.0',
            ],
        ]);
    }

    /**
     * Get exchange rates table for specific date
     * 
     * @throws NbpApiException
     */
    public function getExchangeRatesTable(?DateTimeImmutable $date = null): NbpTableResponse
    {
        $url = $this->buildTableUrl($date);
        
        try {
            $response = $this->httpClient->get($url);
            $data = $this->parseJsonResponse($response, $url);
            
            return NbpTableResponse::fromArray($data);
        } catch (GuzzleException $e) {
            throw NbpApiException::requestFailed($url, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            throw NbpApiException::invalidResponse($url, $e->getMessage());
        }
    }

    /**
     * Get exchange rate for specific currency and date
     * 
     * @throws NbpApiException
     */
    public function getExchangeRate(string $currencyCode, ?DateTimeImmutable $date = null): NbpSingleRateResponse
    {
        $this->validateCurrencyCode($currencyCode);
        $url = $this->buildSingleRateUrl($currencyCode, $date);
        
        try {
            $response = $this->httpClient->get($url);
            $data = $this->parseJsonResponse($response, $url);
            
            return NbpSingleRateResponse::fromArray($data);
        } catch (GuzzleException $e) {
            throw NbpApiException::requestFailed($url, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            throw NbpApiException::invalidResponse($url, $e->getMessage());
        }
    }

    /**
     * Get historical exchange rates for currency within date range
     * 
     * @throws NbpApiException
     */
    public function getHistoricalRates(
        string $currencyCode,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate
    ): NbpSingleRateResponse {
        $this->validateCurrencyCode($currencyCode);
        $this->validateDateRange($startDate, $endDate);
        
        $url = $this->buildHistoricalRatesUrl($currencyCode, $startDate, $endDate);
        
        try {
            $response = $this->httpClient->get($url);
            $data = $this->parseJsonResponse($response, $url);
            
            return NbpSingleRateResponse::fromArray($data);
        } catch (GuzzleException $e) {
            throw NbpApiException::requestFailed($url, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            throw NbpApiException::invalidResponse($url, $e->getMessage());
        }
    }

    /**
     * Check if NBP has data for specific date
     * 
     * @throws NbpApiException
     */
    public function hasDataForDate(DateTimeImmutable $date): bool
    {
        try {
            $this->getExchangeRatesTable($date);
            return true;
        } catch (NbpApiException $e) {
            // If it's a "no data" error, return false
            // If it's a different error, re-throw
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'No data')) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Build URL for exchange rates table
     */
    private function buildTableUrl(?DateTimeImmutable $date): string
    {
        $path = sprintf('/exchangerates/tables/%s/', self::TABLE_A);
        
        if ($date !== null) {
            $path .= $date->format('Y-m-d') . '/';
        }
        
        return $path . '?format=' . self::FORMAT_JSON;
    }

    /**
     * Build URL for single currency rate
     */
    private function buildSingleRateUrl(string $currencyCode, ?DateTimeImmutable $date): string
    {
        $path = sprintf('/exchangerates/rates/%s/%s/', self::TABLE_A, strtoupper($currencyCode));
        
        if ($date !== null) {
            $path .= $date->format('Y-m-d') . '/';
        }
        
        return $path . '?format=' . self::FORMAT_JSON;
    }

    /**
     * Build URL for historical rates
     */
    private function buildHistoricalRatesUrl(
        string $currencyCode,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate
    ): string {
        $path = sprintf(
            '/exchangerates/rates/%s/%s/%s/%s/',
            self::TABLE_A,
            strtoupper($currencyCode),
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );
        
        return $path . '?format=' . self::FORMAT_JSON;
    }

    /**
     * Parse JSON response from NBP API
     * 
     * @return array<mixed>
     * @throws NbpApiException
     */
    private function parseJsonResponse(ResponseInterface $response, string $url): array
    {
        $statusCode = $response->getStatusCode();
        
        if ($statusCode === 404) {
            throw NbpApiException::noDataAvailable($url);
        }
        
        if ($statusCode !== 200) {
            throw NbpApiException::requestFailed($url, "HTTP {$statusCode}");
        }
        
        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw NbpApiException::invalidResponse($url, 'Invalid JSON: ' . json_last_error_msg());
        }
        
        if (!is_array($data)) {
            throw NbpApiException::invalidResponse($url, 'Expected JSON array');
        }
        
        return $data;
    }

    /**
     * Validate currency code
     */
    private function validateCurrencyCode(string $currencyCode): void
    {
        if (empty($currencyCode) || strlen($currencyCode) !== 3) {
            throw new InvalidArgumentException('Currency code must be 3 characters long');
        }
    }

    /**
     * Validate date range
     */
    private function validateDateRange(DateTimeImmutable $startDate, DateTimeImmutable $endDate): void
    {
        if ($startDate > $endDate) {
            throw new InvalidArgumentException('Start date cannot be after end date');
        }
        
        $daysDiff = $endDate->diff($startDate)->days;
        if ($daysDiff > 93) { // NBP API limit
            throw new InvalidArgumentException('Date range cannot exceed 93 days');
        }
    }
}

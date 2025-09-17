/**
 * API Types for Telemedi FX Desk
 * 
 * Type-safe definitions for all API responses from backend.
 * These types match the PHP DTOs from backend implementation.
 */

// ============================================================================
// CURRENCY TYPES
// ============================================================================

/**
 * Supported currency codes
 */
export type CurrencyCode = 'EUR' | 'USD' | 'CZK' | 'IDR' | 'BRL';

/**
 * Currency information
 */
export interface Currency {
  code: CurrencyCode;
  name: string;
  supportsBuying: boolean;
  buyMargin?: number;
  sellMargin: number;
}

// ============================================================================
// RATE TYPES
// ============================================================================

/**
 * Single exchange rate
 * Matches RateResponse DTO from backend
 */
export interface Rate {
  currency: CurrencyCode; // Changed from 'code' to match API
  date: string; // ISO date string (YYYY-MM-DD)
  mid: number;  // NBP middle rate
  buy: number | null; // Buy rate (null for CZK, IDR, BRL)
  sell: number; // Sell rate
  supports_buying: boolean; // Whether currency supports buying
  margins: {
    buy: number | null; // Buy margin (null if no buying)
    sell: number; // Sell margin
  };
}

/**
 * Collection of current rates
 * Matches RatesCollectionResponse DTO from backend
 */
export interface CurrentRatesResponse {
  date: string; // ISO date string
  rates: Record<CurrencyCode, Rate>;
  count: number;
}

/**
 * Historical rates response
 * Matches RatesCollectionResponse DTO for historical endpoint
 */
export interface HistoricalRatesResponse {
  currency: CurrencyCode;
  startDate: string; // ISO date string
  endDate: string;   // ISO date string
  daysRequested: number;
  rates: Rate[];
  count: number;
}

/**
 * Supported currencies response
 * Matches CurrenciesCollectionResponse DTO from backend
 */
export interface CurrenciesResponse {
  currencies: Currency[];
  count: number;
}

// ============================================================================
// ERROR TYPES (RFC 7807)
// ============================================================================

/**
 * API Error Response (RFC 7807 Problem Details)
 * Matches ErrorResponse DTO from backend
 */
export interface ApiError {
  error: string;    // Error type (e.g., "rate_not_found")
  message: string;  // Human-readable error message
  details: Record<string, any>; // Additional error context
  timestamp: string; // ISO timestamp
}

/**
 * Specific error types
 */
export type ApiErrorType = 
  | 'rate_not_found'
  | 'invalid_currency'
  | 'validation_error'
  | 'service_unavailable'
  | 'internal_server_error';

// ============================================================================
// REQUEST TYPES
// ============================================================================

/**
 * Current rates request parameters
 */
export interface CurrentRatesRequest {
  date?: string; // Optional date (YYYY-MM-DD), defaults to today
}

/**
 * Historical rates request parameters
 */
export interface HistoricalRatesRequest {
  currency: CurrencyCode;
  date?: string; // Optional date (YYYY-MM-DD), defaults to today
  days?: number; // Number of days (1-365), defaults to 14
}

// ============================================================================
// HEALTH CHECK TYPES
// ============================================================================

/**
 * Health check response
 */
export interface HealthResponse {
  status: 'ok' | 'error';
  timestamp: string;
  checks: {
    database?: 'ok' | 'error';
    nbp_api?: 'ok' | 'error';
    cache?: 'ok' | 'error';
  };
  message?: string; // Present if status is 'error'
}

// ============================================================================
// UTILITY TYPES
// ============================================================================

/**
 * API Response wrapper for loading states
 */
export interface ApiResponse<T> {
  data: T | null;
  loading: boolean;
  error: ApiError | null;
}

/**
 * Date range for historical data
 */
export interface DateRange {
  startDate: string; // ISO date string
  endDate: string;   // ISO date string
  days: number;
}

/**
 * Chart data point for historical rates
 */
export interface ChartDataPoint {
  date: string;
  mid: number;
  buy: number | null;
  sell: number;
  label: string; // Formatted date for display
}

// ============================================================================
// TYPE GUARDS
// ============================================================================

/**
 * Type guard to check if response is an API error
 */
export function isApiError(response: any): response is ApiError {
  return response && typeof response.error === 'string' && typeof response.message === 'string';
}

/**
 * Type guard to check if currency code is valid
 */
export function isValidCurrencyCode(code: string): code is CurrencyCode {
  return ['EUR', 'USD', 'CZK', 'IDR', 'BRL'].includes(code);
}

/**
 * Type guard to check if rate has buy rate
 */
export function hasBuyRate(rate: Rate): rate is Rate & { buy: number } {
  return rate.buy !== null;
}

// ============================================================================
// CONSTANTS
// ============================================================================

/**
 * All supported currency codes
 */
export const SUPPORTED_CURRENCIES: CurrencyCode[] = ['EUR', 'USD', 'CZK', 'IDR', 'BRL'];

/**
 * Currencies that support buying
 */
export const BUYING_CURRENCIES: CurrencyCode[] = ['EUR', 'USD'];

/**
 * Default number of days for historical data
 */
export const DEFAULT_HISTORY_DAYS = 14;

/**
 * API endpoints
 */
export const API_ENDPOINTS = {
  CURRENT_RATES: '/api/rates/current',
  HISTORICAL_RATES: (currency: CurrencyCode) => `/api/rates/historical/${currency}`,
  CURRENCIES: '/api/currencies',
  HEALTH: '/api/health',
} as const;

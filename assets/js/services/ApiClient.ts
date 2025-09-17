/**
 * API Client for Telemedi FX Desk
 * 
 * Centralized HTTP client for all backend API communication.
 * Provides type-safe methods for fetching exchange rates and currency data.
 */

import axios, { AxiosInstance, AxiosResponse, AxiosError } from 'axios';
import {
  CurrentRatesResponse,
  HistoricalRatesResponse,
  CurrenciesResponse,
  HealthResponse,
  ApiError,
  CurrencyCode,
  CurrentRatesRequest,
  HistoricalRatesRequest,
  isApiError,
  API_ENDPOINTS
} from '../types/api';

/**
 * Configuration for API Client
 */
interface ApiClientConfig {
  baseURL?: string;
  timeout?: number;
  retryAttempts?: number;
  retryDelay?: number;
}

/**
 * Default configuration
 */
const DEFAULT_CONFIG: Required<ApiClientConfig> = {
  baseURL: window.location.origin,
  timeout: 10000, // 10 seconds
  retryAttempts: 3,
  retryDelay: 1000 // 1 second
};

/**
 * API Client class for backend communication
 */
export class ApiClient {
  private axiosInstance: AxiosInstance;
  private config: Required<ApiClientConfig>;

  constructor(config: ApiClientConfig = {}) {
    this.config = { ...DEFAULT_CONFIG, ...config };
    this.axiosInstance = this.createAxiosInstance();
  }

  /**
   * Create and configure Axios instance
   */
  private createAxiosInstance(): AxiosInstance {
    const instance = axios.create({
      baseURL: this.config.baseURL,
      timeout: this.config.timeout,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      }
    });

    // Request interceptor
    instance.interceptors.request.use(
      (config) => {
        console.log(`API Request: ${config.method?.toUpperCase()} ${config.url}`);
        return config;
      },
      (error) => {
        console.error('API Request Error:', error);
        return Promise.reject(error);
      }
    );

    // Response interceptor
    instance.interceptors.response.use(
      (response) => {
        console.log(`API Response: ${response.status} ${response.config.url}`);
        return response;
      },
      (error) => {
        return this.handleResponseError(error);
      }
    );

    return instance;
  }

  /**
   * Handle response errors with retry logic
   */
  private async handleResponseError(error: AxiosError): Promise<never> {
    const originalRequest = error.config;

    // Don't retry if no config or already retried max times
    if (!originalRequest || (originalRequest as any).__retryCount >= this.config.retryAttempts) {
      throw this.transformError(error);
    }

    // Increment retry count
    (originalRequest as any).__retryCount = ((originalRequest as any).__retryCount || 0) + 1;

    // Only retry on network errors or 5xx server errors
    if (error.code === 'ECONNABORTED' || (error.response && error.response.status >= 500)) {
      console.log(`Retrying request (${(originalRequest as any).__retryCount}/${this.config.retryAttempts}): ${originalRequest.url}`);
      
      // Wait before retrying
      await new Promise(resolve => setTimeout(resolve, this.config.retryDelay));
      
      return this.axiosInstance(originalRequest);
    }

    throw this.transformError(error);
  }

  /**
   * Transform Axios error to ApiError
   */
  private transformError(error: AxiosError): ApiError {
    // Network or timeout error
    if (!error.response) {
      return {
        error: 'network_error',
        message: error.code === 'ECONNABORTED' ? 'Request timeout' : 'Network error',
        details: { code: error.code, message: error.message },
        timestamp: new Date().toISOString()
      };
    }

    // Server returned an error response
    const response = error.response;
    
    // If response data is already an ApiError, use it
    if (isApiError(response.data)) {
      return response.data;
    }

    // Transform HTTP error to ApiError
    return {
      error: 'http_error',
      message: `HTTP ${response.status}: ${response.statusText}`,
      details: { 
        status: response.status, 
        statusText: response.statusText,
        data: response.data 
      },
      timestamp: new Date().toISOString()
    };
  }

  /**
   * Generic API call method
   */
  private async apiCall<T>(
    method: 'GET' | 'POST' | 'PUT' | 'DELETE',
    url: string,
    data?: any,
    params?: any
  ): Promise<T> {
    try {
      const response: AxiosResponse<T> = await this.axiosInstance({
        method,
        url,
        data,
        params
      });
      return response.data;
    } catch (error) {
      // Error is already transformed by interceptor
      throw error;
    }
  }

  // ============================================================================
  // PUBLIC API METHODS
  // ============================================================================

  /**
   * Get current exchange rates
   */
  async getCurrentRates(request: CurrentRatesRequest = {}): Promise<CurrentRatesResponse> {
    const params: any = {};
    if (request.date) {
      params.date = request.date;
    }

    return this.apiCall<CurrentRatesResponse>('GET', API_ENDPOINTS.CURRENT_RATES, undefined, params);
  }

  /**
   * Get supported currencies
   */
  async getCurrencies(): Promise<CurrenciesResponse> {
    return this.apiCall<CurrenciesResponse>('GET', API_ENDPOINTS.CURRENCIES);
  }

  /**
   * Get historical rates for a currency
   */
  async getHistoricalRates(request: HistoricalRatesRequest): Promise<HistoricalRatesResponse> {
    const params: any = {};
    if (request.date) {
      params.date = request.date;
    }
    if (request.days) {
      params.days = request.days;
    }

    const url = API_ENDPOINTS.HISTORICAL_RATES(request.currency);
    return this.apiCall<HistoricalRatesResponse>('GET', url, undefined, params);
  }

  /**
   * Health check
   */
  async getHealth(): Promise<HealthResponse> {
    return this.apiCall<HealthResponse>('GET', API_ENDPOINTS.HEALTH);
  }

  // ============================================================================
  // UTILITY METHODS
  // ============================================================================

  /**
   * Test API connectivity
   */
  async testConnection(): Promise<boolean> {
    try {
      await this.getHealth();
      return true;
    } catch (error) {
      console.error('API connection test failed:', error);
      return false;
    }
  }

  /**
   * Get base URL
   */
  getBaseURL(): string {
    return this.config.baseURL;
  }

  /**
   * Update configuration
   */
  updateConfig(newConfig: Partial<ApiClientConfig>): void {
    this.config = { ...this.config, ...newConfig };
    this.axiosInstance = this.createAxiosInstance();
  }
}

// ============================================================================
// SINGLETON INSTANCE
// ============================================================================

/**
 * Default API client instance
 */
export const apiClient = new ApiClient();

/**
 * Create a new API client with custom configuration
 */
export const createApiClient = (config: ApiClientConfig): ApiClient => {
  return new ApiClient(config);
};

export default apiClient;

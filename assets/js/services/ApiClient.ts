/**
 * API Client Service - Centralized HTTP client for FX Desk API
 * 
 * Provides type-safe methods for all API endpoints with automatic
 * error handling, retry logic, and request cancellation.
 */

import axios, { AxiosInstance, AxiosRequestConfig, AxiosError } from 'axios';
import { 
  CurrentRatesRequest, 
  CurrentRatesResponse, 
  HistoricalRatesRequest, 
  HistoricalRatesResponse,
  CurrenciesResponse,
  HealthResponse,
  ApiError 
} from '../types/api';

/**
 * API Client Configuration
 */
interface ApiClientConfig {
  baseURL: string;
  timeout: number;
  retryAttempts: number;
  retryDelay: number;
}

/**
 * Default configuration
 */
const defaultConfig: ApiClientConfig = {
  baseURL: '/api',
  timeout: 10000, // 10 seconds
  retryAttempts: 3,
  retryDelay: 1000, // 1 second
};

/**
 * Centralized API Client using Singleton pattern
 */
class ApiClient {
  private axiosInstance: AxiosInstance;
  private config: ApiClientConfig;
  private static instance: ApiClient;
  private requestCache: Map<string, Promise<any>> = new Map();
  private cacheTimeout: number = 5000; // 5 seconds cache for duplicate requests

  private constructor(config?: Partial<ApiClientConfig>) {
    this.config = { ...defaultConfig, ...config };
    this.axiosInstance = axios.create({
      baseURL: this.config.baseURL,
      timeout: this.config.timeout,
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    });

    this.setupInterceptors();
  }

  /**
   * Get singleton instance
   */
  public static getInstance(config?: Partial<ApiClientConfig>): ApiClient {
    if (!ApiClient.instance) {
      ApiClient.instance = new ApiClient(config);
    } else if (config) {
      ApiClient.instance.updateConfig(config);
    }
    return ApiClient.instance;
  }

  /**
   * Update configuration
   */
  public updateConfig(config: Partial<ApiClientConfig>): void {
    this.config = { ...this.config, ...config };
    this.axiosInstance.defaults.baseURL = this.config.baseURL;
    this.axiosInstance.defaults.timeout = this.config.timeout;
  }

  /**
   * Setup request/response interceptors
   */
  private setupInterceptors(): void {
    // Request interceptor
    this.axiosInstance.interceptors.request.use(
      (config) => {
        console.log(`🔄 API Request: ${config.method?.toUpperCase()} ${config.url}`);
        return config;
      },
      (error) => Promise.reject(error)
    );

    // Response interceptor with retry logic
    this.axiosInstance.interceptors.response.use(
      (response) => {
        console.log(`✅ API Response: ${response.status} ${response.config.url}`);
        return response;
      },
      async (error: AxiosError) => {
        const config = error.config as AxiosRequestConfig & { _retryCount?: number };
        
        if (this.shouldRetry(error) && (!config._retryCount || config._retryCount < this.config.retryAttempts)) {
          config._retryCount = (config._retryCount || 0) + 1;
          console.log(`🔄 Retrying request (${config._retryCount}/${this.config.retryAttempts}): ${config.url}`);
          
          await this.delay(this.config.retryDelay);
          return this.axiosInstance.request(config);
        }

        return Promise.reject(this.transformError(error));
      }
    );
  }

  /**
   * Check if request should be retried
   */
  private shouldRetry(error: AxiosError): boolean {
    if (error.code === 'ECONNABORTED') return true; // Timeout
    if (!error.response) return true; // Network error
    if (error.response.status >= 500) return true; // Server error
    return false;
  }

  /**
   * Request deduplication - prevents duplicate requests
   */
  private async deduplicateRequest<T>(cacheKey: string, requestFn: () => Promise<T>): Promise<T> {
    // Check if request is already in progress
    if (this.requestCache.has(cacheKey)) {
      return this.requestCache.get(cacheKey) as Promise<T>;
    }

    // Create new request and cache it
    const requestPromise = requestFn().finally(() => {
      // Remove from cache after completion
      setTimeout(() => {
        this.requestCache.delete(cacheKey);
      }, this.cacheTimeout);
    });

    this.requestCache.set(cacheKey, requestPromise);
    return requestPromise;
  }

  /**
   * Delay helper for retry logic
   */
  private delay(ms: number): Promise<void> {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  /**
   * Transform axios error to ApiError
   */
  private transformError(error: AxiosError): ApiError {
    if (error.code === 'ECONNABORTED') {
      return {
        error: 'network_error',
        message: 'Request timeout',
        details: { timeout: this.config.timeout },
        timestamp: new Date().toISOString()
      };
    }

    if (!error.response) {
      return {
        error: 'network_error',
        message: 'Network error',
        details: { originalError: error.message },
        timestamp: new Date().toISOString()
      };
    }

    if (error.response.data && typeof error.response.data === 'object') {
      // Backend already returned ApiError format
      return error.response.data as ApiError;
    }

    return {
      error: 'http_error',
      message: `HTTP ${error.response.status}: ${error.response.statusText}`,
      details: { 
        status: error.response.status,
        statusText: error.response.statusText,
        originalError: error.message 
      },
      timestamp: new Date().toISOString()
    };
  }

  /**
   * Get current exchange rates
   */
  public async getCurrentRates(
    request: CurrentRatesRequest = {}, 
    signal?: AbortSignal
  ): Promise<CurrentRatesResponse> {
    const params = new URLSearchParams();
    if (request.date) params.append('date', request.date);
    
    const url = `/rates/current?${params.toString()}`;
    const cacheKey = `getCurrentRates:${url}`;
    
    return this.deduplicateRequest(cacheKey, async () => {
      const response = await this.axiosInstance.get<CurrentRatesResponse>(url, { signal });
      return response.data;
    });
  }

  /**
   * Get supported currencies
   */
  public async getCurrencies(signal?: AbortSignal): Promise<CurrenciesResponse> {
    const response = await this.axiosInstance.get<CurrenciesResponse>('/currencies', { signal });
    return response.data;
  }

  /**
   * Get API health status
   */
  public async getHealth(signal?: AbortSignal): Promise<HealthResponse> {
    const response = await this.axiosInstance.get<HealthResponse>('/health', { signal });
    return response.data;
  }

  /**
   * Get historical rates for a currency
   */
  public async getHistoricalRates(
    request: HistoricalRatesRequest,
    signal?: AbortSignal
  ): Promise<HistoricalRatesResponse> {
    const params = new URLSearchParams();
    if (request.date) params.append('date', request.date);
    if (request.days) params.append('days', request.days.toString());

    const response = await this.axiosInstance.get<HistoricalRatesResponse>(
      `/rates/historical/${request.currency}?${params.toString()}`,
      { signal }
    );
    return response.data;
  }

  /**
   * Test API connection
   */
  public async testConnection(): Promise<boolean> {
    try {
      await this.getHealth();
      return true;
    } catch (error) {
      console.error('❌ API Connection test failed:', error);
      return false;
    }
  }
}

// Export singleton instance
export default ApiClient;
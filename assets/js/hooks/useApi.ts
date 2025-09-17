/**
 * React Hooks for API Integration
 * 
 * Custom hooks for fetching data from backend API with loading states,
 * error handling, and automatic re-fetching capabilities.
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { apiClient } from '../services/ApiClient';
import {
  CurrentRatesResponse,
  HistoricalRatesResponse,
  CurrenciesResponse,
  HealthResponse,
  ApiError,
  ApiResponse,
  CurrencyCode,
  CurrentRatesRequest,
  HistoricalRatesRequest
} from '../types/api';

// ============================================================================
// GENERIC API HOOK
// ============================================================================

/**
 * Generic hook for API calls with loading states
 */
export function useApiCall<T>(
  apiMethod: () => Promise<T>,
  dependencies: any[] = [],
  immediate: boolean = true
): ApiResponse<T> & { refetch: () => Promise<void> } {
  const [data, setData] = useState<T | null>(null);
  const [loading, setLoading] = useState<boolean>(immediate);
  const [error, setError] = useState<ApiError | null>(null);
  const abortControllerRef = useRef<AbortController | null>(null);

  const fetchData = useCallback(async () => {
    // Cancel previous request if still pending
    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
    }

    // Create new abort controller
    abortControllerRef.current = new AbortController();

    try {
      setLoading(true);
      setError(null);
      
      const result = await apiMethod();
      
      // Only update state if request wasn't aborted
      if (!abortControllerRef.current.signal.aborted) {
        setData(result);
      }
    } catch (err) {
      // Only update state if request wasn't aborted
      if (!abortControllerRef.current.signal.aborted) {
        setError(err as ApiError);
        setData(null);
      }
    } finally {
      // Only update loading state if request wasn't aborted
      if (!abortControllerRef.current.signal.aborted) {
        setLoading(false);
      }
    }
  }, dependencies);

  const refetch = useCallback(async () => {
    await fetchData();
  }, [fetchData]);

  useEffect(() => {
    if (immediate) {
      fetchData();
    }

    // Cleanup function to abort request on unmount
    return () => {
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }
    };
  }, [fetchData, immediate]);

  return {
    data,
    loading,
    error,
    refetch
  };
}

// ============================================================================
// SPECIFIC API HOOKS
// ============================================================================

/**
 * Hook for fetching current exchange rates
 */
export function useCurrentRates(
  request: CurrentRatesRequest = {},
  immediate: boolean = true
): ApiResponse<CurrentRatesResponse> & { refetch: () => Promise<void> } {
  return useApiCall(
    () => apiClient.getCurrentRates(request),
    [request.date],
    immediate
  );
}

/**
 * Hook for fetching supported currencies
 */
export function useCurrencies(
  immediate: boolean = true
): ApiResponse<CurrenciesResponse> & { refetch: () => Promise<void> } {
  return useApiCall(
    () => apiClient.getCurrencies(),
    [],
    immediate
  );
}

/**
 * Hook for fetching historical rates
 */
export function useHistoricalRates(
  request: HistoricalRatesRequest,
  immediate: boolean = true
): ApiResponse<HistoricalRatesResponse> & { refetch: () => Promise<void> } {
  return useApiCall(
    () => apiClient.getHistoricalRates(request),
    [request.currency, request.date, request.days],
    immediate
  );
}

/**
 * Hook for health check
 */
export function useHealth(
  immediate: boolean = true
): ApiResponse<HealthResponse> & { refetch: () => Promise<void> } {
  return useApiCall(
    () => apiClient.getHealth(),
    [],
    immediate
  );
}

// ============================================================================
// SPECIALIZED HOOKS
// ============================================================================

/**
 * Hook for fetching rates for a specific currency
 */
export function useCurrencyRate(
  currency: CurrencyCode,
  date?: string,
  immediate: boolean = true
) {
  const { data, loading, error, refetch } = useCurrentRates({ date }, immediate);
  
  const currencyRate = data?.rates[currency] || null;
  
  return {
    data: currencyRate,
    loading,
    error,
    refetch
  };
}

/**
 * Hook for periodic data refresh
 */
export function usePeriodicRefresh<T>(
  hook: () => ApiResponse<T> & { refetch: () => Promise<void> },
  intervalMs: number = 60000 // 1 minute default
): ApiResponse<T> & { refetch: () => Promise<void>; isRefreshing: boolean } {
  const apiResponse = hook();
  const [isRefreshing, setIsRefreshing] = useState(false);
  const intervalRef = useRef<NodeJS.Timeout | null>(null);

  const refreshData = useCallback(async () => {
    setIsRefreshing(true);
    try {
      await apiResponse.refetch();
    } finally {
      setIsRefreshing(false);
    }
  }, [apiResponse.refetch]);

  useEffect(() => {
    if (intervalMs > 0) {
      intervalRef.current = setInterval(refreshData, intervalMs);
      
      return () => {
        if (intervalRef.current) {
          clearInterval(intervalRef.current);
        }
      };
    }
  }, [refreshData, intervalMs]);

  return {
    ...apiResponse,
    isRefreshing
  };
}

/**
 * Hook for connection status monitoring
 */
export function useConnectionStatus(checkIntervalMs: number = 30000): {
  isOnline: boolean;
  lastCheck: Date | null;
  checkConnection: () => Promise<void>;
} {
  const [isOnline, setIsOnline] = useState<boolean>(true);
  const [lastCheck, setLastCheck] = useState<Date | null>(null);
  const intervalRef = useRef<NodeJS.Timeout | null>(null);

  const checkConnection = useCallback(async () => {
    try {
      const isConnected = await apiClient.testConnection();
      setIsOnline(isConnected);
      setLastCheck(new Date());
    } catch (error) {
      setIsOnline(false);
      setLastCheck(new Date());
    }
  }, []);

  useEffect(() => {
    // Initial check
    checkConnection();

    // Set up periodic checks
    if (checkIntervalMs > 0) {
      intervalRef.current = setInterval(checkConnection, checkIntervalMs);
      
      return () => {
        if (intervalRef.current) {
          clearInterval(intervalRef.current);
        }
      };
    }
  }, [checkConnection, checkIntervalMs]);

  return {
    isOnline,
    lastCheck,
    checkConnection
  };
}

// ============================================================================
// UTILITY HOOKS
// ============================================================================

/**
 * Hook for debounced API calls
 */
export function useDebouncedApiCall<T>(
  apiMethod: () => Promise<T>,
  delay: number = 500,
  dependencies: any[] = []
): ApiResponse<T> & { refetch: () => Promise<void> } {
  const [debouncedDeps, setDebouncedDeps] = useState(dependencies);
  const timeoutRef = useRef<NodeJS.Timeout | null>(null);

  useEffect(() => {
    if (timeoutRef.current) {
      clearTimeout(timeoutRef.current);
    }

    timeoutRef.current = setTimeout(() => {
      setDebouncedDeps(dependencies);
    }, delay);

    return () => {
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current);
      }
    };
  }, dependencies);

  return useApiCall(apiMethod, debouncedDeps);
}

/**
 * Hook for caching API responses
 */
export function useCachedApiCall<T>(
  apiMethod: () => Promise<T>,
  cacheKey: string,
  ttlMs: number = 300000, // 5 minutes default
  dependencies: any[] = []
): ApiResponse<T> & { refetch: () => Promise<void>; isCached: boolean } {
  const [cache, setCache] = useState<Map<string, { data: T; timestamp: number }>>(new Map());
  
  const cachedApiMethod = useCallback(async (): Promise<T> => {
    const now = Date.now();
    const cached = cache.get(cacheKey);
    
    // Return cached data if still valid
    if (cached && (now - cached.timestamp) < ttlMs) {
      return cached.data;
    }
    
    // Fetch fresh data
    const freshData = await apiMethod();
    
    // Update cache
    setCache(prev => new Map(prev).set(cacheKey, { data: freshData, timestamp: now }));
    
    return freshData;
  }, [apiMethod, cacheKey, ttlMs, cache]);

  const apiResponse = useApiCall(cachedApiMethod, dependencies);
  const cached = cache.get(cacheKey);
  const isCached = cached ? (Date.now() - cached.timestamp) < ttlMs : false;

  return {
    ...apiResponse,
    isCached
  };
}

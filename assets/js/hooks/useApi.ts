/**
 * React Hooks for API Integration
 * 
 * Provides custom hooks for consuming the ApiClient with automatic
 * loading states, error handling, and request cancellation.
 */

import React, { useState, useEffect, useCallback, useRef, useMemo } from 'react';
import ApiClient from '../services/ApiClient';
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
 * Generic API response state
 */
interface ApiResponse<T> {
  data: T | null;
  loading: boolean;
  error: ApiError | null;
}

/**
 * Generic hook for API calls with loading states
 */
export function useApiCall<T, P = void>(
  apiMethod: (params: P, signal?: AbortSignal) => Promise<T>,
  params: P,
  immediate: boolean = true,
  deps: React.DependencyList = []
): ApiResponse<T> & { refetch: () => Promise<void> } {
  const [data, setData] = useState<T | null>(null);
  const [loading, setLoading] = useState<boolean>(false);
  const [error, setError] = useState<ApiError | null>(null);
  const abortControllerRef = useRef<AbortController | null>(null);
  const hasInitializedRef = useRef<boolean>(false);

  const fetchData = useCallback(async () => {
    console.log(`🎯 fetchData called with params:`, params);
    
    // Cancel previous request
    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
    }

    // Create new abort controller
    abortControllerRef.current = new AbortController();
    
    setLoading(true);
    setError(null);

    try {
      const result = await apiMethod(params, abortControllerRef.current.signal);
      console.log(`✅ fetchData success:`, result);
      setData(result);
    } catch (err) {
      if (err instanceof Error && err.name === 'AbortError') {
        console.log(`⏹️ fetchData aborted`);
        return;
      }
      console.error(`❌ fetchData error:`, err);
      setError(err as ApiError);
    } finally {
      setLoading(false);
    }
  }, [apiMethod]);

  // Single useEffect for both immediate and deps
  useEffect(() => {
    console.log(`🎯 useEffect - immediate: ${immediate}, hasInitialized: ${hasInitializedRef.current}, deps:`, deps);
    
    if (immediate && !hasInitializedRef.current) {
      console.log(`🚀 Initial fetch`);
      hasInitializedRef.current = true;
      fetchData();
    } else if (hasInitializedRef.current) {
      console.log(`🔄 Deps changed, refetching`);
      fetchData();
    }
  }, [immediate, ...deps]);

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }
    };
  }, []);

  return { data, loading, error, refetch: fetchData };
}

/**
 * Hook for current exchange rates
 */
export function useCurrentRates(
  request: CurrentRatesRequest = {},
  immediate: boolean = true,
  deps: React.DependencyList = []
): ApiResponse<CurrentRatesResponse> & { refetch: () => Promise<void> } {
  const apiClient = ApiClient.getInstance();
  return useApiCall(
    (p, signal) => apiClient.getCurrentRates(p, signal), 
    request, 
    immediate, 
    deps
  );
}

/**
 * Hook for supported currencies
 */
export function useCurrencies(
  immediate: boolean = true,
  deps: React.DependencyList = []
): ApiResponse<CurrenciesResponse> & { refetch: () => Promise<void> } {
  const apiClient = ApiClient.getInstance();
  return useApiCall(
    (_, signal) => apiClient.getCurrencies(signal), 
    undefined, 
    immediate, 
    deps
  );
}

/**
 * Hook for API health check
 */
export function useHealth(
  immediate: boolean = true,
  deps: React.DependencyList = []
): ApiResponse<HealthResponse> & { refetch: () => Promise<void> } {
  const apiClient = ApiClient.getInstance();
  return useApiCall(
    (_, signal) => apiClient.getHealth(signal), 
    undefined, 
    immediate, 
    deps
  );
}

/**
 * Hook for historical rates
 */
export function useHistoricalRates(
  request: HistoricalRatesRequest,
  immediate: boolean = true,
  deps: React.DependencyList = []
): ApiResponse<HistoricalRatesResponse> & { refetch: () => Promise<void> } {
  const apiClient = ApiClient.getInstance();
  return useApiCall(
    (p, signal) => apiClient.getHistoricalRates(p, signal), 
    request, 
    immediate, 
    [request.currency, request.date, request.days, ...deps]
  );
}

/**
 * Hook for connection status monitoring
 */
export function useConnectionStatus(
  intervalMs: number = 30000
): { isOnline: boolean; lastCheck: Date | null; checkConnection: () => Promise<void> } {
  const [isOnline, setIsOnline] = useState<boolean>(true);
  const [lastCheck, setLastCheck] = useState<Date | null>(null);
  const intervalRef = useRef<NodeJS.Timeout | null>(null);

  const checkConnection = useCallback(async () => {
    const apiClient = ApiClient.getInstance();
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
    if (intervalMs > 0) {
      intervalRef.current = setInterval(checkConnection, intervalMs);
    }

    return () => {
      if (intervalRef.current) {
        clearInterval(intervalRef.current);
      }
    };
  }, [checkConnection, intervalMs]);

  return { isOnline, lastCheck, checkConnection };
}

/**
 * Hook for periodic data refresh
 */
export function usePeriodicRefresh<T>(
  hook: () => ApiResponse<T> & { refetch: () => Promise<void> },
  intervalMs: number = 60000
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
    }

    return () => {
      if (intervalRef.current) {
        clearInterval(intervalRef.current);
      }
    };
  }, [refreshData, intervalMs]);

  return { ...apiResponse, isRefreshing };
}

/**
 * Hook for debounced API calls
 */
export function useDebouncedApiCall<T, P>(
  apiMethod: (params: P, signal?: AbortSignal) => Promise<T>,
  delay: number = 500,
  deps: React.DependencyList = []
): [
  (params: P) => void,
  ApiResponse<T>
] {
  const [params, setParams] = useState<P | null>(null);
  const [debouncedParams, setDebouncedParams] = useState<P | null>(null);
  const timeoutRef = useRef<NodeJS.Timeout | null>(null);

  // Debounce the params
  useEffect(() => {
    if (params !== null) {
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current);
      }
      
      timeoutRef.current = setTimeout(() => {
        setDebouncedParams(params);
      }, delay);
    }

    return () => {
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current);
      }
    };
  }, [params, delay]);

  const apiResponse = useApiCall(
    apiMethod,
    debouncedParams as P,
    debouncedParams !== null,
    deps
  );

  const triggerCall = useCallback((newParams: P) => {
    setParams(newParams);
  }, []);

  return [triggerCall, apiResponse];
}

/**
 * Hook for cached API calls
 */
export function useCachedApiCall<T, P>(
  apiMethod: (params: P, signal?: AbortSignal) => Promise<T>,
  cacheKey: string,
  ttlMs: number = 300000, // 5 minutes
  deps: React.DependencyList = []
): ApiResponse<T> & { refetch: () => Promise<void>; isCached: boolean } {
  const [isCached, setIsCached] = useState(false);

  const cachedApiMethod = useCallback(async (params: P, signal?: AbortSignal): Promise<T> => {
    const cache = sessionStorage.getItem(cacheKey);
    
    if (cache) {
      const { data, timestamp } = JSON.parse(cache);
      const isExpired = Date.now() - timestamp > ttlMs;
      
      if (!isExpired) {
        setIsCached(true);
        return data;
      }
    }

    setIsCached(false);
    const result = await apiMethod(params, signal);
    
    // Cache the result
    sessionStorage.setItem(cacheKey, JSON.stringify({
      data: result,
      timestamp: Date.now()
    }));
    
    return result;
  }, [apiMethod, cacheKey, ttlMs]);

  const apiResponse = useApiCall(cachedApiMethod, {} as P, true, deps);

  return { ...apiResponse, isCached };
}
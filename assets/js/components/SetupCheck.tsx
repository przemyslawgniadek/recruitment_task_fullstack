import React from 'react';
import { useHealth, useCurrentRates, useCurrencies, useConnectionStatus } from '../hooks/useApi';

/**
 * Setup Check Component
 * 
 * Tests API connectivity and basic functionality.
 * Verifies that backend API is responding correctly using the new API client.
 */

const SetupCheck: React.FC = () => {
  const healthCheck = useHealth();
  const ratesCheck = useCurrentRates({}, false); // Don't fetch immediately
  const currenciesCheck = useCurrencies(false); // Don't fetch immediately
  const connectionStatus = useConnectionStatus(30000); // Check every 30 seconds

  const runFullTest = async () => {
    await Promise.all([
      ratesCheck.refetch(),
      currenciesCheck.refetch()
    ]);
  };

  // Determine overall status
  const isLoading = healthCheck.loading || ratesCheck.loading || currenciesCheck.loading;
  const hasError = healthCheck.error || ratesCheck.error || currenciesCheck.error;
  const isHealthy = healthCheck.data?.status === 'ok';
  const hasRatesData = !!(ratesCheck.data && ratesCheck.data.count > 0);
  const hasCurrenciesData = !!(currenciesCheck.data && currenciesCheck.data.count > 0);

  return (
    <div>
      <section className="row-section">
        <div className="container">
          <div className="row mt-5">
            <div className="col-md-8 offset-md-2">
              <h2 className="text-center">
                <span>FX Desk Setup Check</span> @ Telemedi
              </h2>

              {isLoading ? (
                <div className="text-center">
                  <div className="spinner-border text-primary" role="status">
                    <span className="sr-only">Loading...</span>
                  </div>
                  <p className="mt-3">Checking API connectivity...</p>
                </div>
              ) : (
                <div>
                  {/* Connection Status */}
                  <div className="text-center mb-4">
                    <div className={`alert ${connectionStatus.isOnline ? 'alert-success' : 'alert-warning'}`}>
                      <h4>
                        {connectionStatus.isOnline ? '🟢 Online' : '🟡 Connection Issues'}
                      </h4>
                      <small>
                        Last checked: {connectionStatus.lastCheck?.toLocaleTimeString() || 'Never'}
                      </small>
                    </div>
                  </div>

                  {/* API Health Check */}
                  <div className="row mb-4">
                    <div className="col-md-4">
                      <div className={`card ${isHealthy ? 'border-success' : 'border-danger'}`}>
                        <div className="card-body text-center">
                          <h5 className="card-title">
                            {isHealthy ? '✅' : '❌'} Health Check
                          </h5>
                          <p className="card-text">
                            {healthCheck.error ? (
                              <span className="text-danger">
                                {healthCheck.error.message}
                              </span>
                            ) : isHealthy ? (
                              <span className="text-success">All systems operational</span>
                            ) : (
                              <span className="text-warning">Status unknown</span>
                            )}
                          </p>
                        </div>
                      </div>
                    </div>

                    <div className="col-md-4">
                      <div className={`card ${hasRatesData ? 'border-success' : 'border-warning'}`}>
                        <div className="card-body text-center">
                          <h5 className="card-title">
                            {hasRatesData ? '✅' : '⏳'} Exchange Rates
                          </h5>
                          <p className="card-text">
                            {ratesCheck.error ? (
                              <span className="text-danger">
                                {ratesCheck.error.message}
                              </span>
                            ) : hasRatesData ? (
                              <span className="text-success">
                                {ratesCheck.data?.count} currencies available
                              </span>
                            ) : (
                              <span className="text-muted">
                                <button 
                                  className="btn btn-sm btn-primary" 
                                  onClick={() => ratesCheck.refetch()}
                                  disabled={ratesCheck.loading}
                                >
                                  Test Rates API
                                </button>
                              </span>
                            )}
                          </p>
                        </div>
                      </div>
                    </div>

                    <div className="col-md-4">
                      <div className={`card ${hasCurrenciesData ? 'border-success' : 'border-warning'}`}>
                        <div className="card-body text-center">
                          <h5 className="card-title">
                            {hasCurrenciesData ? '✅' : '⏳'} Currencies
                          </h5>
                          <p className="card-text">
                            {currenciesCheck.error ? (
                              <span className="text-danger">
                                {currenciesCheck.error.message}
                              </span>
                            ) : hasCurrenciesData ? (
                              <span className="text-success">
                                {currenciesCheck.data?.count} currencies configured
                              </span>
                            ) : (
                              <span className="text-muted">
                                <button 
                                  className="btn btn-sm btn-primary" 
                                  onClick={() => currenciesCheck.refetch()}
                                  disabled={currenciesCheck.loading}
                                >
                                  Test Currencies API
                                </button>
                              </span>
                            )}
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Overall Status */}
                  <div className="text-center">
                    {!hasError && isHealthy ? (
                      <div>
                        <h3 className="text-success">
                          <strong>🎉 FX Desk Ready!</strong>
                        </h3>
                        <p className="text-muted">
                          All systems operational. Backend API is responding correctly.
                        </p>
                        <button 
                          className="btn btn-success btn-lg mt-3" 
                          onClick={runFullTest}
                          disabled={isLoading}
                        >
                          Run Full API Test
                        </button>
                      </div>
                    ) : (
                      <div>
                        <h3 className="text-warning">
                          <strong>⚠️ Setup Issues Detected</strong>
                        </h3>
                        <p className="text-muted">
                          Some API endpoints are not responding correctly.
                        </p>
                        <button 
                          className="btn btn-primary btn-lg mt-3" 
                          onClick={runFullTest}
                          disabled={isLoading}
                        >
                          Retry All Tests
                        </button>
                      </div>
                    )}
                  </div>

                  {/* Error Details */}
                  {hasError && (
                    <div className="mt-4">
                      <div className="alert alert-danger">
                        <h5>Error Details:</h5>
                        {healthCheck.error && (
                          <div><strong>Health:</strong> {healthCheck.error.message}</div>
                        )}
                        {ratesCheck.error && (
                          <div><strong>Rates:</strong> {ratesCheck.error.message}</div>
                        )}
                        {currenciesCheck.error && (
                          <div><strong>Currencies:</strong> {currenciesCheck.error.message}</div>
                        )}
                      </div>
                    </div>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>
      </section>
    </div>
  );
};

export default SetupCheck;

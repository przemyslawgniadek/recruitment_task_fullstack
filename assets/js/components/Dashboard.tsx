/**
 * Dashboard Component - Main FX Desk Interface
 * 
 * Displays current exchange rates in a professional table format
 * for currency exchange desk operations. Shows live NBP data with
 * calculated buy/sell rates according to business rules.
 */

import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useCurrentRates } from '../hooks/useApi';
import { CurrencyCode, Rate } from '../types/api';

/**
 * Dashboard Component Props
 */
interface DashboardProps {
  defaultDate?: string;
}

/**
 * Currency display configuration
 */
const CURRENCY_CONFIG = {
  EUR: { name: 'Euro', flag: '🇪🇺' },
  USD: { name: 'US Dollar', flag: '🇺🇸' },
  CZK: { name: 'Czech Koruna', flag: '🇨🇿' },
  IDR: { name: 'Indonesian Rupiah', flag: '🇮🇩' },
  BRL: { name: 'Brazilian Real', flag: '🇧🇷' }
} as const;

/**
 * Format number as PLN currency
 */
const formatPLN = (value: number | null): string => {
  if (value === null) return '—';
  return new Intl.NumberFormat('pl-PL', {
    style: 'currency',
    currency: 'PLN',
    minimumFractionDigits: 4,
    maximumFractionDigits: 4
  }).format(value);
};

/**
 * Format percentage
 */
const formatPercentage = (value: number | null): string => {
  if (value === null) return '—';
  const sign = value >= 0 ? '+' : '';
  return `${sign}${value.toFixed(2)} PLN`;
};

/**
 * Get today's date in YYYY-MM-DD format
 */
const getTodayDate = (): string => {
  return new Date().toISOString().split('T')[0];
};

/**
 * Get minimum allowed date (14 days ago) in YYYY-MM-DD format
 */
const getMinDate = (): string => {
  const date = new Date();
  date.setDate(date.getDate() - 14);
  return date.toISOString().split('T')[0];
};

/**
 * Currency Rate Row Component
 */
interface CurrencyRowProps {
  currency: CurrencyCode;
  rate: Rate;
}

const CurrencyRow: React.FC<CurrencyRowProps> = ({ currency, rate }) => {
  const config = CURRENCY_CONFIG[currency];
  const hasBuyRate = rate.supports_buying;

  return (
    <tr className={hasBuyRate ? 'table-success' : 'table-warning'}>
      <td>
        <div className="d-flex align-items-center">
          <span className="me-2" style={{ fontSize: '1.2em' }}>{config.flag}</span>
          <div>
            <strong>{currency}</strong>
            <br />
            <small className="text-muted">{config.name}</small>
          </div>
        </div>
      </td>
      <td className="text-end">
        <strong>{formatPLN(rate.mid)}</strong>
      </td>
      <td className="text-end">
        {hasBuyRate ? (
          <span className="text-success">
            <strong>{formatPLN(rate.buy)}</strong>
          </span>
        ) : (
          <span className="text-muted">
            <em>Not available</em>
          </span>
        )}
      </td>
      <td className="text-end">
        <span className="text-danger">
          <strong>{formatPLN(rate.sell)}</strong>
        </span>
      </td>
      <td className="text-end">
        <small>
          <div className="text-success">
            Buy: {formatPercentage(rate.margins.buy)}
          </div>
          <div className="text-danger">
            Sell: {formatPercentage(rate.margins.sell)}
          </div>
        </small>
      </td>
      <td className="text-center">
        <div className="d-flex flex-column gap-1">
          {hasBuyRate ? (
            <span className="badge bg-success">Buy & Sell</span>
          ) : (
            <span className="badge bg-warning text-dark">Sell Only</span>
          )}
          <Link 
            to={`/history/${currency}`} 
            className="btn btn-outline-primary btn-sm"
            style={{ fontSize: '0.75rem' }}
          >
            📈 History
          </Link>
        </div>
      </td>
    </tr>
  );
};

/**
 * Main Dashboard Component
 */
const Dashboard: React.FC<DashboardProps> = ({ defaultDate }) => {
  const [selectedDate, setSelectedDate] = useState<string>(defaultDate || getTodayDate());
  console.log('🗓️ Dashboard render - selectedDate:', selectedDate);
  const { data, loading, error, refetch } = useCurrentRates({ date: selectedDate }, true, [selectedDate]);

  // DISABLED: Auto-reset to today on error - let user see the error
  // React.useEffect(() => {
  //   if (error && selectedDate !== getTodayDate()) {
  //     console.log('🔄 Forcing today date due to error');
  //     setSelectedDate(getTodayDate());
  //   }
  // }, [error, selectedDate]);

  const handleDateChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const newDate = event.target.value;
    const minDate = getMinDate();
    const maxDate = getTodayDate();
    
    // Validate date range (14 days back to today)
    if (newDate < minDate || newDate > maxDate) {
      console.log('📅 Date out of range:', newDate, 'allowed:', minDate, 'to', maxDate);
      return; // Don't update if out of range
    }
    
    console.log('📅 Date picker changed:', selectedDate, '→', newDate);
    setSelectedDate(newDate);
  };

  const handleRefresh = () => {
    refetch();
  };

  return (
    <div className="container-fluid">
      {/* Header */}
      <div className="row mb-4">
        <div className="col-12">
          <div className="d-flex justify-content-between align-items-center">
            <div>
              <h1 className="h3 mb-1">💱 FX Desk Dashboard</h1>
              <p className="text-muted mb-0">
                Live exchange rates for currency desk operations
              </p>
            </div>
            <div className="d-flex align-items-center gap-3">
              <div className="date-picker-container">
                <label htmlFor="dateSelect" className="form-label mb-1">
                  <small><strong>Rate Date:</strong> <span className="text-muted">(last 14 days)</span></small>
                </label>
                <input
                  id="dateSelect"
                  type="date"
                  className="form-control"
                  value={selectedDate}
                  onChange={handleDateChange}
                  min={getMinDate()}
                  max={getTodayDate()}
                  key={selectedDate}
                />
              </div>
              <button
                className="btn btn-outline-primary"
                onClick={handleRefresh}
                disabled={loading}
              >
                {loading ? (
                  <>
                    <span className="spinner-border spinner-border-sm me-2" role="status" />
                    Refreshing...
                  </>
                ) : (
                  <>
                    🔄 Refresh
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Status Bar */}
      {data && (
        <div className="row mb-3">
          <div className="col-12">
            <div className="alert alert-info d-flex justify-content-between align-items-center mb-0">
              <div>
                <strong>📊 Data Status:</strong> Showing rates for <strong>{data.date}</strong>
                {' • '}<strong>{data.count}</strong> currencies available
              </div>
              <small className="text-muted">
                Last updated: {new Date().toLocaleTimeString('pl-PL')}
              </small>
            </div>
          </div>
        </div>
      )}

      {/* Main Content */}
      <div className="row">
        <div className="col-12">
          {loading && !data ? (
            /* Loading State */
            <div className="text-center py-5">
              <div className="spinner-border text-primary mb-3" role="status" style={{ width: '3rem', height: '3rem' }}>
                <span className="visually-hidden">Loading...</span>
              </div>
              <h4>Loading Exchange Rates...</h4>
              <p className="text-muted">Fetching live data from NBP API</p>
            </div>
          ) : error ? (
            /* Error State */
            <div className="text-center py-5">
              <div className="alert alert-danger">
                <h4 className="alert-heading">❌ Unable to Load Rates</h4>
                {error.message.includes('429 Too Many Requests') ? (
                  <>
                    <p className="mb-3"><strong>NBP API Rate Limit:</strong> Too many requests to NBP API</p>
                    <p className="mb-3">Please wait a moment and try again, or select today's date ({getTodayDate()})</p>
                    <button 
                      className="btn btn-warning me-2" 
                      onClick={() => setSelectedDate(getTodayDate())}
                    >
                      📅 Use Today's Date
                    </button>
                    <button className="btn btn-danger" onClick={handleRefresh}>
                      🔄 Try Again
                    </button>
                  </>
                ) : (
                  <>
                    <p className="mb-3">{error.message}</p>
                    <button className="btn btn-danger" onClick={handleRefresh}>
                      🔄 Try Again
                    </button>
                  </>
                )}
              </div>
            </div>
          ) : !data || data.count === 0 ? (
            /* Empty State */
            <div className="text-center py-5">
              <div className="alert alert-warning">
                <h4 className="alert-heading">📭 No Data Available</h4>
                <p className="mb-3">No exchange rates found for {selectedDate}</p>
                <button className="btn btn-warning" onClick={handleRefresh}>
                  🔄 Refresh Data
                </button>
              </div>
            </div>
          ) : (
            /* Success State - Rates Table */
            <div className="card">
              <div className="card-header bg-primary text-white">
                <h5 className="card-title mb-0">
                  💰 Exchange Rates - {data.date}
                </h5>
              </div>
              <div className="card-body p-0">
                <div className="table-responsive">
                  <table className="table table-hover mb-0">
                    <thead className="table-dark">
                      <tr>
                        <th scope="col">Currency</th>
                        <th scope="col" className="text-end">NBP Mid Rate</th>
                        <th scope="col" className="text-end">Buy Rate</th>
                        <th scope="col" className="text-end">Sell Rate</th>
                        <th scope="col" className="text-end">Margins</th>
                        <th scope="col" className="text-center">Operations</th>
                      </tr>
                    </thead>
                    <tbody>
                      {Object.entries(data.rates).map(([currencyCode, rate]) => (
                        <CurrencyRow
                          key={currencyCode}
                          currency={rate.currency}
                          rate={rate}
                        />
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
              <div className="card-footer text-muted">
                <div className="row">
                  <div className="col-md-6">
                    <small>
                      <strong>Legend:</strong>
                      <span className="badge bg-success ms-2">Buy & Sell Available</span>
                      <span className="badge bg-warning text-dark ms-2">Sell Only</span>
                    </small>
                  </div>
                  <div className="col-md-6 text-md-end">
                    <small>
                      <strong>Business Rules:</strong> EUR/USD: ±0.15/+0.11 PLN • Others: +0.20 PLN
                    </small>
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Additional Info */}
      {data && (
        <div className="row mt-4">
          <div className="col-12">
            <div className="card bg-light">
              <div className="card-body">
                <h6 className="card-title">📋 Quick Reference</h6>
                <div className="row">
                  <div className="col-md-4">
                    <strong>Buy Operations:</strong>
                    <ul className="list-unstyled mt-1">
                      <li>✅ EUR - Euro</li>
                      <li>✅ USD - US Dollar</li>
                    </ul>
                  </div>
                  <div className="col-md-4">
                    <strong>Sell Only:</strong>
                    <ul className="list-unstyled mt-1">
                      <li>🔸 CZK - Czech Koruna</li>
                      <li>🔸 IDR - Indonesian Rupiah</li>
                      <li>🔸 BRL - Brazilian Real</li>
                    </ul>
                  </div>
                  <div className="col-md-4">
                    <strong>Data Source:</strong>
                    <ul className="list-unstyled mt-1">
                      <li>📡 NBP API (National Bank of Poland)</li>
                      <li>🕐 Updated daily at noon</li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default Dashboard;
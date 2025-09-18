import React, { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useHistoricalRates } from '../hooks/useApi';
import { CurrencyCode, Rate } from '../types/api';

/**
 * Get today's date in YYYY-MM-DD format
 */
const getTodayDate = (): string => {
  return new Date().toISOString().split('T')[0];
};

/**
 * Get currency flag emoji
 */
const getCurrencyFlag = (currency: CurrencyCode): string => {
  const flags: Record<CurrencyCode, string> = {
    EUR: '🇪🇺',
    USD: '🇺🇸', 
    CZK: '🇨🇿',
    IDR: '🇮🇩',
    BRL: '🇧🇷'
  };
  return flags[currency] || '💱';
};

/**
 * Currency selector component
 */
interface CurrencySelectorProps {
  selectedCurrency: CurrencyCode;
  onCurrencyChange: (currency: CurrencyCode) => void;
}

const CurrencySelector: React.FC<CurrencySelectorProps> = ({ selectedCurrency, onCurrencyChange }) => {
  const currencies: CurrencyCode[] = ['EUR', 'USD', 'CZK', 'IDR', 'BRL'];
  
  return (
    <div className="mb-3">
      <label htmlFor="currencySelect" className="form-label">
        <strong>Currency:</strong>
      </label>
      <select 
        id="currencySelect"
        className="form-select"
        value={selectedCurrency}
        onChange={(e) => onCurrencyChange(e.target.value as CurrencyCode)}
        aria-label="Select currency for historical data"
        aria-describedby="currency-help"
      >
        {currencies.map(currency => (
          <option key={currency} value={currency}>
            {getCurrencyFlag(currency)} {currency}
          </option>
        ))}
      </select>
      <div id="currency-help" className="visually-hidden">
        Select a currency to view its 14-day historical exchange rate data
      </div>
    </div>
  );
};

/**
 * Simple SVG Line Chart Component
 */
interface LineChartProps {
  data: Rate[];
  width?: number;
  height?: number;
}

const LineChart: React.FC<LineChartProps> = ({ data, width = 800, height = 400 }) => {
  if (!data || data.length === 0) {
    return (
      <div className="text-center py-5">
        <p className="text-muted">No data available for chart</p>
      </div>
    );
  }

  // Sort data by date
  const sortedData = [...data].sort((a, b) => a.date.localeCompare(b.date));
  
  // Calculate chart dimensions
  const margin = { top: 20, right: 30, bottom: 60, left: 80 };
  const chartWidth = width - margin.left - margin.right;
  const chartHeight = height - margin.top - margin.bottom;
  
  // Get min/max values for scaling
  const allValues = sortedData.flatMap(rate => [rate.mid, rate.buy, rate.sell].filter(v => v !== null));
  const minValue = Math.min(...allValues);
  const maxValue = Math.max(...allValues);
  const valueRange = maxValue - minValue;
  const padding = valueRange * 0.1; // 10% padding
  
  // Scale functions
  const xScale = (index: number) => (index / (sortedData.length - 1)) * chartWidth;
  const yScale = (value: number) => chartHeight - ((value - minValue + padding) / (valueRange + 2 * padding)) * chartHeight;
  
  // Generate path for line
  const generatePath = (getValue: (rate: Rate) => number | null): string => {
    const points = sortedData
      .map((rate, index) => {
        const value = getValue(rate);
        return value !== null ? `${xScale(index)},${yScale(value)}` : null;
      })
      .filter(point => point !== null);
    
    return points.length > 0 ? `M ${points.join(' L ')}` : '';
  };
  
  return (
    <div className="chart-container">
      <svg 
        width={width} 
        height={height} 
        className="border rounded"
        role="img"
        aria-labelledby="chart-title"
        aria-describedby="chart-description"
      >
        <title id="chart-title">Historical Exchange Rates Chart</title>
        <desc id="chart-description">
          Line chart showing 14-day historical exchange rates with buy and sell rates over time
        </desc>
        <g transform={`translate(${margin.left}, ${margin.top})`}>
          {/* Grid lines */}
          {[0, 0.25, 0.5, 0.75, 1].map(ratio => (
            <g key={ratio}>
              <line
                x1={0}
                y1={chartHeight * ratio}
                x2={chartWidth}
                y2={chartHeight * ratio}
                stroke="#e0e0e0"
                strokeWidth={1}
              />
              <text
                x={-10}
                y={chartHeight * ratio + 4}
                textAnchor="end"
                fontSize="12"
                fill="#666"
              >
                {(minValue + (maxValue - minValue) * (1 - ratio)).toFixed(4)}
              </text>
            </g>
          ))}
          
          {/* X-axis labels */}
          {sortedData.map((rate, index) => (
            <text
              key={index}
              x={xScale(index)}
              y={chartHeight + 20}
              textAnchor="middle"
              fontSize="10"
              fill="#666"
              transform={`rotate(-45, ${xScale(index)}, ${chartHeight + 20})`}
            >
              {rate.date}
            </text>
          ))}
          
          {/* Mid rate line (blue) */}
          <path
            d={generatePath(rate => rate.mid)}
            fill="none"
            stroke="#0d6efd"
            strokeWidth={2}
          />
          
          {/* Buy rate line (green) */}
          <path
            d={generatePath(rate => rate.buy)}
            fill="none"
            stroke="#198754"
            strokeWidth={2}
            strokeDasharray="5,5"
          />
          
          {/* Sell rate line (red) */}
          <path
            d={generatePath(rate => rate.sell)}
            fill="none"
            stroke="#dc3545"
            strokeWidth={2}
            strokeDasharray="3,3"
          />
          
          {/* Data points */}
          {sortedData.map((rate, index) => (
            <g key={index}>
              <circle cx={xScale(index)} cy={yScale(rate.mid)} r={4} fill="#0d6efd" />
              {rate.buy && <circle cx={xScale(index)} cy={yScale(rate.buy)} r={3} fill="#198754" />}
              <circle cx={xScale(index)} cy={yScale(rate.sell)} r={3} fill="#dc3545" />
            </g>
          ))}
        </g>
      </svg>
      
      {/* Legend */}
      <div className="mt-3 d-flex justify-content-center gap-4">
        <div className="d-flex align-items-center">
          <div className="me-2" style={{width: '20px', height: '2px', backgroundColor: '#0d6efd'}}></div>
          <small>Mid Rate</small>
        </div>
        <div className="d-flex align-items-center">
          <div className="me-2" style={{width: '20px', height: '2px', backgroundColor: '#198754', borderTop: '2px dashed #198754'}}></div>
          <small>Buy Rate</small>
        </div>
        <div className="d-flex align-items-center">
          <div className="me-2" style={{width: '20px', height: '2px', backgroundColor: '#dc3545', borderTop: '2px dotted #dc3545'}}></div>
          <small>Sell Rate</small>
        </div>
      </div>
    </div>
  );
};

/**
 * Main History View Component
 */
interface HistoryViewProps {
  defaultCurrency?: CurrencyCode;
}

const HistoryView: React.FC<HistoryViewProps> = ({ defaultCurrency }) => {
  const { currency: urlCurrency } = useParams<{ currency: CurrencyCode }>();
  const [selectedCurrency, setSelectedCurrency] = useState<CurrencyCode>(
    urlCurrency || defaultCurrency || 'EUR'
  );
  const [days] = useState<number>(14);
  
  const { data, loading, error, refetch } = useHistoricalRates(
    { currency: selectedCurrency, date: getTodayDate(), days },
    true,
    [selectedCurrency]
  );
  
  const handleCurrencyChange = (currency: CurrencyCode) => {
    setSelectedCurrency(currency);
  };
  
  const handleRefresh = () => {
    refetch();
  };
  
  return (
    <div className="container-fluid py-4">
      {/* Header */}
      <div className="row mb-4">
        <div className="col">
          <div className="d-flex justify-content-between align-items-center">
            <div>
              <h1 className="h3 mb-1">
                📈 Currency History - {getCurrencyFlag(selectedCurrency)} {selectedCurrency}
              </h1>
              <p className="text-muted mb-0">
                14-day exchange rate history and trends
              </p>
            </div>
            <button
              className="btn btn-outline-primary"
              onClick={handleRefresh}
              disabled={loading}
            >
              {loading ? (
                <>
                  <span className="spinner-border spinner-border-sm me-2" />
                  Loading...
                </>
              ) : (
                '🔄 Refresh'
              )}
            </button>
          </div>
        </div>
      </div>
      
      {/* Currency Selector */}
      <div className="row mb-4">
        <div className="col-md-3">
          <CurrencySelector 
            selectedCurrency={selectedCurrency}
            onCurrencyChange={handleCurrencyChange}
          />
        </div>
      </div>
      
      {/* Content */}
      <div className="row">
        <div className="col">
          {loading ? (
            /* Loading State */
            <div className="text-center py-5">
              <div className="spinner-border text-primary mb-3" style={{width: '3rem', height: '3rem'}} />
              <h4>Loading Historical Data...</h4>
              <p className="text-muted">Fetching 14-day exchange rate history</p>
            </div>
          ) : error ? (
            /* Error State */
            <div className="text-center py-5">
              <div className="alert alert-danger">
                <h4 className="alert-heading">❌ Unable to Load History</h4>
                <p className="mb-3">{error.message}</p>
                <button className="btn btn-danger" onClick={handleRefresh}>
                  🔄 Try Again
                </button>
              </div>
            </div>
          ) : !data || data.count === 0 ? (
            /* Empty State */
            <div className="text-center py-5">
              <div className="alert alert-warning">
                <h4 className="alert-heading">📭 No Historical Data</h4>
                <p className="mb-3">No exchange rate history available for {selectedCurrency}</p>
                <button className="btn btn-warning" onClick={handleRefresh}>
                  🔄 Retry
                </button>
              </div>
            </div>
          ) : (
            /* Chart and Data */
            <div>
              {/* Summary Info */}
              <div className="row mb-4">
                <div className="col-md-8">
                  <div className="card">
                    <div className="card-body">
                      <h5 className="card-title">📊 Rate History Summary</h5>
                      <div className="row">
                        <div className="col-md-3">
                          <small className="text-muted">Period</small>
                          <div><strong>{data.start_date} to {data.end_date}</strong></div>
                        </div>
                        <div className="col-md-3">
                          <small className="text-muted">Data Points</small>
                          <div><strong>{data.count} days</strong></div>
                        </div>
                        <div className="col-md-3">
                          <small className="text-muted">Latest Mid Rate</small>
                          <div><strong>{data.rates[0]?.mid.toFixed(4)} PLN</strong></div>
                        </div>
                        <div className="col-md-3">
                          <small className="text-muted">Currency</small>
                          <div><strong>{getCurrencyFlag(data.currency)} {data.currency}</strong></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              {/* Chart */}
              <div className="row">
                <div className="col">
                  <div className="card">
                    <div className="card-header">
                      <h5 className="mb-0">📈 Exchange Rate Trends</h5>
                    </div>
                    <div className="card-body">
                      <LineChart data={data.rates} />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default React.memo(HistoryView);

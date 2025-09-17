# 💱 FX Desk - Currency Exchange Application

A professional currency exchange desk application built with **Symfony 6.4** and **React 17** with **TypeScript**. This application provides real-time exchange rates from the Polish National Bank (NBP) API with advanced business rules for currency trading operations.

## 🚀 Features

### Core Functionality
- **Real-time Exchange Rates** - Live data from NBP API
- **5 Supported Currencies** - EUR, USD, CZK, IDR, BRL
- **Advanced Business Rules** - Buy/sell rates with custom margins
- **14-Day Historical Data** - Interactive charts and trends
- **Professional UI** - Modern, responsive design for trading desks

### Technical Highlights
- **Domain-Driven Design** - Clean architecture with separated concerns
- **Multi-level Caching** - Redis-compatible caching with warming strategies
- **Comprehensive Testing** - 112+ unit tests with high coverage
- **TypeScript Frontend** - Type-safe React components with strict configuration
- **Performance Optimized** - Lazy loading, code splitting, bundle optimization
- **Accessibility Compliant** - WCAG 2.1 standards with ARIA support
- **Production Ready** - Logging, monitoring, error handling

## 🏗️ Architecture

### Backend (Symfony 6.4)
```
src/
├── Domain/                 # Business logic layer
│   ├── Currency/          # Currency entities and value objects
│   ├── Rate/              # Exchange rate domain models
│   └── QuotingRules/      # Business rules for buy/sell rates
├── Infrastructure/        # External integrations
│   ├── Nbp/              # NBP API client and DTOs
│   └── Cache/            # Caching implementation
├── Controller/           # API endpoints
├── Service/             # Application services
└── EventListener/       # Exception handling
```

### Frontend (React + TypeScript)
```
assets/js/
├── components/          # React components
│   ├── Dashboard.tsx   # Main rates table
│   ├── HistoryView.tsx # Historical charts
│   └── SetupCheck.tsx  # System diagnostics
├── services/           # API client
├── hooks/             # Custom React hooks
└── types/            # TypeScript definitions
```

## 🛠️ Quick Start

### Prerequisites
- **Docker** and **Docker Compose**
- **Node.js 18+** (for local development)
- **PHP 8.2+** (for local development)

### Docker Setup (Recommended)
```bash
# Clone the repository
git clone <repository-url>
cd recruitment_task_fullstack

# Start the application
docker-compose up -d

# Access the application
open http://localhost
```

### Local Development Setup
```bash
# Install dependencies
composer install
npm install

# Build frontend assets
npm run build

# Start development server
npm run watch

# Run tests
php bin/phpunit
```

## 📊 API Documentation

### Base URL
```
http://localhost/api
```

### Endpoints

#### Health Check
```http
GET /api/health
```
**Response:**
```json
{
  "status": "ok",
  "timestamp": "2025-09-18T00:00:00+02:00",
  "checks": {
    "database": "ok",
    "nbp_api": "ok",
    "cache": "ok"
  }
}
```

#### Current Exchange Rates
```http
GET /api/rates/current?date=2025-09-17
```
**Response:**
```json
{
  "date": "2025-09-17",
  "count": 5,
  "rates": {
    "EUR": {
      "currency": "EUR",
      "mid": 4.2850,
      "buy": 4.1350,
      "sell": 4.3950,
      "supports_buying": true,
      "margins": {
        "buy": -3.50,
        "sell": 2.57
      }
    }
  }
}
```

#### Historical Rates
```http
GET /api/rates/historical/EUR?days=14
```
**Response:**
```json
{
  "currency": "EUR",
  "days": 14,
  "count": 10,
  "rates": [
    {
      "date": "2025-09-17",
      "mid": 4.2850,
      "buy": 4.1350,
      "sell": 4.3950
    }
  ]
}
```

#### Supported Currencies
```http
GET /api/currencies
```
**Response:**
```json
{
  "count": 5,
  "currencies": ["EUR", "USD", "CZK", "IDR", "BRL"]
}
```

## 🧪 Testing

### Backend Tests
```bash
# Run all tests
php bin/phpunit

# Run with coverage
php bin/phpunit --coverage-html coverage/

# Run specific test suite
php bin/phpunit --testsuite=unit
```

### Frontend Testing
```bash
# Manual testing via Setup Check
open http://localhost/setup-check

# Build verification
npm run build
```

## 🔧 Configuration

### Environment Variables
Key configuration options in `.env`:

```bash
# Application
APP_ENV=dev
APP_DEBUG=1

# NBP API
NBP_BASE_URL=https://api.nbp.pl/api
NBP_TIMEOUT=10
NBP_RETRY_ATTEMPTS=3

# Supported Currencies
SUPPORTED_CODES=EUR,USD,CZK,IDR,BRL

# Cache TTL (seconds)
CACHE_FX_RATES_TTL=86400
CACHE_NBP_API_TTL=3600
```

## 🏢 Business Rules

### Currency Support
- **EUR, USD**: Full support (buy & sell)
- **CZK, IDR, BRL**: Sell only

### Margin Calculation
- **EUR/USD Buy**: NBP mid rate - 0.15 PLN
- **EUR/USD Sell**: NBP mid rate + 0.11 PLN  
- **CZK/IDR/BRL Sell**: NBP mid rate + 0.20 PLN

### Data Handling
- **Business Days**: Automatic fallback to last available rate
- **Timezone**: Europe/Warsaw (configurable)
- **Cache Strategy**: Multi-level with warming for performance

## 📱 User Interface

### Dashboard
- **Real-time Rates Table** - All currencies with buy/sell rates
- **Date Picker** - Historical data up to 14 days back
- **Professional Design** - Optimized for currency desk operations
- **Responsive Layout** - Mobile and tablet friendly

### History View
- **Interactive Charts** - SVG-based line charts
- **Currency Switching** - Easy comparison between currencies
- **14-Day Trends** - Visual representation of rate changes

### Setup Check
- **System Diagnostics** - API connectivity and health checks
- **Real-time Status** - Live connection monitoring
- **Error Reporting** - Detailed troubleshooting information

## 🚀 Performance

### Optimization Features
- **Bundle Size**: 264 KiB (optimized)
- **Lazy Loading**: Route-based code splitting
- **Caching**: Multi-level with automatic warming
- **Request Deduplication**: Prevents duplicate API calls
- **Compression**: Gzip enabled for all assets

### Metrics
- **Initial Load**: < 3 seconds
- **API Response**: < 1 second
- **Chart Rendering**: < 500ms
- **Memory Usage**: Optimized with React.memo

## 🔒 Security

### Implementation
- **Input Validation**: Comprehensive DTO validation
- **Error Handling**: Production-safe error responses
- **CORS Configuration**: Proper cross-origin handling
- **No Direct API Calls**: All NBP requests via backend
- **Trusted Proxies**: Configurable for deployment

## 📈 Monitoring

### Health Checks
- **Endpoint**: `/api/health`
- **Checks**: NBP API, Cache, Database connectivity
- **Format**: RFC 7807 compliant responses

### Logging
- **Structured Logging**: JSON format with context
- **Error Tracking**: Comprehensive exception logging
- **Performance Metrics**: Request timing and caching stats

## 🛠️ Development

### Code Quality
- **TypeScript**: Strict mode enabled
- **PSR Standards**: PHP-FIG compliance
- **Domain-Driven Design**: Clean architecture
- **SOLID Principles**: Dependency injection throughout

### Tools Used
- **Backend**: Symfony 6.4, Guzzle HTTP, PHPUnit
- **Frontend**: React 17, TypeScript 5.9, Webpack 5
- **Infrastructure**: Docker, Apache, PHP 8.2

## 📞 Support

### System Requirements
- **PHP**: 8.2+
- **Node.js**: 18+
- **Memory**: 512MB minimum
- **Storage**: 100MB for application

### Troubleshooting
1. **Check Setup**: Visit `/setup-check` for diagnostics
2. **Clear Cache**: `docker-compose restart`
3. **Rebuild Assets**: `npm run build`
4. **Check Logs**: `docker-compose logs`

---

**Built with ❤️ for professional currency exchange operations**
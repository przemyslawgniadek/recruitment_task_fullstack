# 🏗️ FX Desk Architecture Documentation

## 📋 Overview

The FX Desk application follows **Domain-Driven Design (DDD)** principles with clean architecture separation. The system is built as a modern web application with a Symfony backend API and React frontend.

## 🎯 Design Principles

### Core Principles
- **Domain-Driven Design** - Business logic isolated in domain layer
- **Clean Architecture** - Dependencies point inward toward domain
- **SOLID Principles** - Single responsibility, dependency inversion
- **Separation of Concerns** - Clear boundaries between layers
- **Testability** - All components are unit testable

### Quality Attributes
- **Performance** - Multi-level caching, lazy loading, optimized bundles
- **Scalability** - Stateless design, cacheable responses
- **Maintainability** - Clear structure, comprehensive documentation
- **Reliability** - Error handling, fallback mechanisms, monitoring
- **Security** - Input validation, secure error responses

---

## 🏛️ Backend Architecture (Symfony 6.4)

### Layer Structure
```
src/
├── Domain/                 # 🎯 Business Logic Layer
│   ├── Currency/          # Currency entities and value objects
│   ├── Rate/              # Exchange rate domain models  
│   └── QuotingRules/      # Business rules and calculations
├── Infrastructure/        # 🔌 External Integrations
│   ├── Nbp/              # NBP API client and DTOs
│   └── Cache/            # Caching implementation
├── Controller/           # 🌐 API Endpoints (Application Layer)
├── Service/             # 🛠️ Application Services
├── EventListener/       # 🎧 Cross-cutting Concerns
└── Dto/                # 📦 Data Transfer Objects
```

### Domain Layer Details

#### Currency Domain
```php
src/Domain/Currency/
├── Currency.php           # Currency entity with business logic
├── CurrencyCode.php       # Value object for currency codes
└── CurrencyRepository.php # Repository interface
```

**Key Concepts:**
- `Currency` - Rich domain entity with validation and business methods
- `CurrencyCode` - Enum-like value object ensuring type safety
- Repository pattern for data access abstraction

#### Rate Domain  
```php
src/Domain/Rate/
├── Rate.php              # Exchange rate entity
├── RateRepository.php    # Repository interface
└── RateCollection.php    # Collection of rates with business methods
```

**Key Concepts:**
- `Rate` - Contains mid rate, buy/sell rates, margins, and business logic
- Immutable value objects for data integrity
- Rich domain model with calculated properties

#### Quoting Rules Domain
```php
src/Domain/QuotingRules/
└── QuotingRulesService.php # Business rules for rate calculations
```

**Business Rules Implemented:**
- EUR/USD: Buy support with -0.15 PLN margin, Sell with +0.11 PLN
- CZK/IDR/BRL: Sell only with +0.20 PLN margin
- Margin percentage calculations
- Business day handling

### Infrastructure Layer

#### NBP Integration
```php
src/Infrastructure/Nbp/
├── NbpHttpClient.php      # HTTP client for NBP API
├── NbpRateRepository.php  # Repository implementation
└── Dto/                   # NBP-specific DTOs
    ├── NbpRateDto.php
    └── NbpTableDto.php
```

**Features:**
- Guzzle HTTP client with retry logic
- Timeout and error handling
- DTO mapping from NBP JSON responses
- Business day fallback logic

#### Caching System
```php
src/Infrastructure/Cache/
├── CachedRateRepository.php # Decorator pattern for caching
├── CacheWarmer.php         # Proactive cache warming
└── CacheKeyGenerator.php   # Consistent cache key generation
```

**Caching Strategy:**
- **Decorator Pattern** - Transparent caching layer
- **Multi-level TTL** - Different cache times per data type
- **Cache Warming** - Proactive loading of frequently accessed data
- **Fallback Mechanism** - Graceful degradation when cache fails

### Application Layer

#### Controllers
```php
src/Controller/
└── ExchangeRateController.php # REST API endpoints
```

**Endpoints:**
- `GET /api/health` - System health check
- `GET /api/rates/current` - Current exchange rates
- `GET /api/rates/historical/{currency}` - Historical data
- `GET /api/currencies` - Supported currencies

#### Services
```php
src/Service/
└── TimezoneService.php    # Timezone and business day logic
```

#### Error Handling
```php
src/EventListener/
└── ApiExceptionListener.php # Global exception handling
```

**Features:**
- RFC 7807 compliant error responses
- Structured logging with context
- Production-safe error messages
- Environment-aware stack traces

---

## 💻 Frontend Architecture (React + TypeScript)

### Component Structure
```
assets/js/
├── components/           # 🧩 React Components
│   ├── Dashboard.tsx    # Main rates table with date picker
│   ├── HistoryView.tsx  # Historical charts and currency switching
│   ├── SetupCheck.tsx   # System diagnostics and health checks
│   └── Home.tsx         # Router and navigation
├── services/            # 🔧 External Services
│   └── ApiClient.ts     # HTTP client with error handling
├── hooks/              # 🎣 Custom React Hooks
│   └── useApi.ts       # API integration hooks
├── types/              # 📝 TypeScript Definitions
│   └── api.ts          # API response types
└── app.tsx            # Application entry point
```

### Component Design Patterns

#### Dashboard Component
```typescript
// Responsibilities:
- Display current exchange rates table
- Date picker for historical data
- Loading states and error handling
- Responsive design for mobile/tablet
- Accessibility compliance (ARIA labels)
```

#### HistoryView Component  
```typescript
// Responsibilities:
- SVG-based interactive charts
- Currency switching functionality
- 14-day historical data visualization
- Responsive chart scaling
```

#### SetupCheck Component
```typescript
// Responsibilities:
- System health diagnostics
- API connectivity testing
- Real-time connection monitoring
- Troubleshooting information
```

### State Management

#### Custom Hooks Pattern
```typescript
// useApi.ts - Centralized API state management
- useCurrentRates() - Current rates with caching
- useHistoricalRates() - Historical data fetching
- useHealth() - Health check monitoring
- useConnectionStatus() - Connection state tracking
```

**Benefits:**
- Reusable stateful logic
- Consistent error handling
- Built-in loading states
- Request deduplication

### Performance Optimizations

#### Code Splitting
```javascript
// Lazy loading implementation
const Dashboard = lazy(() => import('./Dashboard'));
const HistoryView = lazy(() => import('./HistoryView'));
const SetupCheck = lazy(() => import('./SetupCheck'));
```

#### Bundle Optimization
- **Webpack Configuration** - Code splitting and vendor chunks
- **React.memo** - Prevent unnecessary re-renders
- **Request Deduplication** - Avoid duplicate API calls
- **Asset Optimization** - Minification and compression

---

## 🔄 Data Flow Architecture

### Request Flow
```
1. User Action (Frontend)
   ↓
2. React Component State Update
   ↓  
3. Custom Hook (useApi)
   ↓
4. ApiClient HTTP Request
   ↓
5. Symfony Controller
   ↓
6. Application Service
   ↓
7. Domain Service (QuotingRules)
   ↓
8. Repository (with Caching)
   ↓
9. Infrastructure (NBP API)
   ↓
10. Response Back Through Layers
```

### Caching Flow
```
Request → Cache Check → Cache Hit? 
                     ↓
                   Yes: Return Cached Data
                     ↓
                   No: Fetch from NBP API
                     ↓
                   Store in Cache
                     ↓
                   Return Fresh Data
```

### Error Flow
```
Exception Thrown → ApiExceptionListener
                 ↓
               Log Error with Context
                 ↓
               Create RFC 7807 Response
                 ↓
               Return Structured Error
                 ↓
               Frontend Error Handling
```

---

## 🗄️ Data Models

### Domain Models

#### Rate Entity
```php
class Rate {
    private Currency $currency;
    private float $mid;           // NBP middle rate
    private ?float $buy;          // Our buy rate (null if not supported)
    private float $sell;          // Our sell rate
    private bool $supportsBuying; // Business rule flag
    private array $margins;       // Calculated margins
}
```

#### Currency Value Object
```php
enum CurrencyCode: string {
    case EUR = 'EUR';
    case USD = 'USD'; 
    case CZK = 'CZK';
    case IDR = 'IDR';
    case BRL = 'BRL';
}
```

### API Response Models
```typescript
interface CurrentRatesResponse {
  date: string;
  count: number;
  rates: Record<string, RateData>;
}

interface RateData {
  currency: string;
  mid: number;
  buy: number | null;
  sell: number;
  supports_buying: boolean;
  margins: {
    buy: number | null;
    sell: number;
  };
}
```

---

## 🔧 Configuration Architecture

### Environment Configuration
```bash
# Application Settings
APP_ENV=dev|prod
APP_DEBUG=0|1
APP_SECRET=<secret-key>

# NBP API Configuration  
NBP_BASE_URL=https://api.nbp.pl/api
NBP_TIMEOUT=10
NBP_RETRY_ATTEMPTS=3

# Business Configuration
SUPPORTED_CODES=EUR,USD,CZK,IDR,BRL
TIMEZONE=Europe/Warsaw

# Cache Configuration
CACHE_FX_RATES_TTL=86400      # 24 hours
CACHE_NBP_API_TTL=3600        # 1 hour
CACHE_APP_METADATA_TTL=604800 # 7 days
```

### Service Configuration
```yaml
# config/services.yaml
services:
  App\Domain\QuotingRules\QuotingRulesService:
    arguments:
      $eurUsdBuyMargin: -0.15
      $eurUsdSellMargin: 0.11
      $otherSellMargin: 0.20
      
  App\Infrastructure\Nbp\NbpHttpClient:
    arguments:
      $baseUrl: '%env(NBP_BASE_URL)%'
      $timeout: '%env(int:NBP_TIMEOUT)%'
      $retryAttempts: '%env(int:NBP_RETRY_ATTEMPTS)%'
```

---

## 🧪 Testing Architecture

### Testing Strategy
- **Unit Tests** - Domain logic and services (112+ tests)
- **Integration Tests** - API endpoints and external services
- **Manual Testing** - Setup Check component for system verification

### Test Structure
```
tests/
├── Unit/
│   ├── Domain/           # Domain logic tests
│   ├── Service/          # Service layer tests
│   └── Infrastructure/   # Infrastructure tests
└── Integration/
    └── Controller/       # API endpoint tests
```

### Testing Principles
- **Arrange-Act-Assert** pattern
- **Mock external dependencies** (NBP API)
- **Test business rules thoroughly**
- **Edge case coverage** (weekends, holidays, errors)

---

## 📊 Monitoring & Observability

### Health Checks
- **Endpoint**: `/api/health`
- **Checks**: NBP API connectivity, Cache availability, System status
- **Format**: Structured JSON with timestamps

### Logging Strategy
- **Structured Logging** - JSON format with context
- **Log Levels** - DEBUG, INFO, WARNING, ERROR
- **Context Enrichment** - Request IDs, user context, timing
- **Error Tracking** - Full exception details with stack traces

### Performance Monitoring
- **Response Times** - API endpoint performance
- **Cache Hit Rates** - Caching effectiveness
- **Error Rates** - System reliability metrics
- **Resource Usage** - Memory and CPU utilization

---

## 🚀 Deployment Architecture

### Docker Configuration
```dockerfile
# Multi-stage build
FROM node:18-alpine AS frontend-builder
# Frontend build stage

FROM php:8.2-apache AS production  
# Production runtime
```

### Infrastructure Requirements
- **PHP 8.2+** with extensions (zip, opcache)
- **Apache 2.4+** with mod_rewrite
- **Node.js 18+** for frontend builds
- **Cache Backend** - Redis compatible (optional)

---

## 🔮 Future Considerations

### Scalability Enhancements
- **Database Integration** - Persistent storage for historical data
- **Redis Caching** - Distributed caching for multiple instances
- **API Rate Limiting** - Protect against abuse
- **Load Balancing** - Multiple application instances

### Feature Extensions
- **More Currencies** - Easy addition through configuration
- **Real-time Updates** - WebSocket integration for live rates
- **User Management** - Authentication and authorization
- **Advanced Analytics** - Trend analysis and forecasting

### Technical Improvements
- **GraphQL API** - More flexible data fetching
- **Event Sourcing** - Audit trail for all rate changes
- **CQRS Pattern** - Separate read/write models
- **Microservices** - Service decomposition for larger scale

---

**📝 Last Updated: September 2025**  
**🔗 Related: [README.md](README.md) | [API Documentation](API_DOCUMENTATION.md)**

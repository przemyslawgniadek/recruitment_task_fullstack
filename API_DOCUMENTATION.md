# 📊 FX Desk API Documentation

Complete API reference for the FX Desk Currency Exchange Application.

## 🌐 Base Information

- **Base URL**: `http://localhost/api`
- **Content-Type**: `application/json`
- **Response Format**: JSON
- **Error Format**: RFC 7807 Problem Details

## 🔗 Endpoints Overview

| Endpoint | Method | Description | Cache TTL |
|----------|--------|-------------|-----------|
| `/health` | GET | System health check | No cache |
| `/rates/current` | GET | Current exchange rates | 1 hour |
| `/rates/historical/{currency}` | GET | Historical rates for currency | 24 hours |
| `/currencies` | GET | Supported currencies list | 7 days |

---

## 🏥 Health Check

### `GET /api/health`

System health and connectivity check endpoint.

**Parameters:** None

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

**Status Codes:**
- `200 OK` - All systems operational
- `503 Service Unavailable` - System issues detected

**Error Response:**
```json
{
  "status": "error",
  "timestamp": "2025-09-18T00:00:00+02:00",
  "checks": {
    "database": "error",
    "nbp_api": "error",
    "cache": "error"
  },
  "message": "Health check failed: Connection timeout"
}
```

---

## 💱 Current Exchange Rates

### `GET /api/rates/current`

Retrieve current exchange rates for all supported currencies.

**Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `date` | string | No | today | Date in YYYY-MM-DD format (max 14 days back) |

**Example Request:**
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
    },
    "USD": {
      "currency": "USD", 
      "mid": 3.9420,
      "buy": 3.7920,
      "sell": 4.0520,
      "supports_buying": true,
      "margins": {
        "buy": -3.81,
        "sell": 2.79
      }
    },
    "CZK": {
      "currency": "CZK",
      "mid": 0.1789,
      "buy": null,
      "sell": 0.1989,
      "supports_buying": false,
      "margins": {
        "buy": null,
        "sell": 11.18
      }
    }
  }
}
```

**Field Descriptions:**
- `date` - Effective date of rates (YYYY-MM-DD)
- `count` - Number of currencies returned
- `rates` - Object with currency codes as keys
- `mid` - NBP middle rate in PLN
- `buy` - Our buying rate (null if not supported)
- `sell` - Our selling rate
- `supports_buying` - Whether we buy this currency
- `margins.buy` - Buy margin percentage vs NBP mid rate
- `margins.sell` - Sell margin percentage vs NBP mid rate

**Status Codes:**
- `200 OK` - Success
- `400 Bad Request` - Invalid date parameter
- `404 Not Found` - No data for specified date
- `503 Service Unavailable` - NBP API unavailable

---

## 📈 Historical Exchange Rates

### `GET /api/rates/historical/{currency}`

Retrieve historical exchange rates for a specific currency.

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `currency` | string | Yes | Currency code (EUR, USD, CZK, IDR, BRL) |

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `days` | integer | No | 14 | Number of days back (1-14) |

**Example Request:**
```http
GET /api/rates/historical/EUR?days=7
```

**Response:**
```json
{
  "currency": "EUR",
  "days": 7,
  "count": 5,
  "rates": [
    {
      "date": "2025-09-17",
      "mid": 4.2850,
      "buy": 4.1350,
      "sell": 4.3950,
      "supports_buying": true
    },
    {
      "date": "2025-09-16", 
      "mid": 4.2920,
      "buy": 4.1420,
      "sell": 4.4020,
      "supports_buying": true
    }
  ]
}
```

**Field Descriptions:**
- `currency` - Requested currency code
- `days` - Requested number of days
- `count` - Actual number of rates returned
- `rates` - Array of historical rates (newest first)

**Status Codes:**
- `200 OK` - Success
- `400 Bad Request` - Invalid currency or days parameter
- `404 Not Found` - Currency not supported
- `503 Service Unavailable` - NBP API unavailable

---

## 🌍 Supported Currencies

### `GET /api/currencies`

Get list of all supported currency codes.

**Parameters:** None

**Response:**
```json
{
  "count": 5,
  "currencies": ["EUR", "USD", "CZK", "IDR", "BRL"]
}
```

**Status Codes:**
- `200 OK` - Success

---

## ⚠️ Error Handling

All API errors follow RFC 7807 Problem Details format.

### Error Response Structure
```json
{
  "type": "https://fx-desk.example.com/errors/validation-failed",
  "title": "Validation Failed", 
  "status": 400,
  "detail": "The date parameter must be in YYYY-MM-DD format",
  "instance": "/api/rates/current",
  "timestamp": "2025-09-18T00:00:00+02:00",
  "errors": {
    "date": ["Invalid date format"]
  }
}
```

### Common Error Types

#### 400 Bad Request
```json
{
  "type": "validation-failed",
  "title": "Validation Failed",
  "status": 400,
  "detail": "Request parameters are invalid"
}
```

#### 404 Not Found  
```json
{
  "type": "resource-not-found",
  "title": "Resource Not Found", 
  "status": 404,
  "detail": "No exchange rates found for the specified date"
}
```

#### 503 Service Unavailable
```json
{
  "type": "service-unavailable",
  "title": "Service Unavailable",
  "status": 503, 
  "detail": "NBP API is temporarily unavailable"
}
```

---

## 🏢 Business Rules

### Currency Support Matrix
| Currency | Buy Support | Sell Support | Buy Margin | Sell Margin |
|----------|-------------|--------------|------------|-------------|
| EUR | ✅ Yes | ✅ Yes | -0.15 PLN | +0.11 PLN |
| USD | ✅ Yes | ✅ Yes | -0.15 PLN | +0.11 PLN |
| CZK | ❌ No | ✅ Yes | - | +0.20 PLN |
| IDR | ❌ No | ✅ Yes | - | +0.20 PLN |
| BRL | ❌ No | ✅ Yes | - | +0.20 PLN |

### Rate Calculation
- **Buy Rate** = NBP Mid Rate - Fixed Margin (PLN)
- **Sell Rate** = NBP Mid Rate + Fixed Margin (PLN)
- **Margin %** = ((Our Rate - NBP Mid) / NBP Mid) × 100

### Data Availability
- **Business Days Only** - NBP publishes rates on working days
- **Publication Time** - Usually around 11:45-12:15 CET
- **Fallback Strategy** - If today's rate unavailable, returns last available rate
- **Historical Limit** - Maximum 14 days back from current date

---

## 🔄 Caching Strategy

### Cache TTL by Endpoint
- **Current Rates**: 1 hour (3600 seconds)
- **Historical Rates**: 24 hours (86400 seconds)  
- **Currencies**: 7 days (604800 seconds)
- **Health Check**: No cache

### Cache Warming
The system automatically warms cache for:
- Current rates for all currencies
- Historical rates for last 14 days
- Business day fallbacks

### Cache Headers
```http
Cache-Control: public, max-age=3600
ETag: "abc123def456"
Last-Modified: Wed, 18 Sep 2025 10:00:00 GMT
```

---

## 🔧 Rate Limiting

Currently no rate limiting is implemented, but recommended limits:
- **Health Check**: 60 requests/minute
- **Current Rates**: 100 requests/minute  
- **Historical Rates**: 50 requests/minute
- **Currencies**: 10 requests/minute

---

## 📊 Response Times

Typical response times under normal conditions:
- **Health Check**: < 100ms
- **Current Rates** (cached): < 50ms
- **Current Rates** (fresh): < 800ms
- **Historical Rates** (cached): < 100ms
- **Historical Rates** (fresh): < 1200ms

---

## 🧪 Testing Examples

### cURL Examples

**Health Check:**
```bash
curl -X GET "http://localhost/api/health" \
  -H "Accept: application/json"
```

**Current Rates:**
```bash
curl -X GET "http://localhost/api/rates/current?date=2025-09-17" \
  -H "Accept: application/json"
```

**Historical Data:**
```bash
curl -X GET "http://localhost/api/rates/historical/EUR?days=7" \
  -H "Accept: application/json"
```

### JavaScript Examples

**Using Fetch API:**
```javascript
// Get current rates
const response = await fetch('/api/rates/current');
const data = await response.json();

// Get EUR history
const history = await fetch('/api/rates/historical/EUR?days=14');
const eurData = await history.json();
```

**Using Axios:**
```javascript
// Health check
const health = await axios.get('/api/health');

// Current rates with error handling
try {
  const rates = await axios.get('/api/rates/current', {
    params: { date: '2025-09-17' }
  });
  console.log(rates.data);
} catch (error) {
  console.error('API Error:', error.response.data);
}
```

---

**📝 Last Updated: September 2025**  
**🔗 Related: [README.md](README.md) | [Manual Testing Checklist](MANUAL_TESTING_CHECKLIST.md)**

# Stockd - Stock Portfolio Tracker

## Quick Start

```bash
cd /home/rob/projects/stockd
php -S localhost:8080
# Open http://localhost:8080
```

## Technology Stack

| Component | Technology |
|-----------|------------|
| Backend | PHP 8+ (single file: `api.php`) |
| Frontend | Alpine.js 3.x + Chart.js (single file: `index.php`) |
| Database | SQLite (file-based: `db/stocks.db`) |
| CSS | Pico CSS (CDN) |
| Data Source | Yahoo Finance API |
| PWA | manifest.json + sw.js |

## Files

```
stockd/
├── api.php          # Backend API (all endpoints)
├── index.php        # Frontend (HTML/CSS/JS)
├── manifest.json    # PWA manifest
├── sw.js            # Service worker
├── db/
│   └── stocks.db    # SQLite database
└── PROJECT.md       # This file
```

## Features

### Core Features
- Real-time stock quotes from Yahoo Finance
- Portfolio value tracking with gain/loss calculations
- Interactive price charts (1D, 1W, 1M, 3M, 1Y, 5Y)
- Price alerts with browser notifications
- Live ticker marquee

### Added Features (Jan 2026)
- **Watchlist Mode**: Track stocks without owning them
- **Benchmark Comparison**: S&P 500, NASDAQ, Dow Jones vs your portfolio
- **News Headlines**: Expandable news section per stock
- **Dividend Tracking**: Annual dividend, yield, income projections
- **CSV Export**: One-click portfolio download
- **PWA Support**: Installable as mobile app
- **Account Dropdown**: Select existing accounts or add new ones

## API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `?action=list` | GET | List all stocks |
| `?action=get&id=X` | GET | Get single stock |
| `?action=create` | POST | Add new stock |
| `?action=update&id=X` | POST | Update stock |
| `?action=delete&id=X` | POST | Delete stock |
| `?action=quote&symbol=X` | GET | Get stock quote |
| `?action=history&symbol=X&range=1m` | GET | Get price history |
| `?action=news&symbol=X` | GET | Get news headlines |
| `?action=benchmark&range=1d` | GET | Get market indices |
| `?action=dividends&symbol=X` | GET | Get dividend history |
| `?action=export&format=csv` | GET | Download CSV |
| `?action=alerts` | GET | List price alerts |
| `?action=createAlert` | POST | Create price alert |
| `?action=deleteAlert&id=X` | POST | Delete alert |
| `?action=checkAlerts` | POST | Check triggered alerts |

## Database Schema

### stocks table
```sql
CREATE TABLE stocks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    symbol VARCHAR(10) NOT NULL,
    company_name VARCHAR(100) NOT NULL,
    account VARCHAR(50),
    purchase_price DECIMAL(10,2),
    shares DECIMAL(10,4),
    notes TEXT,
    is_watchlist BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### alerts table
```sql
CREATE TABLE alerts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    stock_id INTEGER NOT NULL,
    symbol VARCHAR(10) NOT NULL,
    condition VARCHAR(10) NOT NULL,  -- 'above' or 'below'
    target_price DECIMAL(10,2) NOT NULL,
    triggered BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### dividends table
```sql
CREATE TABLE dividends (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    stock_id INTEGER NOT NULL,
    symbol VARCHAR(10) NOT NULL,
    amount DECIMAL(10,4) NOT NULL,
    ex_date DATE,
    pay_date DATE,
    record_date DATE,
    dividend_type VARCHAR(20) DEFAULT 'regular',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

## GitHub Repository

https://github.com/pierceyourmind/stockd

## Production Deployment

### Option 1: Nginx + PHP-FPM
```nginx
server {
    listen 443 ssl http2;
    server_name stockd.yourdomain.com;

    root /var/www/stockd;
    index index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Protect database
    location ~ /db/ {
        deny all;
    }
}
```

### Option 2: Docker
```dockerfile
FROM php:8.2-fpm-alpine
RUN docker-php-ext-install pdo_sqlite
COPY . /var/www/html
```

## Known Issues / TODOs

- Yahoo Finance API may rate-limit requests
- Benchmark data shows 0% when market is closed (previousClose is null)
- News endpoint may fail due to Yahoo API changes

## Last Updated

January 21, 2026

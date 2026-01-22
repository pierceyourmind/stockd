# Technology Stack

**Analysis Date:** 2026-01-21

## Languages

**Primary:**
- PHP 8+ (strict types enabled) - Backend API and business logic in `api.php`
- HTML5 - Frontend markup in `index.php`
- JavaScript (ES6+) - Frontend interactivity and API client logic in `index.php`
- CSS3 - Styling with CSS variables and animations in `index.php`

**Secondary:**
- SQL - SQLite queries for data persistence in `api.php`

## Runtime

**Environment:**
- PHP 8.0+ (tested with PHP 8.2-fpm-alpine in Docker)
- No external runtime required beyond PHP CLI/FPM

**Package Manager:**
- None - No package manager (composer.json not present). All dependencies are CDN-based or built-in.

## Frameworks

**Backend:**
- None - Vanilla PHP with native PDO for database access (`api.php`)
- Uses functional programming pattern with match() expression for routing (PHP 8.0+ feature)

**Frontend:**
- Alpine.js 3.x - Reactive UI framework for state management and DOM interaction
  - Loaded from CDN: `https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js`
- Chart.js - Data visualization library for portfolio and price charts
  - Loaded from CDN: `https://cdn.jsdelivr.net/npm/chart.js`

**CSS:**
- Pico CSS 2.x - Minimalist CSS framework providing semantic HTML styling
  - Loaded from CDN: `https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css`
  - Minimal overrides in `index.php` for dark theme customization

## Key Dependencies

**Critical:**
- PDO (PHP Data Objects) - Database abstraction layer built into PHP, used for SQLite in `api.php`
- Alpine.js 3.x - Core dependency for frontend reactivity and component state
- Chart.js - Required for rendering price history and portfolio allocation charts

**Infrastructure:**
- SQLite3 - File-based relational database at `db/stocks.db`
  - PDO driver: `pdo_sqlite` (must be enabled in PHP: `docker-php-ext-install pdo_sqlite`)

## Configuration

**Environment:**
- Database path: Hardcoded to `db/stocks.db` relative to API root (`api.php`)
- PHP error handling: `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION` set in `api.php:23-26`
- CORS headers enabled via PHP headers in `api.php:4-7`
- No .env file or environment variable configuration detected

**Build:**
- No build process required - HTML/CSS/JS served directly from `index.php`
- Service Worker caching configured in `sw.js` for offline support and asset caching
- PWA manifest at `manifest.json`

## Platform Requirements

**Development:**
- PHP 8.0+ with pdo_sqlite extension
- Basic web server (PHP built-in server or nginx/Apache)
- Modern browser with ES6+ and Service Worker support

**Production:**
- PHP 8.2+ recommended (per PROJECT.md)
- Nginx + PHP-FPM or Apache with mod_php
- Secure file permissions to protect `db/stocks.db`
- HTTPS required for PWA service worker registration
- Browser support: Modern browsers (Chrome, Firefox, Safari, Edge) with ES6+ and fetch API

## Single-File Architecture

**API Layer:** `api.php` (845 lines)
- All backend logic in single file
- Database initialization and migrations inline (lines 14-115)
- 15+ functions for CRUD, data fetching, and calculations
- Direct HTTP context creation for external API calls

**Frontend Layer:** `index.php` (2614 lines)
- Complete HTML structure and styling
- Alpine.js component state defined inline (lines 1752+)
- All event handlers and API client logic embedded
- Portfolio calculations and chart rendering inline

**Assets:**
- `manifest.json` - PWA configuration
- `sw.js` - Service Worker for offline support and caching (113 lines)

## External Data Sources

**Yahoo Finance API:**
- Endpoints used: query1.finance.yahoo.com v1 and v8
- No API key required (public endpoints)
- Data fetched server-side in `api.php` with User-Agent spoofing
- Supports: Stock quotes, price history, news, dividends, benchmark indices

---

*Stack analysis: 2026-01-21*

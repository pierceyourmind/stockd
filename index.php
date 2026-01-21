<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stockd - Stock Portfolio Tracker</title>
    <meta name="description" content="Track your stock portfolio with real-time prices, charts, news, and dividend information">
    <meta name="theme-color" content="#58a6ff">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'%3E%3Crect fill='%230d1117' width='512' height='512' rx='64'/%3E%3Cpath fill='%2358a6ff' d='M128 384l80-80 64 64 112-160 32 32-144 192-64-64-80 80v-64z'/%3E%3C/svg%3E">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root {
            --pico-background-color: #0d1117;
            --pico-card-background-color: rgba(22, 27, 34, 0.8);
            --pico-color: #e6edf3;
            --pico-muted-color: #8b949e;
            --pico-primary: #58a6ff;
            --pico-primary-hover: #79b8ff;
            --green: #3fb950;
            --red: #f85149;
            --glass-bg: rgba(22, 27, 34, 0.7);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        body {
            background: linear-gradient(135deg, #0d1117 0%, #161b22 50%, #0d1117 100%);
            min-height: 100vh;
        }

        /* Ticker Bar */
        .ticker-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 48px;
            background: rgba(0, 0, 0, 0.9);
            border-bottom: 1px solid var(--glass-border);
            overflow: hidden;
            z-index: 100;
            backdrop-filter: blur(10px);
        }

        .ticker-track {
            display: flex;
            animation: ticker 30s linear infinite;
            white-space: nowrap;
            padding: 12px 0;
        }

        .ticker-track:hover {
            animation-play-state: paused;
        }

        @keyframes ticker {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }

        .ticker-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0 24px;
            font-size: 14px;
            font-weight: 500;
        }

        .ticker-item .symbol {
            color: var(--pico-primary);
            font-weight: 700;
        }

        .ticker-item .price {
            color: var(--pico-color);
        }

        .ticker-item .change {
            font-size: 12px;
            padding: 2px 6px;
            border-radius: 4px;
        }

        .ticker-item .change.up {
            color: var(--green);
            background: rgba(63, 185, 80, 0.15);
        }

        .ticker-item .change.down {
            color: var(--red);
            background: rgba(248, 81, 73, 0.15);
        }

        /* Main Content */
        main {
            padding-top: 80px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 56px;
            margin-bottom: 32px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .header h1 {
            margin: 0;
            font-size: 2rem;
            background: linear-gradient(135deg, var(--pico-primary), var(--green));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header .subtitle {
            color: var(--pico-muted-color);
            font-size: 0.9rem;
            margin-top: 4px;
        }

        /* Stock Cards Grid */
        .stocks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }

        .stock-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 24px;
            backdrop-filter: blur(10px);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stock-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
        }

        .stock-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .stock-symbol {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--pico-primary);
        }

        .stock-company {
            color: var(--pico-muted-color);
            font-size: 0.85rem;
            margin-top: 4px;
        }

        .stock-account {
            color: var(--pico-primary);
            font-size: 0.7rem;
            margin-top: 4px;
            padding: 2px 8px;
            background: rgba(88, 166, 255, 0.15);
            border-radius: 4px;
            display: inline-block;
        }

        .stock-price {
            text-align: right;
        }

        .stock-price .current {
            font-size: 1.4rem;
            font-weight: 600;
        }

        .stock-price .change {
            font-size: 0.85rem;
            margin-top: 4px;
        }

        .stock-price .change.up { color: var(--green); }
        .stock-price .change.down { color: var(--red); }

        /* Period Changes Grid */
        .period-changes {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 4px;
            margin: 16px 0;
            padding: 10px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            overflow: hidden;
        }

        .period-change {
            text-align: center;
            padding: 6px 2px;
            min-width: 0;
        }

        .period-change .label {
            font-size: 0.6rem;
            color: var(--pico-muted-color);
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .period-change .value {
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .period-change .value.up { color: var(--green); }
        .period-change .value.down { color: var(--red); }

        /* Live indicator */
        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.7rem;
            color: var(--pico-muted-color);
        }

        .live-dot {
            width: 8px;
            height: 8px;
            background: var(--green);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        .live-dot.stale {
            background: var(--pico-muted-color);
            animation: none;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
        }

        /* Price change flash animation */
        .price-flash {
            animation: priceFlash 1s ease-out;
        }

        .price-flash.up {
            animation: priceFlashUp 1s ease-out;
        }

        .price-flash.down {
            animation: priceFlashDown 1s ease-out;
        }

        @keyframes priceFlashUp {
            0% { background: rgba(63, 185, 80, 0.4); }
            100% { background: transparent; }
        }

        @keyframes priceFlashDown {
            0% { background: rgba(248, 81, 73, 0.4); }
            100% { background: transparent; }
        }

        .stock-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin: 16px 0;
            padding: 16px 0;
            border-top: 1px solid var(--glass-border);
            border-bottom: 1px solid var(--glass-border);
        }

        .stock-detail label {
            color: var(--pico-muted-color);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stock-detail span {
            display: block;
            font-size: 1rem;
            font-weight: 500;
            margin-top: 4px;
        }

        .stock-detail span.profit { color: var(--green); }
        .stock-detail span.loss { color: var(--red); }

        /* Stock Metrics Section */
        .stock-metrics {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin: 12px 0;
            padding: 12px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            font-size: 0.8rem;
        }

        .stock-metric {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stock-metric .metric-label {
            color: var(--pico-muted-color);
            font-size: 0.7rem;
        }

        .stock-metric .metric-value {
            font-weight: 500;
        }

        /* 52-Week Range Bar */
        .range-52w {
            grid-column: 1 / -1;
            margin-top: 4px;
        }

        .range-52w .range-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.65rem;
            color: var(--pico-muted-color);
            margin-bottom: 4px;
        }

        .range-bar {
            position: relative;
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
            overflow: visible;
        }

        .range-bar .current-marker {
            position: absolute;
            top: 50%;
            width: 10px;
            height: 10px;
            background: var(--pico-primary);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            box-shadow: 0 0 6px rgba(88, 166, 255, 0.5);
        }

        .range-bar .range-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--red), var(--pico-muted-color) 50%, var(--green));
            border-radius: 3px;
            opacity: 0.3;
        }

        .stock-notes {
            color: var(--pico-muted-color);
            font-size: 0.85rem;
            font-style: italic;
            margin: 12px 0;
            white-space: pre-line;
        }

        .stock-actions {
            display: flex;
            gap: 8px;
            margin-top: 16px;
        }

        .stock-actions button {
            flex: 1;
            padding: 8px 16px;
            font-size: 0.85rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--pico-muted-color);
        }

        .empty-state svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        /* Modal Customization */
        dialog {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            backdrop-filter: blur(20px);
        }

        dialog article {
            background: transparent;
            border: none;
            box-shadow: none;
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 200;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .toast {
            padding: 12px 20px;
            border-radius: 8px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(10px);
            animation: slideIn 0.3s ease;
        }

        .toast.success { border-left: 3px solid var(--green); }
        .toast.error { border-left: 3px solid var(--red); }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Loading Spinner */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid var(--glass-border);
            border-top-color: var(--pico-primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Portfolio Summary */
        .portfolio-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }

        .summary-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 20px;
            backdrop-filter: blur(10px);
        }

        .summary-card label {
            color: var(--pico-muted-color);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .summary-card .value {
            font-size: 1.5rem;
            font-weight: 600;
            margin-top: 8px;
        }

        .summary-card .value.profit { color: var(--green); }
        .summary-card .value.loss { color: var(--red); }

        /* Portfolio Charts Section */
        .portfolio-charts {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .portfolio-chart-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 20px;
            backdrop-filter: blur(10px);
        }

        .portfolio-chart-card h4 {
            margin: 0 0 16px 0;
            font-size: 0.9rem;
            color: var(--pico-muted-color);
            text-align: center;
        }

        .portfolio-chart-wrapper {
            position: relative;
            height: 200px;
        }

        .chart-toggle-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px;
            margin-bottom: 16px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            color: var(--pico-muted-color);
            cursor: pointer;
            transition: all 0.2s;
        }

        .chart-toggle-btn:hover {
            border-color: var(--pico-primary);
            color: var(--pico-primary);
        }

        .chart-toggle-btn.active {
            background: rgba(88, 166, 255, 0.1);
            border-color: var(--pico-primary);
            color: var(--pico-primary);
        }

        .chart-toggle-btn svg {
            width: 20px;
            height: 20px;
        }

        /* Controls Bar (Sort/Filter/Search) */
        .controls-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
            align-items: center;
        }

        .controls-bar .search-box {
            flex: 1;
            min-width: 200px;
            position: relative;
        }

        .controls-bar .search-box input {
            padding-left: 40px;
            margin: 0;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
        }

        .controls-bar .search-box svg {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            color: var(--pico-muted-color);
            pointer-events: none;
        }

        .controls-bar select {
            margin: 0;
            min-width: 140px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
        }

        .controls-bar .filter-pills {
            display: flex;
            gap: 6px;
        }

        .filter-pill {
            padding: 6px 12px;
            font-size: 0.8rem;
            border-radius: 20px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-pill:hover {
            border-color: var(--pico-primary);
        }

        .filter-pill.active {
            background: var(--pico-primary);
            border-color: var(--pico-primary);
            color: #fff;
        }

        .filter-pill.gainers.active {
            background: var(--green);
            border-color: var(--green);
        }

        .filter-pill.losers.active {
            background: var(--red);
            border-color: var(--red);
        }

        .results-count {
            font-size: 0.85rem;
            color: var(--pico-muted-color);
        }

        /* Stock Chart Section */
        .chart-section {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--glass-border);
        }

        .chart-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            color: var(--pico-muted-color);
            font-size: 0.85rem;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .chart-toggle:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--pico-primary);
        }

        .chart-toggle svg {
            width: 18px;
            height: 18px;
            transition: transform 0.2s;
        }

        .chart-toggle.expanded svg {
            transform: rotate(180deg);
        }

        .chart-container {
            margin-top: 12px;
        }

        .chart-range-buttons {
            display: flex;
            gap: 4px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .chart-range-btn {
            padding: 4px 10px;
            font-size: 0.75rem;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            color: var(--pico-muted-color);
            cursor: pointer;
            transition: all 0.2s;
        }

        .chart-range-btn:hover {
            border-color: var(--pico-primary);
            color: var(--pico-color);
        }

        .chart-range-btn.active {
            background: var(--pico-primary);
            border-color: var(--pico-primary);
            color: #fff;
        }

        .chart-canvas-wrapper {
            position: relative;
            height: 200px;
        }

        .chart-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--pico-muted-color);
            font-size: 0.85rem;
        }

        /* Alert Styles */
        .alert-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 6px 12px;
            background: transparent;
            border: 1px solid var(--glass-border);
            border-radius: 6px;
            color: var(--pico-muted-color);
            cursor: pointer;
            font-size: 0.75rem;
            transition: all 0.2s;
        }

        .alert-btn:hover {
            border-color: #f0883e;
            color: #f0883e;
        }

        .alert-btn.has-alerts {
            border-color: #f0883e;
            color: #f0883e;
            background: rgba(240, 136, 62, 0.1);
        }

        .alert-btn svg {
            width: 14px;
            height: 14px;
        }

        .alert-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            width: 8px;
            height: 8px;
            background: #f0883e;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        .alert-list {
            margin: 16px 0;
        }

        .alert-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 12px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: 0.85rem;
        }

        .alert-item.triggered {
            opacity: 0.5;
            text-decoration: line-through;
        }

        .alert-item .alert-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-item .alert-condition {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            text-transform: uppercase;
        }

        .alert-item .alert-condition.above {
            background: rgba(63, 185, 80, 0.15);
            color: var(--green);
        }

        .alert-item .alert-condition.below {
            background: rgba(248, 81, 73, 0.15);
            color: var(--red);
        }

        .alert-item .delete-alert {
            padding: 4px 8px;
            background: transparent;
            border: none;
            color: var(--pico-muted-color);
            cursor: pointer;
            font-size: 0.75rem;
        }

        .alert-item .delete-alert:hover {
            color: var(--red);
        }

        .notification-permission {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px;
            background: rgba(88, 166, 255, 0.1);
            border: 1px solid var(--pico-primary);
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 0.85rem;
        }

        .notification-permission button {
            margin-left: auto;
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        /* Benchmark Comparison Section */
        .benchmark-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }

        .benchmark-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            padding: 14px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .benchmark-card .name {
            font-size: 0.85rem;
            color: var(--pico-muted-color);
        }

        .benchmark-card .price {
            font-size: 1.1rem;
            font-weight: 600;
            margin-top: 4px;
        }

        .benchmark-card .change {
            text-align: right;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .benchmark-card .change.up { color: var(--green); }
        .benchmark-card .change.down { color: var(--red); }

        .portfolio-vs-benchmark {
            font-size: 0.75rem;
            color: var(--pico-muted-color);
            margin-top: 4px;
        }

        .portfolio-vs-benchmark span.better { color: var(--green); }
        .portfolio-vs-benchmark span.worse { color: var(--red); }

        /* Holdings/Watchlist Tabs */
        .view-tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 16px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            padding: 4px;
            width: fit-content;
        }

        .view-tab {
            padding: 8px 16px;
            font-size: 0.85rem;
            border-radius: 6px;
            cursor: pointer;
            color: var(--pico-muted-color);
            transition: all 0.2s;
            border: none;
            background: transparent;
        }

        .view-tab:hover {
            color: var(--pico-color);
        }

        .view-tab.active {
            background: var(--pico-primary);
            color: #fff;
        }

        .view-tab .count {
            margin-left: 6px;
            font-size: 0.7rem;
            opacity: 0.8;
        }

        /* Watchlist Badge */
        .watchlist-badge {
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 4px;
            background: rgba(240, 136, 62, 0.15);
            color: #f0883e;
            text-transform: uppercase;
            margin-left: 8px;
        }

        /* News Section */
        .news-section {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--glass-border);
        }

        .news-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            color: var(--pico-muted-color);
            font-size: 0.85rem;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .news-toggle:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--pico-primary);
        }

        .news-toggle svg {
            width: 16px;
            height: 16px;
        }

        .news-list {
            margin-top: 12px;
        }

        .news-item {
            display: block;
            padding: 10px 12px;
            margin-bottom: 8px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            text-decoration: none;
            transition: background 0.2s;
        }

        .news-item:hover {
            background: rgba(0, 0, 0, 0.3);
        }

        .news-item .news-title {
            font-size: 0.85rem;
            color: var(--pico-color);
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .news-item .news-meta {
            font-size: 0.7rem;
            color: var(--pico-muted-color);
            margin-top: 4px;
        }

        .news-loading {
            padding: 16px;
            text-align: center;
            color: var(--pico-muted-color);
            font-size: 0.85rem;
        }

        /* Dividend Section */
        .dividend-section {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--glass-border);
        }

        .dividend-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            color: var(--pico-muted-color);
            font-size: 0.85rem;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .dividend-toggle:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--green);
        }

        .dividend-toggle svg {
            width: 16px;
            height: 16px;
        }

        .dividend-summary {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 12px;
            padding: 12px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
        }

        .dividend-stat {
            text-align: center;
        }

        .dividend-stat .label {
            font-size: 0.7rem;
            color: var(--pico-muted-color);
            text-transform: uppercase;
        }

        .dividend-stat .value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--green);
            margin-top: 4px;
        }

        .dividend-history {
            margin-top: 12px;
            max-height: 150px;
            overflow-y: auto;
        }

        .dividend-item {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 0.8rem;
        }

        .dividend-item:last-child {
            border-bottom: none;
        }

        .dividend-item .date {
            color: var(--pico-muted-color);
        }

        .dividend-item .amount {
            color: var(--green);
            font-weight: 500;
        }

        /* Export Button */
        .export-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: transparent;
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            color: var(--pico-muted-color);
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .export-btn:hover {
            border-color: var(--pico-primary);
            color: var(--pico-primary);
        }

        .export-btn svg {
            width: 16px;
            height: 16px;
        }

        /* Watchlist Toggle in Form */
        .watchlist-toggle {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .watchlist-toggle label {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .watchlist-toggle input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin: 0;
        }

        .watchlist-toggle .hint {
            font-size: 0.75rem;
            color: var(--pico-muted-color);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header { flex-direction: column; align-items: flex-start; }
            .stocks-grid { grid-template-columns: 1fr; }
            .ticker-item { padding: 0 16px; }
            .controls-bar { flex-direction: column; align-items: stretch; }
            .controls-bar .search-box { min-width: 100%; }
            .controls-bar .filter-pills { justify-content: center; }
            .benchmark-section { grid-template-columns: 1fr 1fr; }
            .view-tabs { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body x-data="stockApp()" x-init="init()">

    <!-- Ticker Bar -->
    <div class="ticker-wrapper" x-show="tickerItems.length > 0">
        <div class="ticker-track">
            <template x-for="item in [...tickerItems, ...tickerItems]" :key="item.symbol + Math.random()">
                <div class="ticker-item">
                    <span class="symbol" x-text="item.symbol"></span>
                    <span class="price" x-text="'$' + item.price.toFixed(2)"></span>
                    <span class="change"
                          :class="item.change >= 0 ? 'up' : 'down'"
                          x-text="(item.change >= 0 ? '+' : '') + item.change.toFixed(2) + ' (' + item.changePercent.toFixed(2) + '%)'">
                    </span>
                </div>
            </template>
        </div>
    </div>

    <main class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>Stockd</h1>
                <p class="subtitle">
                    <span class="live-indicator">
                        <span class="live-dot" :class="{ 'stale': !isMarketOpen }"></span>
                        <span x-text="isMarketOpen ? 'Live' : 'Market Closed'"></span>
                        <span x-show="lastUpdate"> · Updated <span x-text="lastUpdateAgo"></span></span>
                    </span>
                </p>
            </div>
            <div style="display: flex; gap: 12px; align-items: center;">
                <button class="export-btn" @click="exportPortfolio()" x-show="stocks.length > 0">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    Export CSV
                </button>
                <button @click="openAddModal()" class="primary">
                    + Add Stock
                </button>
            </div>
        </div>

        <!-- Portfolio Summary -->
        <div class="portfolio-summary" x-show="stocks.length > 0">
            <div class="summary-card">
                <label>Total Value</label>
                <div class="value" x-text="'$' + totalValue.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></div>
            </div>
            <div class="summary-card">
                <label>Total Cost</label>
                <div class="value" x-text="'$' + totalCost.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></div>
            </div>
            <div class="summary-card">
                <label>Total Gain/Loss</label>
                <div class="value" :class="totalGain >= 0 ? 'profit' : 'loss'"
                     x-text="(totalGain >= 0 ? '+' : '') + '$' + totalGain.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></div>
            </div>
            <div class="summary-card">
                <label>Stocks Tracked</label>
                <div class="value" x-text="stocks.length"></div>
            </div>
        </div>

        <!-- Benchmark Comparison -->
        <div class="benchmark-section" x-show="Object.keys(benchmarks).length > 0">
            <template x-for="(bench, symbol) in benchmarks" :key="symbol">
                <div class="benchmark-card">
                    <div>
                        <div class="name" x-text="bench.name"></div>
                        <div class="price" x-text="'$' + bench.price.toLocaleString()"></div>
                    </div>
                    <div>
                        <div class="change" :class="bench.dayChangePercent >= 0 ? 'up' : 'down'"
                             x-show="bench.dayChangePercent !== null"
                             x-text="(bench.dayChangePercent >= 0 ? '+' : '') + bench.dayChangePercent + '%'">
                        </div>
                        <div class="portfolio-vs-benchmark" x-show="portfolioDayChange !== null && bench.dayChangePercent !== null">
                            <span :class="parseFloat(portfolioDayChange) > bench.dayChangePercent ? 'better' : 'worse'"
                                  x-text="parseFloat(portfolioDayChange) > bench.dayChangePercent ? 'You: +' + (parseFloat(portfolioDayChange) - bench.dayChangePercent).toFixed(2) + '%' : 'You: ' + (parseFloat(portfolioDayChange) - bench.dayChangePercent).toFixed(2) + '%'">
                            </span>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Holdings/Watchlist Tabs -->
        <div class="view-tabs" x-show="stocks.length > 0">
            <button class="view-tab" :class="{ active: viewMode === 'all' }" @click="viewMode = 'all'">
                All<span class="count" x-text="'(' + stocks.length + ')'"></span>
            </button>
            <button class="view-tab" :class="{ active: viewMode === 'holdings' }" @click="viewMode = 'holdings'">
                Holdings<span class="count" x-text="'(' + holdingsCount + ')'"></span>
            </button>
            <button class="view-tab" :class="{ active: viewMode === 'watchlist' }" @click="viewMode = 'watchlist'">
                Watchlist<span class="count" x-text="'(' + watchlistCount + ')'"></span>
            </button>
        </div>

        <!-- Portfolio Charts Toggle -->
        <button class="chart-toggle-btn"
                :class="{ active: showPortfolioCharts }"
                @click="togglePortfolioCharts()"
                x-show="stocks.length > 0">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path>
                <path d="M22 12A10 10 0 0 0 12 2v10z"></path>
            </svg>
            <span x-text="showPortfolioCharts ? 'Hide Portfolio Charts' : 'Show Portfolio Charts'"></span>
        </button>

        <!-- Portfolio Charts -->
        <div class="portfolio-charts" x-show="showPortfolioCharts && stocks.length > 0" x-collapse>
            <div class="portfolio-chart-card">
                <h4>Allocation by Stock</h4>
                <div class="portfolio-chart-wrapper">
                    <canvas id="allocation-by-stock-chart"></canvas>
                </div>
            </div>
            <div class="portfolio-chart-card" x-show="uniqueAccounts.length > 0">
                <h4>Allocation by Account</h4>
                <div class="portfolio-chart-wrapper">
                    <canvas id="allocation-by-account-chart"></canvas>
                </div>
            </div>
        </div>

        <!-- Controls Bar (Sort/Filter/Search) -->
        <div class="controls-bar" x-show="stocks.length > 0">
            <div class="search-box">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="M21 21l-4.35-4.35"></path>
                </svg>
                <input type="text" x-model="searchQuery" placeholder="Search stocks...">
            </div>

            <select x-model="sortBy">
                <option value="symbol">Sort: Symbol (A-Z)</option>
                <option value="symbol-desc">Sort: Symbol (Z-A)</option>
                <option value="price-high">Sort: Price (High-Low)</option>
                <option value="price-low">Sort: Price (Low-High)</option>
                <option value="change-high">Sort: Day Change (Best)</option>
                <option value="change-low">Sort: Day Change (Worst)</option>
                <option value="gain-high">Sort: Total Gain (Best)</option>
                <option value="gain-low">Sort: Total Gain (Worst)</option>
                <option value="value-high">Sort: Position Value (High)</option>
                <option value="value-low">Sort: Position Value (Low)</option>
            </select>

            <select x-model="filterAccount" x-show="uniqueAccounts.length > 0">
                <option value="">All Accounts</option>
                <template x-for="acc in uniqueAccounts" :key="acc">
                    <option :value="acc" x-text="acc"></option>
                </template>
            </select>

            <div class="filter-pills">
                <span class="filter-pill" :class="{ active: filterType === 'all' }" @click="filterType = 'all'">All</span>
                <span class="filter-pill gainers" :class="{ active: filterType === 'gainers' }" @click="filterType = 'gainers'">Gainers</span>
                <span class="filter-pill losers" :class="{ active: filterType === 'losers' }" @click="filterType = 'losers'">Losers</span>
            </div>

            <span class="results-count" x-show="filteredStocks.length !== stocks.length">
                Showing <span x-text="filteredStocks.length"></span> of <span x-text="stocks.length"></span>
            </span>
        </div>

        <!-- Loading State -->
        <div x-show="loading" style="text-align: center; padding: 40px;">
            <div class="loading"></div>
            <p style="margin-top: 16px; color: var(--pico-muted-color);">Loading your watchlist...</p>
        </div>

        <!-- Empty State -->
        <div class="empty-state" x-show="!loading && stocks.length === 0">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M3 3v18h18M7 16l4-4 4 4 5-6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <h3>No stocks yet</h3>
            <p>Add your first stock to start tracking your portfolio</p>
            <button @click="openAddModal()" class="primary" style="margin-top: 16px;">Add Your First Stock</button>
        </div>

        <!-- Stock Cards Grid -->
        <div class="stocks-grid" x-show="!loading && stocks.length > 0">
            <template x-for="stock in filteredStocks" :key="stock.id">
                <article class="stock-card" :class="{ 'price-flash': stock.priceChanged, 'up': stock.priceUp, 'down': !stock.priceUp }">
                    <div class="stock-card-header">
                        <div>
                            <div class="stock-symbol">
                                <span x-text="stock.symbol"></span>
                                <span class="watchlist-badge" x-show="stock.is_watchlist">Watching</span>
                            </div>
                            <div class="stock-company" x-text="stock.company_name"></div>
                            <div class="stock-account" x-show="stock.account" x-text="stock.account"></div>
                        </div>
                        <div class="stock-price">
                            <div class="current" x-text="stock.quote ? '$' + stock.quote.price.toFixed(2) : '—'"></div>
                            <button class="alert-btn"
                                    :class="{ 'has-alerts': getStockAlerts(stock.id).length > 0 }"
                                    @click="openAlertModal(stock)"
                                    style="margin-top: 8px;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                                </svg>
                                <span x-text="getStockAlerts(stock.id).length > 0 ? getStockAlerts(stock.id).length : 'Alert'"></span>
                            </button>
                        </div>
                    </div>

                    <!-- Period Changes Grid -->
                    <div class="period-changes" x-show="stock.quote && stock.quote.changes">
                        <template x-for="[key, period] in Object.entries(stock.quote?.changes || {})" :key="key">
                            <div class="period-change">
                                <div class="label" x-text="period.label"></div>
                                <div class="value" :class="period.change >= 0 ? 'up' : 'down'"
                                     x-text="period.change !== null ? ((period.change >= 0 ? '+' : '') + period.changePercent.toFixed(2) + '%') : '—'">
                                </div>
                            </div>
                        </template>
                        <!-- Total (from purchase) -->
                        <div class="period-change" x-show="stock.purchase_price && stock.quote">
                            <div class="label">Total</div>
                            <div class="value"
                                 :class="(stock.quote.price - parseFloat(stock.purchase_price)) >= 0 ? 'up' : 'down'"
                                 x-text="(((stock.quote.price - parseFloat(stock.purchase_price)) / parseFloat(stock.purchase_price)) * 100).toFixed(2) + '%'">
                            </div>
                        </div>
                    </div>

                    <!-- Stock Metrics (52-week, P/E, etc.) -->
                    <div class="stock-metrics" x-show="stock.quote && (stock.quote.fiftyTwoWeekHigh || stock.quote.marketCap || stock.quote.trailingPE)">
                        <div class="stock-metric" x-show="stock.quote?.marketCap">
                            <span class="metric-label">Market Cap</span>
                            <span class="metric-value" x-text="formatMarketCap(stock.quote?.marketCap)"></span>
                        </div>
                        <div class="stock-metric" x-show="stock.quote?.trailingPE">
                            <span class="metric-label">P/E Ratio</span>
                            <span class="metric-value" x-text="stock.quote?.trailingPE"></span>
                        </div>
                        <div class="stock-metric" x-show="stock.quote?.dividendYield">
                            <span class="metric-label">Div Yield</span>
                            <span class="metric-value" x-text="stock.quote?.dividendYield + '%'"></span>
                        </div>
                        <div class="stock-metric" x-show="!stock.quote?.dividendYield && stock.quote?.trailingPE">
                            <span class="metric-label">Div Yield</span>
                            <span class="metric-value" style="color: var(--pico-muted-color);">N/A</span>
                        </div>
                        <!-- 52-Week Range Bar -->
                        <div class="range-52w" x-show="stock.quote?.fiftyTwoWeekHigh && stock.quote?.fiftyTwoWeekLow">
                            <div class="range-labels">
                                <span x-text="'$' + stock.quote?.fiftyTwoWeekLow?.toFixed(2)">52W Low</span>
                                <span style="color: var(--pico-color); font-weight: 500;">52-Week Range</span>
                                <span x-text="'$' + stock.quote?.fiftyTwoWeekHigh?.toFixed(2)">52W High</span>
                            </div>
                            <div class="range-bar">
                                <div class="range-fill"></div>
                                <div class="current-marker" :style="'left: ' + (stock.quote?.fiftyTwoWeekRangePercent || 50) + '%'"></div>
                            </div>
                        </div>
                    </div>

                    <div class="stock-details" x-show="stock.purchase_price || stock.shares">
                        <div class="stock-detail" x-show="stock.purchase_price">
                            <label>Purchase Price</label>
                            <span x-text="'$' + parseFloat(stock.purchase_price).toFixed(2)"></span>
                        </div>
                        <div class="stock-detail" x-show="stock.shares">
                            <label>Shares</label>
                            <span x-text="parseFloat(stock.shares).toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 4})"></span>
                        </div>
                        <div class="stock-detail" x-show="stock.purchase_price && stock.shares && stock.quote">
                            <label>Position Value</label>
                            <span x-text="stock.quote ? '$' + (stock.quote.price * parseFloat(stock.shares)).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '—'"></span>
                        </div>
                        <div class="stock-detail" x-show="stock.purchase_price && stock.shares && stock.quote">
                            <label>Gain/Loss</label>
                            <span :class="stock.quote && (stock.quote.price - parseFloat(stock.purchase_price)) * parseFloat(stock.shares) >= 0 ? 'profit' : 'loss'"
                                  x-text="stock.quote ? (((stock.quote.price - parseFloat(stock.purchase_price)) * parseFloat(stock.shares) >= 0 ? '+' : '') + '$' + ((stock.quote.price - parseFloat(stock.purchase_price)) * parseFloat(stock.shares)).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})) : '—'"></span>
                        </div>
                    </div>

                    <p class="stock-notes" x-show="stock.notes" x-text="stock.notes"></p>

                    <!-- Price Chart Section -->
                    <div class="chart-section" x-show="stock.quote">
                        <div class="chart-toggle"
                             :class="{ expanded: stock.chartExpanded }"
                             @click="toggleChart(stock)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline>
                                <polyline points="16 7 22 7 22 13"></polyline>
                            </svg>
                            <span x-text="stock.chartExpanded ? 'Hide Chart' : 'Show Price Chart'"></span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left: auto;">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </div>

                        <div class="chart-container" x-show="stock.chartExpanded" x-collapse>
                            <div class="chart-range-buttons">
                                <template x-for="range in ['1d', '1w', '1m', '3m', '1y', '5y']" :key="range">
                                    <button class="chart-range-btn"
                                            :class="{ active: stock.chartRange === range }"
                                            @click="loadChart(stock, range)"
                                            x-text="range.toUpperCase()">
                                    </button>
                                </template>
                            </div>
                            <div class="chart-canvas-wrapper">
                                <div class="chart-loading" x-show="stock.chartLoading">
                                    <div class="loading"></div>
                                    <span>Loading chart...</span>
                                </div>
                                <canvas :id="'chart-' + stock.id"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- News Section -->
                    <div class="news-section" x-show="stock.quote">
                        <div class="news-toggle" @click="toggleNews(stock)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 20H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v1m2 13a2 2 0 0 1-2-2V7m2 13a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2m-4-3H9M7 16h6M7 12h10"></path>
                            </svg>
                            <span x-text="stock.newsExpanded ? 'Hide News' : 'Show News'"></span>
                        </div>
                        <div class="news-list" x-show="stock.newsExpanded" x-collapse>
                            <div class="news-loading" x-show="stock.newsLoading">Loading news...</div>
                            <template x-for="item in stock.news || []" :key="item.link">
                                <a :href="item.link" target="_blank" rel="noopener" class="news-item">
                                    <div class="news-title" x-text="item.title"></div>
                                    <div class="news-meta">
                                        <span x-text="item.publisher"></span>
                                        <span x-show="item.publishedAt"> · <span x-text="item.publishedAt"></span></span>
                                    </div>
                                </a>
                            </template>
                            <div class="news-loading" x-show="!stock.newsLoading && (!stock.news || stock.news.length === 0)">
                                No recent news found
                            </div>
                        </div>
                    </div>

                    <!-- Dividend Section -->
                    <div class="dividend-section" x-show="stock.quote && !stock.is_watchlist">
                        <div class="dividend-toggle" @click="toggleDividends(stock)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="1" x2="12" y2="23"></line>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                            </svg>
                            <span x-text="stock.dividendsExpanded ? 'Hide Dividends' : 'Show Dividends'"></span>
                            <span x-show="stock.dividendData?.annualDividend > 0" style="margin-left: auto; color: var(--green); font-weight: 500;"
                                  x-text="'$' + stock.dividendData?.annualDividend?.toFixed(2) + '/yr'"></span>
                        </div>
                        <div x-show="stock.dividendsExpanded" x-collapse>
                            <div class="news-loading" x-show="stock.dividendsLoading">Loading dividend info...</div>
                            <template x-if="stock.dividendData && !stock.dividendsLoading">
                                <div>
                                    <div class="dividend-summary">
                                        <div class="dividend-stat">
                                            <div class="label">Annual Dividend</div>
                                            <div class="value" x-text="'$' + (stock.dividendData.annualDividend || 0).toFixed(2)"></div>
                                        </div>
                                        <div class="dividend-stat">
                                            <div class="label">Yield</div>
                                            <div class="value" x-text="(stock.dividendData.dividendYield || 0).toFixed(2) + '%'"></div>
                                        </div>
                                        <div class="dividend-stat" x-show="stock.shares">
                                            <div class="label">Annual Income</div>
                                            <div class="value" x-text="'$' + ((stock.dividendData.annualDividend || 0) * parseFloat(stock.shares || 0)).toFixed(2)"></div>
                                        </div>
                                        <div class="dividend-stat" x-show="stock.shares">
                                            <div class="label">Monthly Income</div>
                                            <div class="value" x-text="'$' + (((stock.dividendData.annualDividend || 0) * parseFloat(stock.shares || 0)) / 12).toFixed(2)"></div>
                                        </div>
                                    </div>
                                    <div class="dividend-history" x-show="stock.dividendData.dividends && stock.dividendData.dividends.length > 0">
                                        <div style="font-size: 0.75rem; color: var(--pico-muted-color); margin-bottom: 8px; text-transform: uppercase;">Recent Payments</div>
                                        <template x-for="div in (stock.dividendData.dividends || []).slice(0, 5)" :key="div.date">
                                            <div class="dividend-item">
                                                <span class="date" x-text="div.date"></span>
                                                <span class="amount" x-text="'$' + div.amount.toFixed(4)"></span>
                                            </div>
                                        </template>
                                    </div>
                                    <div class="news-loading" x-show="!stock.dividendData.dividends || stock.dividendData.dividends.length === 0">
                                        No dividend history found
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="stock-actions">
                        <button class="secondary outline" @click="openEditModal(stock)">Edit</button>
                        <button class="secondary outline" style="color: var(--red); border-color: var(--red);" @click="confirmDelete(stock)">Delete</button>
                    </div>
                </article>
            </template>
        </div>
    </main>

    <!-- Add/Edit Modal -->
    <dialog :open="showModal" @click.self="closeModal()">
        <article style="min-width: 400px; max-width: 90vw;">
            <header>
                <button aria-label="Close" rel="prev" @click="closeModal()"></button>
                <h3 x-text="editingStock ? 'Edit Stock' : 'Add Stock'"></h3>
            </header>
            <form @submit.prevent="saveStock()">
                <div class="watchlist-toggle">
                    <label>
                        <input type="checkbox" x-model="form.is_watchlist">
                        Watchlist only (no position)
                    </label>
                    <span class="hint">Check this if you just want to track the price without owning shares</span>
                </div>
                <label>
                    Symbol *
                    <input type="text" x-model="form.symbol" placeholder="AAPL" required
                           :readonly="editingStock" maxlength="10" style="text-transform: uppercase;">
                </label>
                <label>
                    Company Name *
                    <input type="text" x-model="form.company_name" placeholder="Apple Inc." required>
                </label>
                <div x-show="!form.is_watchlist">
                    <label>
                        Account
                        <select x-model="form.accountSelection" @change="handleAccountSelection()">
                            <option value="">— No Account —</option>
                            <template x-for="acc in uniqueAccounts" :key="acc">
                                <option :value="acc" x-text="acc"></option>
                            </template>
                            <option value="__new__">+ Add New Account...</option>
                        </select>
                    </label>
                    <div x-show="form.accountSelection === '__new__'" x-collapse style="margin-top: 8px;">
                        <input type="text" x-model="form.newAccountName"
                               placeholder="Enter new account name (e.g., Fidelity 401k)"
                               @input="form.account = form.newAccountName">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;" x-show="!form.is_watchlist">
                    <label>
                        Purchase Price
                        <input type="number" x-model="form.purchase_price" placeholder="150.00" step="0.01" min="0">
                    </label>
                    <label>
                        Shares
                        <input type="number" x-model="form.shares" placeholder="10" step="0.0001" min="0">
                    </label>
                </div>
                <label>
                    Notes
                    <textarea x-model="form.notes" placeholder="Optional notes about this position..."></textarea>
                </label>
                <footer style="display: flex; gap: 8px; justify-content: flex-end;">
                    <button type="button" class="secondary" @click="closeModal()">Cancel</button>
                    <button type="submit" :disabled="saving">
                        <span x-show="saving" class="loading" style="width: 16px; height: 16px; margin-right: 8px;"></span>
                        <span x-text="editingStock ? 'Update' : 'Add Stock'"></span>
                    </button>
                </footer>
            </form>
        </article>
    </dialog>

    <!-- Delete Confirmation Modal -->
    <dialog :open="showDeleteModal" @click.self="showDeleteModal = false">
        <article style="min-width: 350px;">
            <header>
                <h3>Delete Stock</h3>
            </header>
            <p>Are you sure you want to delete <strong x-text="deletingStock?.symbol"></strong> from your watchlist?</p>
            <footer style="display: flex; gap: 8px; justify-content: flex-end;">
                <button class="secondary" @click="showDeleteModal = false">Cancel</button>
                <button style="background: var(--red);" @click="deleteStock()" :disabled="deleting">
                    <span x-show="deleting" class="loading" style="width: 16px; height: 16px; margin-right: 8px;"></span>
                    Delete
                </button>
            </footer>
        </article>
    </dialog>

    <!-- Alert Modal -->
    <dialog :open="showAlertModal" @click.self="closeAlertModal()">
        <article style="min-width: 400px; max-width: 90vw;">
            <header>
                <button aria-label="Close" rel="prev" @click="closeAlertModal()"></button>
                <h3>
                    <span x-text="alertStock?.symbol"></span> Price Alerts
                </h3>
            </header>

            <!-- Notification Permission Banner -->
            <div class="notification-permission" x-show="notificationPermission === 'default'">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px;">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <span>Enable browser notifications to receive alert triggers</span>
                <button class="primary" @click="requestNotificationPermission()">Enable</button>
            </div>

            <!-- Existing Alerts -->
            <div class="alert-list" x-show="getStockAlerts(alertStock?.id).length > 0">
                <h4 style="font-size: 0.85rem; color: var(--pico-muted-color); margin-bottom: 12px;">Active Alerts</h4>
                <template x-for="alert in getStockAlerts(alertStock?.id)" :key="alert.id">
                    <div class="alert-item" :class="{ triggered: alert.triggered }">
                        <div class="alert-info">
                            <span class="alert-condition" :class="alert.condition" x-text="alert.condition"></span>
                            <span x-text="'$' + parseFloat(alert.target_price).toFixed(2)"></span>
                        </div>
                        <button class="delete-alert" @click="deleteAlert(alert.id)">Remove</button>
                    </div>
                </template>
            </div>

            <!-- Create New Alert -->
            <form @submit.prevent="createAlert()">
                <h4 style="font-size: 0.85rem; color: var(--pico-muted-color); margin-bottom: 12px;">Create New Alert</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                    <label>
                        Condition
                        <select x-model="alertForm.condition">
                            <option value="above">Price goes above</option>
                            <option value="below">Price goes below</option>
                        </select>
                    </label>
                    <label>
                        Target Price
                        <input type="number" x-model="alertForm.target_price" step="0.01" min="0" required
                               :placeholder="alertStock?.quote?.price?.toFixed(2) || '0.00'">
                    </label>
                </div>
                <p style="font-size: 0.8rem; color: var(--pico-muted-color); margin-bottom: 16px;">
                    Current price: <strong x-text="alertStock?.quote ? '$' + alertStock.quote.price.toFixed(2) : 'N/A'"></strong>
                </p>
                <footer style="display: flex; gap: 8px; justify-content: flex-end;">
                    <button type="button" class="secondary" @click="closeAlertModal()">Close</button>
                    <button type="submit" :disabled="savingAlert">
                        <span x-show="savingAlert" class="loading" style="width: 16px; height: 16px; margin-right: 8px;"></span>
                        Create Alert
                    </button>
                </footer>
            </form>
        </article>
    </dialog>

    <!-- Toast Notifications -->
    <div class="toast-container">
        <template x-for="toast in toasts" :key="toast.id">
            <div class="toast" :class="toast.type" x-text="toast.message"
                 x-init="setTimeout(() => removeToast(toast.id), 3000)"></div>
        </template>
    </div>

    <script>
        function stockApp() {
            return {
                stocks: [],
                tickerItems: [],
                loading: true,
                showModal: false,
                showDeleteModal: false,
                editingStock: null,
                deletingStock: null,
                saving: false,
                deleting: false,
                toasts: [],
                form: {
                    symbol: '',
                    company_name: '',
                    account: '',
                    accountSelection: '',
                    newAccountName: '',
                    purchase_price: '',
                    shares: '',
                    notes: '',
                    is_watchlist: false
                },
                // Sort/Filter/Search state
                searchQuery: '',
                sortBy: 'symbol',
                filterType: 'all',
                filterAccount: '',
                viewMode: 'all', // 'all', 'holdings', 'watchlist'
                // Charts state
                charts: {},
                showPortfolioCharts: false,
                portfolioCharts: {},
                // Alerts state
                alerts: [],
                showAlertModal: false,
                alertStock: null,
                savingAlert: false,
                alertForm: {
                    condition: 'above',
                    target_price: ''
                },
                notificationPermission: 'default',
                refreshInterval: null,
                lastUpdate: null,
                updateCounter: 0,
                errorCount: 0,
                backoffMultiplier: 1,
                // Benchmark data
                benchmarks: {},

                get isMarketOpen() {
                    const now = new Date();
                    const day = now.getDay();
                    // Weekend check (0 = Sunday, 6 = Saturday)
                    if (day === 0 || day === 6) return false;

                    // Convert to ET (approximate - doesn't handle DST perfectly)
                    const utcHour = now.getUTCHours();
                    const utcMin = now.getUTCMinutes();
                    const etHour = (utcHour - 5 + 24) % 24; // EST offset

                    // Market hours: 9:30 AM - 4:00 PM ET
                    const marketOpen = etHour > 9 || (etHour === 9 && utcMin >= 30);
                    const marketClose = etHour < 16;

                    return marketOpen && marketClose;
                },

                get lastUpdateAgo() {
                    if (!this.lastUpdate) return '';
                    const seconds = Math.floor((Date.now() - this.lastUpdate) / 1000);
                    if (seconds < 5) return 'just now';
                    if (seconds < 60) return `${seconds}s ago`;
                    const minutes = Math.floor(seconds / 60);
                    return `${minutes}m ago`;
                },

                get holdingsCount() {
                    return this.stocks.filter(s => !s.is_watchlist).length;
                },

                get watchlistCount() {
                    return this.stocks.filter(s => s.is_watchlist).length;
                },

                get portfolioDayChange() {
                    const holdings = this.stocks.filter(s => !s.is_watchlist && s.quote?.changes?.day && s.shares);
                    if (holdings.length === 0) return null;

                    let totalPrevValue = 0;
                    let totalCurrentValue = 0;

                    holdings.forEach(s => {
                        const shares = parseFloat(s.shares);
                        const currentPrice = s.quote.price;
                        const prevPrice = s.quote.changes.day.basePrice;
                        totalCurrentValue += currentPrice * shares;
                        totalPrevValue += prevPrice * shares;
                    });

                    if (totalPrevValue === 0) return null;
                    return ((totalCurrentValue - totalPrevValue) / totalPrevValue * 100).toFixed(2);
                },

                async init() {
                    await this.loadStocks();
                    await this.loadAlerts();
                    await this.loadBenchmarks();
                    this.startAutoRefresh();
                    // Update the "ago" display every second
                    setInterval(() => this.updateCounter++, 1000);
                    // Check notification permission
                    if ('Notification' in window) {
                        this.notificationPermission = Notification.permission;
                    }
                },

                async loadBenchmarks() {
                    try {
                        const res = await fetch('api.php?action=benchmark&range=1d');
                        const data = await res.json();
                        if (data.benchmarks) {
                            this.benchmarks = data.benchmarks;
                        }
                    } catch (e) {
                        console.error('Failed to load benchmarks', e);
                    }
                },

                startAutoRefresh() {
                    // Clear existing interval
                    if (this.refreshInterval) clearInterval(this.refreshInterval);

                    // Base: 5s during market hours, 60s otherwise
                    // Apply backoff multiplier if rate limited
                    const baseInterval = this.isMarketOpen ? 5000 : 60000;
                    const interval = baseInterval * this.backoffMultiplier;
                    this.refreshInterval = setInterval(() => this.refreshQuotes(), interval);

                    // Re-check market status and backoff every 30 seconds
                    setTimeout(() => this.startAutoRefresh(), 30000);
                },

                async loadStocks() {
                    this.loading = true;
                    try {
                        const res = await fetch('api.php?action=list');
                        const data = await res.json();
                        if (data.stocks) {
                            this.stocks = data.stocks;
                            await this.refreshQuotes();
                        }
                    } catch (e) {
                        this.showToast('Failed to load stocks', 'error');
                    }
                    this.loading = false;
                },

                async refreshQuotes() {
                    let hadError = false;
                    const promises = this.stocks.map(async (stock) => {
                        try {
                            const res = await fetch(`api.php?action=quote&symbol=${encodeURIComponent(stock.symbol)}`);
                            if (!res.ok) {
                                hadError = true;
                                return;
                            }
                            const data = await res.json();
                            if (data.quote) {
                                // Track if price changed for animation
                                const oldPrice = stock.quote?.price;
                                stock.quote = data.quote;
                                stock.priceChanged = oldPrice && oldPrice !== data.quote.price;
                                if (stock.priceChanged) {
                                    stock.priceUp = data.quote.price > oldPrice;
                                    setTimeout(() => stock.priceChanged = false, 1000);
                                }
                            }
                        } catch (e) {
                            console.error(`Failed to fetch quote for ${stock.symbol}`, e);
                            hadError = true;
                        }
                    });
                    await Promise.all(promises);

                    // Handle rate limiting with exponential backoff
                    if (hadError) {
                        this.errorCount++;
                        this.backoffMultiplier = Math.min(this.backoffMultiplier * 2, 12); // Max 60s (5s * 12)
                        console.warn(`Rate limited, backing off to ${5 * this.backoffMultiplier}s`);
                    } else {
                        this.errorCount = 0;
                        this.backoffMultiplier = 1;
                        this.lastUpdate = Date.now();
                    }

                    this.updateTicker();

                    // Check alerts after refreshing quotes
                    await this.checkAlerts();
                },

                updateTicker() {
                    this.tickerItems = this.stocks
                        .filter(s => s.quote && s.quote.changes)
                        .map(s => ({
                            symbol: s.symbol,
                            price: s.quote.price,
                            change: s.quote.changes.day?.change ?? 0,
                            changePercent: s.quote.changes.day?.changePercent ?? 0
                        }));
                },

                get totalValue() {
                    return this.stocks.reduce((sum, s) => {
                        if (!s.is_watchlist && s.quote && s.shares) {
                            return sum + (s.quote.price * parseFloat(s.shares));
                        }
                        return sum;
                    }, 0);
                },

                get totalCost() {
                    return this.stocks.reduce((sum, s) => {
                        if (!s.is_watchlist && s.purchase_price && s.shares) {
                            return sum + (parseFloat(s.purchase_price) * parseFloat(s.shares));
                        }
                        return sum;
                    }, 0);
                },

                get totalGain() {
                    return this.totalValue - this.totalCost;
                },

                get uniqueAccounts() {
                    const accounts = this.stocks
                        .map(s => s.account)
                        .filter(a => a && a.trim() !== '');
                    return [...new Set(accounts)].sort();
                },

                get filteredStocks() {
                    let result = [...this.stocks];

                    // View mode filter (holdings vs watchlist)
                    if (this.viewMode === 'holdings') {
                        result = result.filter(s => !s.is_watchlist);
                    } else if (this.viewMode === 'watchlist') {
                        result = result.filter(s => s.is_watchlist);
                    }

                    // Search filter
                    if (this.searchQuery.trim()) {
                        const query = this.searchQuery.toLowerCase();
                        result = result.filter(s =>
                            s.symbol.toLowerCase().includes(query) ||
                            s.company_name.toLowerCase().includes(query) ||
                            (s.account && s.account.toLowerCase().includes(query))
                        );
                    }

                    // Account filter
                    if (this.filterAccount) {
                        result = result.filter(s => s.account === this.filterAccount);
                    }

                    // Gainers/Losers filter (based on day change)
                    if (this.filterType === 'gainers') {
                        result = result.filter(s => s.quote?.changes?.day?.change >= 0);
                    } else if (this.filterType === 'losers') {
                        result = result.filter(s => s.quote?.changes?.day?.change < 0);
                    }

                    // Sorting
                    result.sort((a, b) => {
                        switch (this.sortBy) {
                            case 'symbol':
                                return a.symbol.localeCompare(b.symbol);
                            case 'symbol-desc':
                                return b.symbol.localeCompare(a.symbol);
                            case 'price-high':
                                return (b.quote?.price || 0) - (a.quote?.price || 0);
                            case 'price-low':
                                return (a.quote?.price || 0) - (b.quote?.price || 0);
                            case 'change-high':
                                return (b.quote?.changes?.day?.changePercent || -999) - (a.quote?.changes?.day?.changePercent || -999);
                            case 'change-low':
                                return (a.quote?.changes?.day?.changePercent || 999) - (b.quote?.changes?.day?.changePercent || 999);
                            case 'gain-high':
                                return this.getGainPercent(b) - this.getGainPercent(a);
                            case 'gain-low':
                                return this.getGainPercent(a) - this.getGainPercent(b);
                            case 'value-high':
                                return this.getPositionValue(b) - this.getPositionValue(a);
                            case 'value-low':
                                return this.getPositionValue(a) - this.getPositionValue(b);
                            default:
                                return 0;
                        }
                    });

                    return result;
                },

                getGainPercent(stock) {
                    if (!stock.purchase_price || !stock.quote?.price) return -9999;
                    return ((stock.quote.price - parseFloat(stock.purchase_price)) / parseFloat(stock.purchase_price)) * 100;
                },

                getPositionValue(stock) {
                    if (!stock.shares || !stock.quote?.price) return 0;
                    return stock.quote.price * parseFloat(stock.shares);
                },

                openAddModal() {
                    this.editingStock = null;
                    this.form = {
                        symbol: '',
                        company_name: '',
                        account: '',
                        accountSelection: '',
                        newAccountName: '',
                        purchase_price: '',
                        shares: '',
                        notes: '',
                        is_watchlist: false
                    };
                    this.showModal = true;
                },

                openEditModal(stock) {
                    this.editingStock = stock;
                    const existingAccount = stock.account || '';
                    const isExistingAccount = existingAccount && this.uniqueAccounts.includes(existingAccount);
                    this.form = {
                        symbol: stock.symbol,
                        company_name: stock.company_name,
                        account: existingAccount,
                        accountSelection: isExistingAccount ? existingAccount : (existingAccount ? '__new__' : ''),
                        newAccountName: isExistingAccount ? '' : existingAccount,
                        purchase_price: stock.purchase_price || '',
                        shares: stock.shares || '',
                        notes: stock.notes || '',
                        is_watchlist: !!stock.is_watchlist
                    };
                    this.showModal = true;
                },

                closeModal() {
                    this.showModal = false;
                    this.editingStock = null;
                },

                handleAccountSelection() {
                    if (this.form.accountSelection === '__new__') {
                        // User wants to add a new account - clear account, wait for input
                        this.form.account = this.form.newAccountName || '';
                    } else {
                        // User selected an existing account or "No Account"
                        this.form.account = this.form.accountSelection;
                        this.form.newAccountName = '';
                    }
                },

                async saveStock() {
                    this.saving = true;
                    const url = this.editingStock
                        ? `api.php?action=update&id=${this.editingStock.id}`
                        : 'api.php?action=create';

                    try {
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(this.form)
                        });
                        const data = await res.json();

                        if (res.ok) {
                            this.showToast(data.message || 'Stock saved successfully', 'success');
                            this.closeModal();
                            await this.loadStocks();
                        } else {
                            this.showToast(data.error || 'Failed to save stock', 'error');
                        }
                    } catch (e) {
                        this.showToast('Failed to save stock', 'error');
                    }
                    this.saving = false;
                },

                confirmDelete(stock) {
                    this.deletingStock = stock;
                    this.showDeleteModal = true;
                },

                async deleteStock() {
                    this.deleting = true;
                    try {
                        const res = await fetch(`api.php?action=delete&id=${this.deletingStock.id}`, {
                            method: 'POST'
                        });
                        const data = await res.json();

                        if (res.ok) {
                            this.showToast('Stock deleted successfully', 'success');
                            this.showDeleteModal = false;
                            this.deletingStock = null;
                            await this.loadStocks();
                        } else {
                            this.showToast(data.error || 'Failed to delete stock', 'error');
                        }
                    } catch (e) {
                        this.showToast('Failed to delete stock', 'error');
                    }
                    this.deleting = false;
                },

                showToast(message, type = 'success') {
                    const id = Date.now();
                    this.toasts.push({ id, message, type });
                },

                removeToast(id) {
                    this.toasts = this.toasts.filter(t => t.id !== id);
                },

                formatMarketCap(value) {
                    if (!value) return 'N/A';
                    if (value >= 1e12) return '$' + (value / 1e12).toFixed(2) + 'T';
                    if (value >= 1e9) return '$' + (value / 1e9).toFixed(2) + 'B';
                    if (value >= 1e6) return '$' + (value / 1e6).toFixed(2) + 'M';
                    return '$' + value.toLocaleString();
                },

                toggleChart(stock) {
                    stock.chartExpanded = !stock.chartExpanded;
                    if (stock.chartExpanded && !stock.chartData) {
                        stock.chartRange = '1m';
                        this.loadChart(stock, '1m');
                    }
                },

                async loadChart(stock, range) {
                    stock.chartRange = range;
                    stock.chartLoading = true;

                    try {
                        const res = await fetch(`api.php?action=history&symbol=${encodeURIComponent(stock.symbol)}&range=${range}`);
                        const data = await res.json();

                        if (data.history && data.history.data) {
                            stock.chartData = data.history.data;
                            this.renderChart(stock);
                        }
                    } catch (e) {
                        console.error(`Failed to load chart for ${stock.symbol}`, e);
                    }

                    stock.chartLoading = false;
                },

                renderChart(stock) {
                    const canvasId = 'chart-' + stock.id;
                    const canvas = document.getElementById(canvasId);
                    if (!canvas || !stock.chartData) return;

                    // Destroy existing chart if any
                    if (this.charts[stock.id]) {
                        this.charts[stock.id].destroy();
                    }

                    const ctx = canvas.getContext('2d');
                    const labels = stock.chartData.map(d => d.date);
                    const prices = stock.chartData.map(d => d.price);

                    // Determine chart color based on price direction
                    const firstPrice = prices[0] || 0;
                    const lastPrice = prices[prices.length - 1] || 0;
                    const isUp = lastPrice >= firstPrice;
                    const lineColor = isUp ? '#3fb950' : '#f85149';
                    const bgColor = isUp ? 'rgba(63, 185, 80, 0.1)' : 'rgba(248, 81, 73, 0.1)';

                    this.charts[stock.id] = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                data: prices,
                                borderColor: lineColor,
                                backgroundColor: bgColor,
                                borderWidth: 2,
                                fill: true,
                                tension: 0.1,
                                pointRadius: 0,
                                pointHoverRadius: 4,
                                pointHoverBackgroundColor: lineColor,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    mode: 'index',
                                    intersect: false,
                                    backgroundColor: 'rgba(22, 27, 34, 0.9)',
                                    titleColor: '#e6edf3',
                                    bodyColor: '#e6edf3',
                                    borderColor: 'rgba(255, 255, 255, 0.1)',
                                    borderWidth: 1,
                                    callbacks: {
                                        label: (context) => '$' + context.parsed.y.toFixed(2)
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    display: false,
                                },
                                y: {
                                    display: true,
                                    grid: {
                                        color: 'rgba(255, 255, 255, 0.05)',
                                    },
                                    ticks: {
                                        color: '#8b949e',
                                        font: { size: 10 },
                                        callback: (value) => '$' + value.toFixed(0)
                                    }
                                }
                            },
                            interaction: {
                                mode: 'nearest',
                                axis: 'x',
                                intersect: false
                            }
                        }
                    });
                },

                togglePortfolioCharts() {
                    this.showPortfolioCharts = !this.showPortfolioCharts;
                    if (this.showPortfolioCharts) {
                        // Use nextTick to ensure canvas is rendered before creating chart
                        this.$nextTick(() => this.renderPortfolioCharts());
                    }
                },

                renderPortfolioCharts() {
                    // Destroy existing charts
                    if (this.portfolioCharts.byStock) {
                        this.portfolioCharts.byStock.destroy();
                    }
                    if (this.portfolioCharts.byAccount) {
                        this.portfolioCharts.byAccount.destroy();
                    }

                    // Color palette for charts
                    const colors = [
                        '#58a6ff', '#3fb950', '#f85149', '#a371f7', '#f0883e',
                        '#56d4dd', '#db61a2', '#7ee787', '#79c0ff', '#ffa657'
                    ];

                    // Allocation by Stock Chart
                    const stockCanvas = document.getElementById('allocation-by-stock-chart');
                    if (stockCanvas) {
                        const stockData = this.stocks
                            .filter(s => s.quote && s.shares)
                            .map(s => ({
                                label: s.symbol,
                                value: s.quote.price * parseFloat(s.shares)
                            }))
                            .sort((a, b) => b.value - a.value);

                        if (stockData.length > 0) {
                            this.portfolioCharts.byStock = new Chart(stockCanvas.getContext('2d'), {
                                type: 'doughnut',
                                data: {
                                    labels: stockData.map(d => d.label),
                                    datasets: [{
                                        data: stockData.map(d => d.value),
                                        backgroundColor: stockData.map((_, i) => colors[i % colors.length]),
                                        borderColor: 'rgba(22, 27, 34, 0.8)',
                                        borderWidth: 2,
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            position: 'right',
                                            labels: {
                                                color: '#8b949e',
                                                font: { size: 11 },
                                                boxWidth: 12,
                                                padding: 8,
                                            }
                                        },
                                        tooltip: {
                                            backgroundColor: 'rgba(22, 27, 34, 0.9)',
                                            titleColor: '#e6edf3',
                                            bodyColor: '#e6edf3',
                                            callbacks: {
                                                label: (ctx) => {
                                                    const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                                    const pct = ((ctx.raw / total) * 100).toFixed(1);
                                                    return `$${ctx.raw.toLocaleString('en-US', {minimumFractionDigits: 2})} (${pct}%)`;
                                                }
                                            }
                                        }
                                    }
                                }
                            });
                        }
                    }

                    // Allocation by Account Chart
                    const accountCanvas = document.getElementById('allocation-by-account-chart');
                    if (accountCanvas && this.uniqueAccounts.length > 0) {
                        const accountTotals = {};
                        this.stocks.forEach(s => {
                            if (s.quote && s.shares) {
                                const acc = s.account || 'Unassigned';
                                accountTotals[acc] = (accountTotals[acc] || 0) + (s.quote.price * parseFloat(s.shares));
                            }
                        });

                        const accountData = Object.entries(accountTotals)
                            .map(([label, value]) => ({ label, value }))
                            .sort((a, b) => b.value - a.value);

                        if (accountData.length > 0) {
                            this.portfolioCharts.byAccount = new Chart(accountCanvas.getContext('2d'), {
                                type: 'doughnut',
                                data: {
                                    labels: accountData.map(d => d.label),
                                    datasets: [{
                                        data: accountData.map(d => d.value),
                                        backgroundColor: accountData.map((_, i) => colors[i % colors.length]),
                                        borderColor: 'rgba(22, 27, 34, 0.8)',
                                        borderWidth: 2,
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            position: 'right',
                                            labels: {
                                                color: '#8b949e',
                                                font: { size: 11 },
                                                boxWidth: 12,
                                                padding: 8,
                                            }
                                        },
                                        tooltip: {
                                            backgroundColor: 'rgba(22, 27, 34, 0.9)',
                                            titleColor: '#e6edf3',
                                            bodyColor: '#e6edf3',
                                            callbacks: {
                                                label: (ctx) => {
                                                    const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                                    const pct = ((ctx.raw / total) * 100).toFixed(1);
                                                    return `$${ctx.raw.toLocaleString('en-US', {minimumFractionDigits: 2})} (${pct}%)`;
                                                }
                                            }
                                        }
                                    }
                                }
                            });
                        }
                    }
                },

                // Alert Methods
                async loadAlerts() {
                    try {
                        const res = await fetch('api.php?action=alerts');
                        const data = await res.json();
                        if (data.alerts) {
                            this.alerts = data.alerts;
                        }
                    } catch (e) {
                        console.error('Failed to load alerts', e);
                    }
                },

                getStockAlerts(stockId) {
                    if (!stockId) return [];
                    return this.alerts.filter(a => a.stock_id == stockId && !a.triggered);
                },

                openAlertModal(stock) {
                    this.alertStock = stock;
                    this.alertForm = { condition: 'above', target_price: '' };
                    this.showAlertModal = true;
                },

                closeAlertModal() {
                    this.showAlertModal = false;
                    this.alertStock = null;
                },

                async createAlert() {
                    if (!this.alertStock || !this.alertForm.target_price) return;

                    this.savingAlert = true;
                    try {
                        const res = await fetch('api.php?action=createAlert', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                stock_id: this.alertStock.id,
                                symbol: this.alertStock.symbol,
                                condition: this.alertForm.condition,
                                target_price: this.alertForm.target_price
                            })
                        });
                        const data = await res.json();

                        if (res.ok && data.alert) {
                            this.alerts.push(data.alert);
                            this.alertForm = { condition: 'above', target_price: '' };
                            this.showToast('Alert created successfully', 'success');
                        } else {
                            this.showToast(data.error || 'Failed to create alert', 'error');
                        }
                    } catch (e) {
                        this.showToast('Failed to create alert', 'error');
                    }
                    this.savingAlert = false;
                },

                async deleteAlert(alertId) {
                    try {
                        const res = await fetch(`api.php?action=deleteAlert&id=${alertId}`, {
                            method: 'POST'
                        });

                        if (res.ok) {
                            this.alerts = this.alerts.filter(a => a.id !== alertId);
                            this.showToast('Alert removed', 'success');
                        }
                    } catch (e) {
                        this.showToast('Failed to delete alert', 'error');
                    }
                },

                async checkAlerts() {
                    // Build quotes map
                    const quotes = {};
                    this.stocks.forEach(s => {
                        if (s.quote) {
                            quotes[s.symbol] = s.quote.price;
                        }
                    });

                    if (Object.keys(quotes).length === 0) return;

                    try {
                        const res = await fetch('api.php?action=checkAlerts', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ quotes })
                        });
                        const data = await res.json();

                        if (data.triggered && data.triggered.length > 0) {
                            // Update local alerts state
                            data.triggered.forEach(t => {
                                const alert = this.alerts.find(a => a.id === t.id);
                                if (alert) alert.triggered = 1;
                            });

                            // Send browser notifications
                            data.triggered.forEach(t => {
                                this.sendNotification(t);
                            });
                        }
                    } catch (e) {
                        console.error('Failed to check alerts', e);
                    }
                },

                async requestNotificationPermission() {
                    if (!('Notification' in window)) {
                        this.showToast('Browser does not support notifications', 'error');
                        return;
                    }

                    const permission = await Notification.requestPermission();
                    this.notificationPermission = permission;

                    if (permission === 'granted') {
                        this.showToast('Notifications enabled', 'success');
                    } else {
                        this.showToast('Notification permission denied', 'error');
                    }
                },

                sendNotification(alert) {
                    if (this.notificationPermission !== 'granted') return;

                    const direction = alert.condition === 'above' ? 'above' : 'below';
                    const title = `${alert.symbol} Price Alert`;
                    const body = `${alert.symbol} is now $${alert.current_price.toFixed(2)} (${direction} $${alert.target_price.toFixed(2)})`;

                    new Notification(title, {
                        body: body,
                        icon: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%2358a6ff"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
                        tag: `stockd-alert-${alert.id}`,
                    });

                    // Also show toast
                    this.showToast(`${alert.symbol} hit $${alert.target_price.toFixed(2)}!`, 'success');
                },

                // News Methods
                async toggleNews(stock) {
                    stock.newsExpanded = !stock.newsExpanded;
                    if (stock.newsExpanded && !stock.news) {
                        await this.loadNews(stock);
                    }
                },

                async loadNews(stock) {
                    stock.newsLoading = true;
                    try {
                        const res = await fetch(`api.php?action=news&symbol=${encodeURIComponent(stock.symbol)}`);
                        const data = await res.json();
                        stock.news = data.news || [];
                    } catch (e) {
                        console.error(`Failed to load news for ${stock.symbol}`, e);
                        stock.news = [];
                    }
                    stock.newsLoading = false;
                },

                // Dividend Methods
                async toggleDividends(stock) {
                    stock.dividendsExpanded = !stock.dividendsExpanded;
                    if (stock.dividendsExpanded && !stock.dividendData) {
                        await this.loadDividends(stock);
                    }
                },

                async loadDividends(stock) {
                    stock.dividendsLoading = true;
                    try {
                        const res = await fetch(`api.php?action=dividends&symbol=${encodeURIComponent(stock.symbol)}`);
                        const data = await res.json();
                        stock.dividendData = data;
                    } catch (e) {
                        console.error(`Failed to load dividends for ${stock.symbol}`, e);
                        stock.dividendData = { dividends: [], annualDividend: 0, dividendYield: 0 };
                    }
                    stock.dividendsLoading = false;
                },

                // Export Method
                exportPortfolio() {
                    window.location.href = 'api.php?action=export&format=csv';
                    this.showToast('Downloading portfolio CSV...', 'success');
                }
            };
        }

        // Register Service Worker for PWA
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js')
                    .then((reg) => console.log('Service Worker registered', reg.scope))
                    .catch((err) => console.log('Service Worker registration failed', err));
            });
        }
    </script>
</body>
</html>

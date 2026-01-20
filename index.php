<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stockd - Stock Watchlist</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
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

        /* Responsive */
        @media (max-width: 768px) {
            .header { flex-direction: column; align-items: flex-start; }
            .stocks-grid { grid-template-columns: 1fr; }
            .ticker-item { padding: 0 16px; }
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
            <button @click="openAddModal()" class="primary">
                + Add Stock
            </button>
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
            <template x-for="stock in stocks" :key="stock.id">
                <article class="stock-card" :class="{ 'price-flash': stock.priceChanged, 'up': stock.priceUp, 'down': !stock.priceUp }">
                    <div class="stock-card-header">
                        <div>
                            <div class="stock-symbol" x-text="stock.symbol"></div>
                            <div class="stock-company" x-text="stock.company_name"></div>
                            <div class="stock-account" x-show="stock.account" x-text="stock.account"></div>
                        </div>
                        <div class="stock-price">
                            <div class="current" x-text="stock.quote ? '$' + stock.quote.price.toFixed(2) : '—'"></div>
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
                <label>
                    Symbol *
                    <input type="text" x-model="form.symbol" placeholder="AAPL" required
                           :readonly="editingStock" maxlength="10" style="text-transform: uppercase;">
                </label>
                <label>
                    Company Name *
                    <input type="text" x-model="form.company_name" placeholder="Apple Inc." required>
                </label>
                <label>
                    Account
                    <input type="text" x-model="form.account" placeholder="e.g., Brokerage, 401k, IRA">
                </label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
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
                    purchase_price: '',
                    shares: '',
                    notes: ''
                },
                refreshInterval: null,
                lastUpdate: null,
                updateCounter: 0,
                errorCount: 0,
                backoffMultiplier: 1,

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

                async init() {
                    await this.loadStocks();
                    this.startAutoRefresh();
                    // Update the "ago" display every second
                    setInterval(() => this.updateCounter++, 1000);
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
                        if (s.quote && s.shares) {
                            return sum + (s.quote.price * parseFloat(s.shares));
                        }
                        return sum;
                    }, 0);
                },

                get totalCost() {
                    return this.stocks.reduce((sum, s) => {
                        if (s.purchase_price && s.shares) {
                            return sum + (parseFloat(s.purchase_price) * parseFloat(s.shares));
                        }
                        return sum;
                    }, 0);
                },

                get totalGain() {
                    return this.totalValue - this.totalCost;
                },

                openAddModal() {
                    this.editingStock = null;
                    this.form = { symbol: '', company_name: '', account: '', purchase_price: '', shares: '', notes: '' };
                    this.showModal = true;
                },

                openEditModal(stock) {
                    this.editingStock = stock;
                    this.form = {
                        symbol: stock.symbol,
                        company_name: stock.company_name,
                        account: stock.account || '',
                        purchase_price: stock.purchase_price || '',
                        shares: stock.shares || '',
                        notes: stock.notes || ''
                    };
                    this.showModal = true;
                },

                closeModal() {
                    this.showModal = false;
                    this.editingStock = null;
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
                }
            };
        }
    </script>
</body>
</html>

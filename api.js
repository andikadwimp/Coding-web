// API Client
const API = {
    base: '/api', // Change to your path e.g. '/api'

    async call(endpoint, action, data = {}) {
        try {
            const res = await fetch(`${this.base}/${endpoint}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ action, ...data })
            });
            const json = await res.json();
            if (!json.ok && json.error === 'NOT_LOGGED_IN') {
                window.location.href = 'index.php';
                return null;
            }
            return json;
        } catch (e) {
            console.error('API Error:', e);
            return { ok: false, error: 'NETWORK_ERROR' };
        }
    },

    // Auth
    login(username, password) { return this.call('auth.php', 'login', { username, password }); },
    register(data) { return this.call('auth.php', 'register', data); },
    logout() { return this.call('auth.php', 'logout'); },
    me() { return this.call('auth.php', 'me'); },
    updateProfile(data) { return this.call('auth.php', 'update_profile', data); },
    changePassword(data) { return this.call('auth.php', 'change_password', data); },
    updateBank(data) { return this.call('auth.php', 'update_bank', data); },

    // Deposit
    createDeposit(data) { return this.call('deposit.php', 'create', data); },
    confirmDeposit(tx_id) { return this.call('deposit.php', 'confirm', { tx_id }); },
    listDeposits() { return this.call('deposit.php', 'list'); },
    getBonuses() { return this.call('deposit.php', 'bonuses'); },
    checkDeposit(tx_id) { return this.call('deposit.php', 'check', { tx_id }); },
    pendingDeposits() { return this.call('deposit.php', 'pending'); },
    reconcileMyDeposits() { return this.call('deposit.php', 'reconcile_mine'); },

    // Withdraw
    createWithdraw(data) { return this.call('withdraw.php', 'create', data); },
    listWithdraws() { return this.call('withdraw.php', 'list'); },

    // Data
    getMemos() { return this.call('data.php', 'memo_inbox'); },
    readMemo(memo_id) { return this.call('data.php', 'memo_read', { memo_id }); },
    getAnnouncements() { return this.call('data.php', 'memo_announcements'); },
    getDownlines() { return this.call('data.php', 'referral_downline'); },
    getReferralBonus() { return this.call('data.php', 'referral_bonus'); },
    getHistory(type = 'all') { return this.call('data.php', 'history', { type }); },
    getLevelInfo() { return this.call('data.php', 'level_info'); },
    getBanners() { return this.call('data.php', 'banners'); },
    getPromos() { return this.call('data.php', 'promos'); },
    getSettings() { return this.call('data.php', 'settings'); },

    // Game (NexusGGR)
    getProviders() { return this.call('game.php', 'providers'); },
    getGames(provider) { return this.call('game.php', 'games', { provider }); },
    launchGame(provider, game_code, lang='id') { return this.call('game.php', 'launch', { provider, game_code, lang }); },
    getGameBalance() { return this.call('game.php', 'game_balance'); },
    withdrawFromGame() { return this.call('game.php', 'withdraw_game'); },
    syncTurnover() { return this.call('game.php', 'sync_turnover'); },
    getGameHistory(data) { return this.call('game.php', 'history', data); },

    // Admin
    admin(action, data = {}) { return this.call('admin.php', action, data); },

    // Helpers
    fmt(n) { return 'Rp ' + (n || 0).toLocaleString('id-ID'); },
    mask(s, show) {
        if (!s) return '-';
        if (s.length <= show * 2) return s;
        return s.substring(0, show) + '*'.repeat(s.length - show * 2) + s.substring(s.length - show);
    }
};

// Check session
API.ensureSession = async function() {
    try {
        const me = await this.call('auth.php', 'me');
        if (me && me.ok) return true;
    } catch(e) {}
    return false;
};

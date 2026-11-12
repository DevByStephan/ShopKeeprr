import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('productBuyBox', (variants, plans, mode, basePrice) => ({
    variantId: variants.length ? variants[0].id : null,
    planId: plans.length ? plans[0].id : null,
    quantity: 1,
    mode,
    basePrice,
    get selectedVariant() {
        return variants.find((variant) => variant.id === this.variantId) || null;
    },
    get selectedPlan() {
        return plans.find((plan) => plan.id === this.planId) || null;
    },
    get price() {
        if (this.mode === 'subscription' && this.selectedPlan) {
            return this.selectedPlan.price;
        }
        return this.selectedVariant?.price ?? this.basePrice;
    },
    increment() {
        this.quantity += 1;
    },
    decrement() {
        if (this.quantity > 1) {
            this.quantity -= 1;
        }
    },
}));

Alpine.data('checkoutForm', (config) => ({
    country: config.country || 'US',
    state: config.state || '',
    shippingMethodId: config.shippingMethodId || null,
    options: config.options || [],
    totals: config.totals || {},
    loadingShipping: false,
    async refreshShipping() {
        if (this.country.length !== 2) {
            return;
        }
        this.loadingShipping = true;
        try {
            const response = await fetch(config.shippingUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrf,
                },
                body: JSON.stringify({ country: this.country, state: this.state }),
            });
            const data = await response.json();
            this.options = data.options || [];
            this.totals = data.totals || this.totals;
            if (!this.options.some((option) => Number(option.id) === Number(this.shippingMethodId))) {
                this.shippingMethodId = this.options[0]?.id ?? null;
            }
        } finally {
            this.loadingShipping = false;
        }
    },
}));

Alpine.data('storefrontCarousel', (slides, autoplay = false) => ({
    slides: Array.isArray(slides) ? slides : [],
    index: 0,
    timer: null,
    boot() {
        if (this.slides.length < 2 || ! autoplay) return;
        this.start();
    },
    start() {
        this.stop();
        this.timer = setInterval(() => this.next(), 6500);
    },
    stop() {
        if (this.timer) clearInterval(this.timer);
        this.timer = null;
    },
    restart() {
        if (this.slides.length > 1 && this.timer !== null) this.start();
    },
    go(index) {
        this.index = index;
        this.restart();
    },
    next() {
        if (! this.slides.length) return;
        this.index = (this.index + 1) % this.slides.length;
    },
    prev() {
        if (! this.slides.length) return;
        this.index = (this.index - 1 + this.slides.length) % this.slides.length;
    },
    destroy() {
        this.stop();
    },
}));

Alpine.data('countdownBanner', (endsAt) => ({
    expired: false,
    expiredAt: Number(endsAt) || 0,
    parts: { days: '00', hours: '00', minutes: '00', seconds: '00' },
    timer: null,
    boot() {
        this.tick();
        if (! this.expired) this.timer = setInterval(() => this.tick(), 1000);
    },
    tick() {
        const seconds = Math.max(0, this.expiredAt - Math.floor(Date.now() / 1000));

        if (seconds <= 0) {
            this.expired = true;
            if (this.timer) clearInterval(this.timer);
            this.timer = null;
            return;
        }

        const pad = (value) => String(value).padStart(2, '0');
        this.parts = {
            days: String(Math.floor(seconds / 86400)),
            hours: pad(Math.floor((seconds % 86400) / 3600)),
            minutes: pad(Math.floor((seconds % 3600) / 60)),
            seconds: pad(seconds % 60),
        };
    },
    destroy() {
        if (this.timer) clearInterval(this.timer);
    },
}));

Alpine.start();

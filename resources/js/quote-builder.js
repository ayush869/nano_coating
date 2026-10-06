import { priceQuote } from './pricing.js';
import { lineHelpers, uid, clone, uploadPhoto } from './lines.js';

/**
 * Staff quote builder (Alpine component). Prices live with pricing.js; the
 * server re-prices everything on save with PricingService.php.
 */
export default (config) => ({
    ...lineHelpers(config.card),
    card: config.card,
    states: config.states,
    companyState: config.companyState,
    discountLimit: config.discountLimit,
    lockCustomer: config.lockCustomer,
    urls: config.urls,

    customer: null,
    customerQuery: '',
    customerResults: [],
    searching: false,
    newCustomerMode: false,
    newCustomer: { name: '', phone: '', email: '', gstin: '' },
    newSiteMode: false,
    newSite: { label: '', address: '', city: '', pincode: '', state_code: config.companyState },
    site_id: null,
    service_request_id: null,

    discount_type: '',
    discount_value: 0,
    visit_charge: 0,
    notes: '',
    lines: [],
    rules: null,
    refresh_rates: false,
    saving: false,
    uploading: {},
    picker: false,
    pickerService: null,
    pickerQuery: '',

    init() {
        const s = config.old ?? config.state;
        this.customer = s.customer ?? null;
        this.site_id = s.site_id ?? null;
        this.service_request_id = s.service_request_id ?? null;
        this.discount_type = s.discount_type ?? '';
        this.discount_value = s.discount_value ?? 0;
        this.visit_charge = s.visit_charge ?? this.card.visit_charge;
        this.notes = s.notes ?? '';
        this.rules = s.rules ?? this.card.rules;
        this.refresh_rates = !!s.refresh_rates;
        if (s.new_customer?.name) {
            this.newCustomerMode = true;
            this.newCustomer = { ...this.newCustomer, ...s.new_customer };
        }
        if (s.new_site?.address) {
            this.newSiteMode = true;
            this.newSite = { ...this.newSite, ...s.new_site };
        }
        this.lines = (s.lines ?? []).map((l) => {
            const line = { uid: uid(), snap: {}, photos: [], photo_urls: [], addon_id: '', ...clone(l) };
            line.addon_id = line.addon_id ?? '';
            this.hydrateInputs(line);
            return line;
        });
        if (!this.lines.length && !config.isEdit) this.picker = true;
    },

    // ---------- pricing ----------
    get siteState() {
        if (this.newCustomerMode || this.newSiteMode) return this.newSite.state_code;
        return this.customer?.sites?.find((s) => s.id === Number(this.site_id))?.state_code ?? this.companyState;
    },

    get intraState() {
        return this.siteState === this.companyState;
    },

    get priced() {
        return priceQuote(
            this.lines.map((l) => this.toPricing(l)),
            { discount_type: this.discount_type || null, discount_value: this.discount_value, visit_charge: this.visit_charge, intra_state: this.intraState },
            this.rules,
        );
    },

    get overLimit() {
        return this.priced.discount_pct > this.discountLimit + 1e-9;
    },

    get pickerProducts() {
        const q = this.pickerQuery.trim().toLowerCase();
        return this.card.products.filter((p) =>
            (!this.pickerService || p.service_id === this.pickerService) && (!q || p.name.toLowerCase().includes(q)));
    },

    gstHalf() {
        return this.num(this.rules.gst_pct / 2);
    },

    // ---------- lines ----------
    addProduct(productId) {
        const line = this.newLine(productId);
        this.lines.push(line);
        this.picker = false;
        this.pickerQuery = '';
        this.$nextTick(() => document.getElementById(`line-${line.uid}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
    },

    duplicate(i) {
        // A copy is a new line: priced at today's rate card, like the server will.
        const copy = clone(this.lines[i]);
        copy.uid = uid();
        copy.id = null;
        copy.snap = {};
        if (!this.product(copy)) {
            alert('This product is no longer active, so it cannot be copied.');
            return;
        }
        this.hydrateInputs(copy);
        this.lines.splice(i + 1, 0, copy);
    },

    move(i, dir) {
        const j = i + dir;
        if (j < 0 || j >= this.lines.length) return;
        const [l] = this.lines.splice(i, 1);
        this.lines.splice(j, 0, l);
    },

    remove(i) {
        if (confirm('Remove this item?')) this.lines.splice(i, 1);
    },

    refreshRates() {
        if (!confirm("Re-price every item at today's products, rate card and rules? Saved rates on this quote will be replaced.")) return;
        for (const l of this.lines) {
            l.id = null;
            l.snap = {};
            this.hydrateInputs(l);
        }
        this.rules = this.card.rules;
        this.refresh_rates = true;
    },

    async addPhotos(line, event) {
        const files = [...event.target.files];
        this.uploading[line.uid] = true;
        try {
            for (const file of files.slice(0, 8 - line.photos.length)) {
                const data = await uploadPhoto(this.urls.photo, this.urls.csrf, file);
                line.photos.push(data.path);
                line.photo_urls.push(data.url);
            }
        } catch (e) {
            alert(e.message);
        } finally {
            this.uploading[line.uid] = false;
            event.target.value = '';
        }
    },

    removePhoto(line, k) {
        line.photos.splice(k, 1);
        line.photo_urls.splice(k, 1);
    },

    // ---------- customer ----------
    async searchCustomers() {
        const q = this.customerQuery.trim();
        if (q.length < 2) {
            this.customerResults = [];
            return;
        }
        this.searching = true;
        try {
            const res = await fetch(`${this.urls.search}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
            this.customerResults = await res.json();
        } finally {
            this.searching = false;
        }
    },

    selectCustomer(c) {
        this.customer = c;
        this.site_id = c.sites[0]?.id ?? null;
        this.customerResults = [];
        this.customerQuery = '';
        this.newCustomerMode = false;
        this.newSiteMode = !c.sites.length;
    },

    startNewCustomer() {
        this.customer = null;
        this.newCustomerMode = true;
        const digits = this.customerQuery.replace(/\D/g, '');
        if (digits.length >= 10) this.newCustomer.phone = digits;
        else if (this.customerQuery.trim()) this.newCustomer.name = this.customerQuery.trim();
        this.customerResults = [];
    },

    // ---------- save ----------
    payload() {
        return JSON.stringify({
            customer: this.customer,
            customer_id: this.newCustomerMode ? null : this.customer?.id,
            site_id: this.newSiteMode ? null : this.site_id,
            service_request_id: this.service_request_id,
            new_customer: this.newCustomerMode ? this.newCustomer : null,
            new_site: this.newCustomerMode || this.newSiteMode ? this.newSite : null,
            discount_type: this.discount_type || null,
            discount_value: this.discount_value,
            visit_charge: this.visit_charge,
            notes: this.notes,
            rules: this.rules,
            refresh_rates: this.refresh_rates,
            lines: this.lines.map((l) => ({ ...this.payloadLine(l), photo_urls: l.photo_urls })),
        });
    },

    submit(event) {
        if (this.lines.some((l, i) => this.priced.lines[i]?.error)) {
            alert('One of the items has a formula error. Please fix it before saving.');
            return;
        }
        this.saving = true;
        event.target.querySelector('input[name=payload]').value = this.payload();
        event.target.submit();
    },
});

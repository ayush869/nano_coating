import { priceQuote } from './pricing.js';
import { lineHelpers, uid, clone, uploadPhoto } from './lines.js';

const DRAFT_KEY = 'nc-request-draft-v1';

/**
 * Customer "request a visit" wizard: pick items → photos → address & time →
 * review. Shows a live estimate with the same engine the server uses.
 */
export default (config) => ({
    ...lineHelpers(config.card),
    card: config.card,
    states: config.states,
    companyState: config.companyState,
    urls: config.urls,
    sites: config.sites,

    step: 1,
    lines: [],
    photos: [],
    photo_urls: [],
    notes: '',
    site_id: config.sites[0]?.id ?? null,
    newSiteMode: !config.sites.length,
    newSite: { label: 'Home', address: '', city: '', pincode: '', state_code: config.companyState },
    preferred_date: '',
    preferred_slot: 'morning',
    service: null,
    uploading: {},
    saving: false,

    init() {
        if (config.old) {
            this.restore(config.old);
        } else {
            try {
                const d = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null');
                if (d && d.lines?.length) this.restore(d);
            } catch (e) { /* storage unavailable */ }
        }
        if (config.preselect.service) this.service = config.preselect.service;
        if (config.preselect.product && !this.lines.some((l) => l.product_id === config.preselect.product)) {
            if (this.card.products.some((p) => p.id === config.preselect.product)) this.add(config.preselect.product);
        }
        if (!this.service) this.service = this.card.services[0]?.id ?? null;
        this.$watch('lines', () => this.saveDraft(), { deep: true });
    },

    restore(d) {
        this.lines = (d.lines || []).filter((l) => this.card.products.some((p) => p.id === Number(l.product_id))).map((l) => {
            const line = { uid: uid(), snap: {}, photos: [], photo_urls: [], addon_id: '', ...clone(l) };
            this.hydrateInputs(line);
            return line;
        });
        this.notes = d.notes ?? '';
        this.photos = d.photos ?? [];
        this.photo_urls = d.photo_urls ?? [];
        if (d.preferred_date) this.preferred_date = d.preferred_date;
        if (d.preferred_slot) this.preferred_slot = d.preferred_slot;
        if (d.new_site?.address) {
            this.newSiteMode = true;
            this.newSite = { ...this.newSite, ...d.new_site };
        }
    },

    saveDraft() {
        try {
            localStorage.setItem(DRAFT_KEY, JSON.stringify({
                lines: this.lines.map((l) => ({ ...this.payloadLine(l), photo_urls: l.photo_urls })),
                notes: this.notes, photos: this.photos, photo_urls: this.photo_urls,
            }));
        } catch (e) { /* storage unavailable */ }
    },

    get siteState() {
        return this.newSiteMode ? this.newSite.state_code : (this.sites.find((s) => s.id === Number(this.site_id))?.state_code ?? this.companyState);
    },

    get estimate() {
        return priceQuote(
            this.lines.map((l) => this.toPricing(l)),
            { visit_charge: this.card.visit_charge, intra_state: this.siteState === this.companyState },
            this.card.rules,
        );
    },

    get servicesWithProducts() {
        return this.card.services.filter((s) => this.card.products.some((p) => p.service_id === s.id));
    },

    countOf(productId) {
        return this.lines.filter((l) => l.product_id === productId).length;
    },

    add(productId) {
        const line = this.newLine(productId);
        line.name = '';
        this.lines.push(line);
        this.$nextTick(() => document.getElementById(`item-${line.uid}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
    },

    remove(i) {
        this.lines.splice(i, 1);
    },

    async addPhotos(target, event) {
        const files = [...event.target.files];
        const key = target ? target.uid : 'site';
        this.uploading[key] = true;
        try {
            for (const file of files.slice(0, 8)) {
                const data = await uploadPhoto(this.urls.photo, this.urls.csrf, file);
                if (target) {
                    target.photos.push(data.path);
                    target.photo_urls.push(data.url);
                } else {
                    this.photos.push(data.path);
                    this.photo_urls.push(data.url);
                }
            }
            this.saveDraft();
        } catch (e) {
            alert(e.message);
        } finally {
            this.uploading[key] = false;
            event.target.value = '';
        }
    },

    removePhoto(target, k) {
        const t = target ?? this;
        t.photos.splice(k, 1);
        t.photo_urls.splice(k, 1);
        this.saveDraft();
    },

    canNext() {
        if (this.step === 1) return this.lines.length > 0 && this.estimate.lines.every((r) => !r.error && r.area > 0);
        if (this.step === 3) return this.newSiteMode ? this.newSite.address.trim() && this.newSite.state_code : !!this.site_id;
        return true;
    },

    go(step) {
        this.step = step;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    submit(event) {
        this.saving = true;
        event.target.querySelector('input[name=payload]').value = JSON.stringify({
            lines: this.lines.map((l) => this.payloadLine(l)),
            photos: this.photos,
            notes: this.notes,
            site_id: this.newSiteMode ? null : this.site_id,
            new_site: this.newSiteMode ? this.newSite : null,
            preferred_date: this.preferred_date || null,
            preferred_slot: this.preferred_slot,
        });
        try { localStorage.removeItem(DRAFT_KEY); } catch (e) { /* ignore */ }
        event.target.submit();
    },
});

/**
 * Starts an online payment from the customer's quote page. With Razorpay it
 * opens Razorpay Checkout and posts the signed result back for verification;
 * with the built-in test gateway it goes to the simulated checkout page.
 */
function loadRazorpay() {
    if (window.Razorpay) return Promise.resolve();
    return new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = 'https://checkout.razorpay.com/v1/checkout.js';
        s.onload = resolve;
        s.onerror = () => reject(new Error('Could not load Razorpay. Check your internet connection.'));
        document.head.appendChild(s);
    });
}

const post = async (url, csrf, body) => {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || 'Something went wrong. Please try again.');
    return data;
};

export default (config) => ({
    busy: false,
    error: null,

    async pay(purpose) {
        this.busy = true;
        this.error = null;
        try {
            const start = await post(config.startUrl, config.csrf, { purpose });
            if (start.gateway === 'test') {
                window.location = start.redirect;
                return;
            }
            await loadRazorpay();
            const rzp = new window.Razorpay({
                ...start.options,
                handler: async (response) => {
                    try {
                        const done = await post(start.verify_url, config.csrf, response);
                        window.location = done.redirect;
                    } catch (e) {
                        this.error = e.message;
                        this.busy = false;
                    }
                },
                modal: {
                    ondismiss: () => {
                        this.busy = false;
                        post(start.failed_url, config.csrf, { reason: 'Checkout closed by customer' }).catch(() => {});
                    },
                },
            });
            rzp.on('payment.failed', (r) => {
                this.error = r.error?.description || 'Payment failed. No money was taken; please try again.';
                post(start.failed_url, config.csrf, { reason: this.error }).catch(() => {});
            });
            rzp.open();
        } catch (e) {
            this.error = e.message;
            this.busy = false;
        }
    },
});

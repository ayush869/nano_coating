# Nano Coating: Website, Quotations & Payments

A complete system for a nano coating service business. It has three parts:

- **Public website.** Customers browse services, see typical prices, build an instant estimate, upload photos and request a site visit.
- **Customer portal.** Customers sign in with a one-time code on their phone, track requests, accept quotations online, pay an advance or the full amount through Razorpay, pay the balance, and download receipts and GST invoices.
- **Staff panel.** Admins assign technicians to visits. Technicians measure on site and turn the request into a quotation. Staff send quotes on WhatsApp or email, then handle jobs, invoices, offline payments and reports. Admins design **products with their own formulas**: sofas, curved chairs, walls, anything.

Built with Laravel 12, MySQL/MariaDB, Blade, Alpine.js, Tailwind and dompdf.

## Setup (XAMPP on Windows, or any PHP 8.2+ host)

```bash
composer install
npm install && npm run build
cp .env.example .env          # then set DB_* if not XAMPP defaults
php artisan key:generate
# create the database once, e.g. in phpMyAdmin: nano_coating (utf8mb4)
php artisan migrate --seed    # tables + rate card + 20 starter products + one admin
```

Then open:

| | URL (XAMPP) |
|---|---|
| Website | `http://localhost/nano_coating/public` |
| Customer portal | `…/public/login` (then `/account`) |
| Staff panel | `…/public/staff` |

Or run `php artisan serve` and use `http://127.0.0.1:8000` (set `APP_URL` to match).

### Login

A fresh install has one admin account and the starter products (with their formulas and photos). There are no demo customers, quotes or payments.

| Staff login | Password | Role |
|---|---|---|
| admin@gmail.com | 123456 | Admin: everything, including products, formulas, rates, users |

Add sales and technician accounts in Admin → Users.

**Customers** sign in with their mobile number. While `OTP_SHOW_ON_SCREEN=true`, the code appears on the screen, so you can test without an SMS provider. Any 10-digit mobile number creates a new customer account.

### Designing products (formulas)

Admin → Products & formulas → New product. Pick a starting point, then:

- **Build automatically from inputs** (on by default): the area formula follows the inputs as you add them. Counted parts with a matching size are added up (`seats * seat_sqft + …`), lengths and choices multiply, and inputs named `openings` or `deduct…` are subtracted.
- **Quick add a counted part**: type "Ottoman, 6 sq ft" and the counter, its size and the formula term are created in one click.
- **Price rules, no formula typing**: minimum billed area, a volume discount and an extra charge per item build the price formula for you.
- Every formula is also shown **in words**, and the **Try it** panel prices it live.

**Before going live:** change the admin password, replace the company profile, GSTIN and bank details (Admin → Company profile), set `OTP_SHOW_ON_SCREEN=false`, connect an SMS provider, and add Razorpay live keys.

## Payments (Razorpay)

| `PAYMENT_GATEWAY` | What happens |
|---|---|
| `test` (default) | A built-in **test checkout**: "simulate success / failure". No money moves. It uses the same signature check and the same automation as the real gateway. |
| `razorpay` | Razorpay Checkout (UPI, cards, net banking, wallets). Falls back to `test` if keys are missing. |

To test with Razorpay's own test mode:

1. Create a free account at https://dashboard.razorpay.com, switch to **Test mode**, then open **Account & Settings → API keys** and generate keys.
2. Put them in `.env`:
   ```
   PAYMENT_GATEWAY=razorpay
   RAZORPAY_KEY_ID=rzp_test_xxxxxxxxxxxx
   RAZORPAY_KEY_SECRET=xxxxxxxxxxxxxxxx
   RAZORPAY_WEBHOOK_SECRET=any-long-random-string
   ```
3. `php artisan config:clear`. Admin → Rules, GST & payments shows "Razorpay is connected (TEST mode)".
4. Optional but recommended: in the Razorpay dashboard, add a webhook for `payment.captured`, `payment.failed` and `order.paid` to `https://your-domain/webhooks/razorpay`, using the same secret. The webhook completes a payment even if the customer closes the browser after paying. It needs a public URL, so for local testing use a tunnel such as ngrok.

Use rzp_live_ keys for real money. How it works: the server creates a Razorpay **order**, the browser opens Checkout, and the server verifies the HMAC signature before marking anything paid. Webhooks are signature-checked, amount-checked and idempotent, so a duplicate delivery can't pay twice.

**Payment options** (Admin → Rules, GST & payments): advance % (default 50), allow full payment, create the job automatically on payment, issue the invoice automatically when the job is done. Staff can also record **cash, UPI, bank transfer or cheque** received offline. Every payment gets a receipt number (`RCPT/2026-27/0001`) and a PDF receipt.

## The flow

```
Customer: website → estimate + photos → request (SR/2026-27/0001)
Admin:    assigns technician + visit time            → customer gets email/SMS
Tech:     visits, measures, "Create quotation from this visit" (items prefilled)
          → sends (WhatsApp / email / portal)        → request = Quoted
Customer: reviews in portal → accepts and pays advance (Razorpay)
          → quote Converted, job created for the same technician
Tech:     marks job Done (adds before/after photos)  → tax invoice issued automatically
Customer: pays balance online → fully paid
```

Quote statuses: Draft → Sent → Accepted / Rejected / Expired → Converted to job → Invoiced. Revisions keep the number with `-R1`, `-R2`; a quote with payments can't be revised. Discounts above a role's limit need admin approval before sending, and technicians always need approval for a discount.

## Products and formulas (no hard-coded sofa)

Every item is an admin-defined **product**: Admin → Products & formulas → **New product**. Start from a template (parts, measured surface, fixed piece, S/M/L sizes, running feet, wall, or blank) and change anything:

1. **Inputs**: counters (+/−), lengths with a unit (ft, in, m or cm; converted to feet automatically), choice lists (each choice is a number, e.g. Small = 8, Large = 16), and plain numbers. Choose which inputs customers fill in and which use the default.
2. **Constants**: standard sizes you can tune without touching the formula, e.g. `seat_sqft = 4.5`.
3. **Area formula**: e.g. `seats * seat_sqft + backs * back_sqft`, or `max(0, height * width - openings)`.
4. **Price formula**: default `area * rate * material * design`. Write your own, e.g. a volume discount `area * rate * material * design * if(area > 500, 0.9, 1)`, or a fixed rate per running foot `area * rate`.
5. **Rates**: use the coating rate card (and which coatings are allowed), or a fixed rate per unit. Set a minimum charge per product, or turn it off.

Formulas support `+ - * / ^`, brackets, comparisons, `&& || !`, and `min max round ceil floor abs sqrt if`. They are parsed by a safe engine; nothing is ever passed to `eval`. The designer validates names and formulas as you type and has a **Try it** panel that prices the product live. The server re-validates on save, including a dry run with the default values. **Duplicate** a product to make a variant quickly, e.g. another chair size.

The same engine prices the customer's estimate, the staff quote builder and the saved quote:

- `app/Services/Pricing/Formula.php` and `resources/js/formula.js`: the formula engine
- `app/Services/Pricing/PricingService.php` and `resources/js/pricing.js`: product line + GST totals

The PHP and JS versions are line-for-line ports, tested against shared fixtures (`tests/fixtures/*.json`), so the browser preview and the server always agree. The server always re-prices; browser totals are ignored.

```
area        = area formula (inputs, constants)           never below 0
rate        = coating + add-on   (or the product's fixed rate)
unit price  = price formula (area, rate, material, design, coating, addon, inputs, constants)
line        = max(unit price × qty, minimum charge)      rounded to 2 decimals
taxable     = max(subtotal − discount + visit charge, minimum job charge)
GST         = CGST + SGST if site state = company state, else IGST
```

## Snapshots and audit

Each quote line stores a copy of its **product definition** (inputs, constants, formulas), the coating, add-on, material and design names and rates, the inputs, the photos and the formula text. Each quote also stores GST %, the minimums, the advance % and the customer/site details. **Changing a product, a rate or a rule never changes a saved quote.** When a draft is edited, unchanged items keep their saved rates; "Re-price at current rates" refreshes everything. Products, services, rates, rules and the company profile are audited (Admin → Audit log), and nothing in the rate card is ever hard-deleted.

## Tests

```bash
php vendor/bin/phpunit   # 100 tests: formula + pricing unit tests, feature tests (SQLite in memory)
npm run test:js          # 54 tests: browser engines against the same fixtures
```

The feature tests cover:

- OTP sign-in, including expiry and lockout
- the full journey from website request to fully paid invoice
- Razorpay order creation, signature verification, tampered signatures, webhooks (valid, invalid, duplicate, wrong amount) and gateway errors
- the product designer and formula validation
- product and rate snapshots
- the original acceptance checks (₹1,422 sofa, minimum item charge, ₹ in the PDF, CGST/SGST vs IGST)
- revisions, offline payments, expiry
- role access, e.g. technicians only see their own visits

## Operations

- **Scheduler**: `php artisan quotes:expire` marks unpaid sent quotes past validity as Expired. It's scheduled daily; on Windows, add a Task Scheduler entry running `php artisan schedule:run` every minute. Without it, the dashboard and lists still expire overdue quotes when they load.
- **Email**: `MAIL_MAILER=log` writes emails to `storage/logs/laravel.log`. Set SMTP to send for real. Customers get "quote ready" (with PDF), "visit scheduled", "payment received" and "invoice" emails. Admins get "new request", "quote accepted/declined" and "payment received".
- **SMS**: `SMS_DRIVER=log` writes OTPs and alerts to the log. To go live, add your provider (MSG91, Twilio, Gupshup…) in `app/Services/Sms/SmsSender.php`. That file is the only place that sends SMS.
- **Photos**: phone photos are scaled down to 1600 px and served through the app, so no `storage:link` is needed. Site photos need a signed-in staff member or customer.
- **Sample PDF**: `php artisan quotes:sample-pdf [QT/2026-27/0003]` writes `docs/sample-quotation.pdf`.

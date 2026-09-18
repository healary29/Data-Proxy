# DataProxy Platform

Combined data bundle + proxy reselling platform (PHP + MySQL + JS).

## Setup (XAMPP / htdocs)
1. Drop this whole `dataproxy` folder into `htdocs/`
2. Import `schema.sql` into MySQL (phpMyAdmin: Import tab, or `mysql -u root -p < schema.sql`)
3. Edit `includes/config.php` — set your DB credentials, Daraja (M-Pesa) keys, Africa's Talking keys
4. Visit `http://localhost/dataproxy/public/` in your browser

## Structure
- `includes/` — core PHP logic (db, auth, mpesa, sms) — never accessed directly by browser
- `api/` — endpoints called via fetch()/JS or M-Pesa webhooks
- `public/` — customer-facing pages (login, register, dashboard, buy flows, tickets)
- `admin/` — admin panel (bundles, plans, orders, devices, payouts, complaints)
- `assets/` — css/js shared across public + admin
- `logs/` — mpesa_callbacks.log written here, keep writable (chmod 755)
- `uploads/tickets/` — ticket attachment uploads, keep writable

## Status — MVP complete
- Backend: auth, wallet, M-Pesa STK push + webhook, SMS, buy bundle
  (guest or wallet), buy proxy (wallet only), tickets/complaints
- Customer site (`public/`): landing, register, login, dashboard,
  buy data, buy proxy, guest order tracking, ticket raise/reply
- Admin panel (`admin/`): dashboard stats, bundle & plan management,
  order status updates, device monitor (ban/unban), payout approval,
  complaints inbox with order context + reply + refund-to-wallet

## Admin login
Default seeded username is `admin` — you MUST generate a real bcrypt
hash and update it before using the panel. In PHP:
```php
echo password_hash('YourNewPassword', PASSWORD_BCRYPT);
```
Then run in MySQL:
```sql
UPDATE admin_users SET password_hash = 'PASTE_HASH_HERE' WHERE username = 'admin';
```

## Known gaps / next steps (not yet built)
- Bundle delivery: code is in place (`includes/bundle_delivery.php`,
  `BUNDLE_DELIVERY_MODE` in config.php). Default 'manual' mode flags
  admin via SMS + `bundle_delivery_queue` table — no external API
  account needed to use it. Switching to 'api' mode requires you to
  get a real aggregator/dealer API account and fill in the request
  shape in `deliverBundleViaApi()`.
- B2C payouts: code is in place (`includes/mpesa_b2c.php`,
  `api/mpesa_b2c_callback.php`). `MPESA_B2C_ENABLED` is `false` by
  default, so "Send Payout" in admin fails gracefully with a clear
  message and you fall back to "Mark Paid Manually". Flip it on once
  Safaricom approves your B2C Daraja application and you've filled in
  the B2C credentials in config.php.
- Device heartbeat: `api/device_heartbeat.php` + `public/my_devices.php`
  let a participant register this browser session and send a heartbeat
  every 60s while the page stays open; `includes/device_cleanup.php`
  auto-marks a device offline after 3 minutes of silence (no cron
  needed — runs opportunistically). **Important limitation**: this only
  tracks presence/status. It does NOT actually route proxy traffic
  through the participant's connection — that requires a real proxy
  server component (a SOCKS/HTTP proxy client) running on the device,
  which is a separate build (likely a small native app or script, not
  just a browser tab).
- Ticket attachments upload UI (table exists, no upload form yet)
- Rate limiting / CAPTCHA on login, register, and guest lookup forms

## Before going live
- Set `error_reporting`/`display_errors` OFF in config.php
- Replace the placeholder admin password hash (see above)
- Move config.php credentials out of version control
- Switch MPESA_ENV to 'production' and use real Daraja production keys
- Confirm all internal links assume the app lives at `htdocs/dataproxy/` —
  update paths in `_header.php`/`_footer.php` files if you rename the folder

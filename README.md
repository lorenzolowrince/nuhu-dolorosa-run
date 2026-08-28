# Charity Run Nuhu Dolorosa De Kamijan
## Online Registration Portal — "Run For Hope"

A full-stack PHP registration portal for the Charity Run Nuhu Dolorosa De Kamijan event on **28 June 2025**.

---

## Features

- **Landing Page** — Hero with countdown timer, event categories, and registration CTA
- **Online Registration** — Validated multi-section form with real-time fee calculator
- **Payment Integration** — FPX (via Billplz) + Stripe (card payments)
- **Confirmation Email** — Automatic email sent upon successful payment
- **Admin Dashboard** — Full CRUD, search, filter, export CSV
- **Security** — CSRF, PDO prepared statements, bcrypt, rate-limited login, XSS prevention, CSP headers

---

## Requirements

| Requirement     | Version      |
|-----------------|--------------|
| PHP             | 8.0+         |
| MySQL / MariaDB | 8.0+ / 10.4+ |
| Composer        | 2.x          |
| Web Server      | Apache / Nginx |
| PHP Extensions  | pdo_mysql, curl, openssl, mbstring |

---

## Installation

### 1. Clone / copy the project

```
c:\your-webserver-root\nuhu-dolorosa-run\
```

### 2. Install Composer dependencies

```bash
cd "c:\Visual Code\Nuhu Dolorosa Kamijan"
composer install
```

### 3. Create database

```bash
mysql -u root -p < database/schema.sql
```

Or import `database/schema.sql` via phpMyAdmin.

### 4. Configure environment

```bash
cp .env.example .env
```

Edit `.env` and fill in:
- Database credentials
- Stripe API keys (from https://dashboard.stripe.com/apikeys)
- Billplz API key + Collection ID (from https://www.billplz.com)
- SMTP email credentials

### 5. Set web server document root

Point your vhost to the `public/` folder:

**Apache** (`httpd.conf` or vhost):
```apache
DocumentRoot "C:/Visual Code/Nuhu Dolorosa Kamijan/public"
<Directory "C:/Visual Code/Nuhu Dolorosa Kamijan/public">
    AllowOverride All
    Require all granted
</Directory>
```

**Nginx:**
```nginx
root /path/to/nuhu-dolorosa-run/public;
index index.php;
location ~ \.php$ { fastcgi_pass 127.0.0.1:9000; ... }
```

### 6. Set APP_URL

In `.env`, set `APP_URL` to the URL that reaches your `public/` folder:
```
APP_URL=https://yourdomain.my/public
```

### 7. Generate admin password hash (optional)

The default admin credentials are:
- **Username:** `admin`
- **Password:** `Admin@123456`

**Change this immediately after first login!** Go to Admin → Settings → Change Password.

To generate a new hash manually:
```php
<?php echo password_hash('YourNewPassword', PASSWORD_BCRYPT, ['cost' => 12]);
```

---

## Project Structure

```
nuhu-dolorosa-run/
├── .env.example          ← Environment template
├── .env                  ← Your config (never commit!)
├── composer.json
├── database/
│   └── schema.sql        ← Full DB schema + seed data
├── app/
│   ├── config.php        ← Loads .env, defines constants
│   ├── database.php      ← PDO singleton with helpers
│   ├── functions.php     ← Global helpers
│   ├── auth.php          ← Admin authentication + rate limiting
│   ├── csrf.php          ← CSRF token generation and verification
│   ├── validation.php    ← Input validation class
│   ├── email.php         ← PHPMailer email service
│   └── payment/
│       ├── stripe.php    ← Stripe Checkout Sessions
│       └── fpx.php       ← Billplz FPX integration
├── public/               ← Web root (point vhost here)
│   ├── index.php         ← Landing page
│   ├── register.php      ← Registration form
│   ├── payment.php       ← Payment confirmation + initiation
│   ├── success.php       ← Payment success + verification
│   ├── failed.php        ← Payment failure handler
│   ├── webhook_stripe.php ← Stripe webhook endpoint
│   ├── webhook_fpx.php   ← Billplz FPX callback endpoint
│   ├── includes/
│   │   ├── header.php
│   │   └── footer.php
│   └── assets/
│       ├── css/style.css
│       └── js/main.js
└── admin/
    ├── login.php
    ├── logout.php
    ├── dashboard.php
    ├── registrations.php
    ├── payments.php
    ├── settings.php
    ├── export.php
    └── includes/
        ├── header.php
        └── sidebar.php
```

---

## Payment Gateway Setup

### Stripe

1. Sign up at https://stripe.com
2. Get API keys from Dashboard → Developers → API keys
3. Add to `.env`:
   ```
   STRIPE_PUBLISHABLE_KEY=pk_live_xxx
   STRIPE_SECRET_KEY=sk_live_xxx
   STRIPE_WEBHOOK_SECRET=whsec_xxx
   ```
4. In Stripe Dashboard → Webhooks, add endpoint:
   `https://yourdomain.my/webhook_stripe.php`
   Listen for: `checkout.session.completed`, `checkout.session.expired`

### Billplz FPX

1. Sign up at https://www.billplz.com
2. Create a Collection and note the Collection ID
3. Get API key and X-Signature key from Account Settings
4. Add to `.env`:
   ```
   BILLPLZ_API_KEY=your_key
   BILLPLZ_COLLECTION_ID=your_collection_id
   BILLPLZ_X_SIGNATURE_KEY=your_x_signature_key
   BILLPLZ_SANDBOX=false
   ```
5. Billplz will callback to: `https://yourdomain.my/webhook_fpx.php`

---

## Admin Panel

Access at: `https://yourdomain.my/admin/login.php`

| Page | URL |
|------|-----|
| Login | `/admin/login.php` |
| Dashboard | `/admin/dashboard.php` |
| Registrations | `/admin/registrations.php` |
| Payments | `/admin/payments.php` |
| Settings | `/admin/settings.php` |
| Export CSV | `/admin/export.php?type=csv` |
| Shirt Summary | `/admin/export.php?type=shirts` |

---

## Security

| Feature | Implementation |
|---------|----------------|
| SQL Injection | PDO prepared statements throughout |
| XSS | `htmlspecialchars()` on all output |
| CSRF | Token in all POST forms, verified server-side |
| Password | `password_hash()` with bcrypt cost=12 |
| Rate Limiting | 5 attempts / 15 min per IP for admin login |
| Session | httpOnly, SameSite=Strict, strict mode |
| Headers | CSP, X-Frame-Options, X-Content-Type-Options |
| Payment Data | Never stored — handled by Stripe/Billplz |

---

## Email Configuration

Uses PHPMailer with SMTP. For Gmail:
1. Enable 2FA on your Google account
2. Generate an App Password: Google Account → Security → App Passwords
3. Use the app password in `.env` as `MAIL_PASSWORD`

---

## Troubleshooting

**Blank page / 500 error**
- Set `APP_DEBUG=true` in `.env` temporarily
- Check PHP error logs

**Payment not redirecting**
- Verify API keys in `.env`
- Check cURL is enabled: `php -m | grep curl`
- For FPX: ensure BILLPLZ_SANDBOX matches your API key type

**Emails not sending**
- Verify SMTP credentials
- Check spam folder
- Test with a tool like Mailtrap in development

**Session issues**
- Ensure PHP session directory is writable
- Check session timeout settings in `config.php`

---

## License

Built for Charity Run Nuhu Dolorosa De Kamijan. All rights reserved.

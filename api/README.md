# FoodMitra PHP API

## Setup (XAMPP)

1. Copy `api` under `htdocs` (or serve this folder).
2. Import SQL in order:
   - [`sql/001_schema.sql`](sql/001_schema.sql) — orders + webhooks
   - [`sql/002_security.sql`](sql/002_security.sql) — api_keys, otp_codes, rate_limits
   - [`sql/003_catalog.sql`](sql/003_catalog.sql) — users, hotels, menu
   - [`sql/004_seed_catalog.sql`](sql/004_seed_catalog.sql) — sample hotels
   - [`sql/005_seed_menu_items.sql`](sql/005_seed_menu_items.sql) — sample menu items
   - [`sql/006_hotel_coordinates.sql`](sql/006_hotel_coordinates.sql) — latitude/longitude (also migrates old lat/lng)
3. Copy [`.env.example`](.env.example) → `.env` and fill DB / Razorpay / Fast2SMS.
4. Generate an API key (from the `api` folder):

```bash
php bin/generate-api-key.php --name=mobile --insert
```

5. Put the **plain** key in the Expo app `.env.local`:

```env
EXPO_PUBLIC_API_BASE_URL=http://YOUR_LAN_IP/FoodMitra/api
EXPO_PUBLIC_API_KEY=fm_....
```

6. Put the **SHA-256 hash** in `api/.env`:

```env
API_KEY_HASH=...
```

   (`--insert` also stores the same hash in `api_keys`.)

7. Razorpay Dashboard → Webhooks → URL:
   `http://YOUR_HOST/FoodMitra/api/webhooks/razorpay.php`  
   Events: `payment.captured`, `payment.failed`, `order.paid`

## Security

| Layer | Behavior |
|-------|----------|
| API key | Every app route requires `X-API-Key`. Plain key is hashed (SHA-256 + optional `API_KEY_PEPPER`) and matched against `API_KEY_HASH` **and/or** `api_keys` table. |
| Rate limit | Per IP + per key, `RATE_LIMIT_PER_MINUTE` (default 60). OTP send has a stricter hourly limit. |
| Webhooks | Skip API key; verified with Razorpay webhook signature only. |

## Prepaid order flow (crash-safe)

1. App → `POST /orders/create.php` (`payment_mode: prepaid`) → DB row **`awaiting_payment`** + Razorpay order.
2. Razorpay Checkout in app.
3. App → `POST /orders/verify-payment.php` → **`placed`**.
4. If the app dies after pay, webhook still flips **`awaiting_payment` → `placed`**.

## Endpoints

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| POST | `/orders/create.php` | API key | COD or awaiting_payment + Razorpay |
| POST | `/orders/verify-payment.php` | API key | Verify Checkout signature |
| GET | `/orders/get.php?id=` | API key | Poll order status |
| POST | `/otp/send.php` | API key | Send OTP via Fast2SMS |
| POST | `/otp/verify.php` | API key | Verify OTP + upsert user |
| GET | `/users/get.php?id=` / `?phone=` | API key | Fetch user |
| POST | `/users/upsert.php` | API key | Create / refresh user |
| POST | `/users/update.php` | API key | Update profile |
| GET | `/hotels/list.php` | API key | List hotels (`q`, `pure_veg`, `offer_active`) |
| GET | `/hotels/get.php?id=` | API key | Hotel detail (`menu=1` optional) |
| GET | `/menu/list.php?hotel_id=` | API key | Full menu (categories + items + offers) |
| GET | `/menu/items.php?hotel_id=` | API key | Menu items (`category`, `veg_only`, `q`) |
| GET | `/menu/item.php?id=` | API key | Single menu item |
| POST | `/users/location.php` | API key | Upsert user GPS for admin / nearby |
| GET | `/hotels/nearby.php?lat=&lng=` | API key | Hotels within delivery radius |
| POST | `/delivery/login.php` | API key | Partner login → Bearer token |
| POST | `/delivery/heartbeat.php` | API key + Bearer | Partner GPS + online |
| GET | `/delivery/offers.php` | API key + Bearer | Exclusive 60s offer |
| POST | `/delivery/accept.php` / `reject.php` | API key + Bearer | Accept / skip offer |
| GET | `/delivery/active-order.php` | API key + Bearer | Active trip + OSRM |
| POST | `/delivery/verify-otp.php` | API key + Bearer | pickup / delivery OTP |
| GET | `/delivery/wallet.php` | API key + Bearer | Earnings + COD hold |
| GET | `/tracking/order.php?id=` | API key | Customer tracking + delivery OTP |
| GET | `/cms/get.php?slug=` | API key | Terms / privacy |
| POST | `/webhooks/razorpay.php` | Razorpay sig | Payment safety net |

### OTP bodies

```json
POST /otp/send.php
{ "phone": "9876543210", "purpose": "login" }

POST /otp/verify.php
{ "phone": "9876543210", "otp": "123456", "purpose": "login" }
```

Set `OTP_DEBUG=1` in `api/.env` only on local to echo OTP in the send response.

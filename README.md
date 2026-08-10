# FoodMitra backend — deploy THIS folder to `/var/www/foodmitra`

## Layout

```text
foodmitra/
  api/                      → https://foodmitra.me/api/
  hotel-login.php           → https://foodmitra.me/hotel-login.php
  dashboard.php …           → hotel admin (root)
  admin6354932452/          → super admin (URL only, not linked)
```

## URLs

| Who | URL |
|-----|-----|
| Hotel | `https://foodmitra.me/hotel-login.php` |
| Super admin | `https://foodmitra.me/admin6354932452/login.php` |
| API | `https://foodmitra.me/api/` |

Root `/` redirects to hotel login. Super admin is **not** shown on any public page.

## Nginx

```bash
sudo cp /var/www/foodmitra/deploy/nginx-foodmitra.me.conf /etc/nginx/sites-available/foodmitra
sudo ln -sf /etc/nginx/sites-available/foodmitra /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

## App

```env
EXPO_PUBLIC_API_BASE_URL=https://foodmitra.me/api
```

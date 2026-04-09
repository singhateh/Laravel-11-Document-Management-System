This is a PHP/server upload limit issue, not a Laravel validation error. The file is being rejected before it even reaches your Laravel validation rules.

---

## Where the limit is enforced

There are **three separate limits** that all need to match. If any one is lower than the file size, it blocks the upload:

```
Browser → PHP (upload_max_filesize) → PHP (post_max_size) → Laravel validation (max:)
```

All three must be raised together or the fix won't work.

---

## Fix 1 — `php.ini`

This is the root cause. Find your active `php.ini`:

```bash
php --ini | grep "Loaded Configuration"
```

Then edit it:

```ini
; Maximum size of a single uploaded file
upload_max_filesize = 100M

; Maximum size of entire POST body (must be >= upload_max_filesize)
; If uploading multiple files, this must cover the total combined size
post_max_size = 120M

; Maximum time PHP will spend parsing input (increase for large uploads)
max_input_time = 300

; Maximum execution time for the script
max_execution_time = 300

; Maximum memory PHP can use per request
memory_limit = 256M
```

> `post_max_size` must always be **larger** than `upload_max_filesize`. If you upload multiple files at once, `post_max_size` must cover their combined size.

After editing, restart your server:

```bash
# PHP-FPM
sudo systemctl restart php8.x-fpm

# Apache
sudo systemctl restart apache2

# Nginx (doesn't serve PHP directly — restart PHP-FPM instead)
sudo systemctl restart php8.x-fpm

# Laravel Valet / Herd
valet restart
# or
herd restart
```

---

## Fix 2 — Nginx `client_max_body_size` (if using Nginx)

PHP-FPM + Nginx is common for Laravel. Nginx has its own body size limit that sits **in front of** PHP and will return a `413 Request Entity Too Large` before PHP even sees the request.

```nginx
# In your server block or http block
# /etc/nginx/sites-available/your-site or /etc/nginx/nginx.conf

server {
    client_max_body_size 120M;  # Match or exceed post_max_size
    # ...rest of your config
}
```

```bash
sudo nginx -t          # test config before reloading
sudo systemctl reload nginx
```

---

## Fix 3 — Apache (if using Apache)

```apache
# In .htaccess or your VirtualHost config
LimitRequestBody 125829120   # 120MB in bytes

# Or via php_value in .htaccess
php_value upload_max_filesize 100M
php_value post_max_size 120M
php_value max_input_time 300
php_value max_execution_time 300
```

---

## Fix 4 — Laravel validation rule

Once the server accepts the file, make sure your Laravel validation rule isn't also capping it. The `max:` rule uses **kilobytes**:

```php
// In StoreCarrierRequest (or wherever your upload validation is)
public function rules(): array
{
    return [
        'carrier' => [
            'required',
            'file',
            'mimes:png,bmp,jpeg,jpg',
            'max:102400',   // 100MB in KB (100 * 1024)
        ],
    ];
}
```

---

## Fix 5 — For local dev (`.env` / Valet / Herd)

If you're running `php artisan serve`, the limits come from whichever `php.ini` that PHP binary loads. Confirm which one is active:

```bash
php -r "echo php_ini_loaded_file();"
```

If you're using **Laravel Herd**, edit the ini via:
```
Herd → PHP → Active PHP version → Open php.ini
```

If you're using **Laravel Valet**:
```bash
# Find the ini
php --ini

# Or use valet's php directly
~/.config/valet/valet.ini
```

---

## Quick verification after fixing

Run this to confirm all three PHP values are applied:

```bash
php -r "echo 'upload_max_filesize: ' . ini_get('upload_max_filesize') . PHP_EOL;
echo 'post_max_size: ' . ini_get('post_max_size') . PHP_EOL;
echo 'memory_limit: ' . ini_get('memory_limit') . PHP_EOL;"
```

Or add a temporary route to check from the browser:

```php
// routes/web.php — remove after confirming
Route::get('/php-limits', function () {
    return [
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size'       => ini_get('post_max_size'),
        'memory_limit'        => ini_get('memory_limit'),
        'max_execution_time'  => ini_get('max_execution_time'),
    ];
});
```

---

## Summary checklist

- [ ] `upload_max_filesize` raised in `php.ini`
- [ ] `post_max_size` raised **above** `upload_max_filesize` in `php.ini`
- [ ] `memory_limit` and `max_execution_time` raised in `php.ini`
- [ ] PHP-FPM / Apache / Valet / Herd restarted
- [ ] `client_max_body_size` raised in Nginx config (if using Nginx)
- [ ] `LimitRequestBody` raised in Apache config (if using Apache)
- [ ] Laravel `max:` rule in KB matches the new limit
- [ ] Verified with `php -r` or the temp route
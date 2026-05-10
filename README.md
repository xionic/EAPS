# EAPS
Easy Access Pair Storage

## Admin

`admin.php` is protected by a PHP session login. Credentials are set in `config.php`:

```php
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', '$2y$12$...');
```

To set a password, open `setup.php` in a browser (LAN only). Enter the new password and copy the generated hash into `ADMIN_PASSWORD_HASH` in `config.php`.

Alternatively, generate a hash from the command line:

```
php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT, ['cost'=>12]);"
```

`ADMIN_LAN_ONLY` (default `true`) restricts access to RFC1918 addresses. `ADMIN_REQUIRE_AUTH` can be set to `false` to disable the login gate entirely.

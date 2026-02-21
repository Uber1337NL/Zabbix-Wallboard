# Zabbix Wallboard

A secure, modern dashboard for displaying Zabbix monitoring alerts in a wallboard/TV display format.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
  - [Environment Variables](#environment-variables)
  - [Apache Setup](#apache-setup)
  - [Nginx Setup](#nginx-setup)
  - [Using .env File](#using-env-file)
- [Security Features](#security-features)
- [Usage](#usage)
- [Changelog](#changelog)
- [Troubleshooting](#troubleshooting)
- [License](#license)

## Features

- **Real-time Monitoring**: Display active Zabbix triggers in a clean, tile-based interface
- **Severity-based Filtering**: Filter alerts by severity level (0-5)
- **Host Group Filtering**: View alerts for specific host groups or all groups
- **Acknowledgement Support**: Acknowledge alerts directly from the wallboard
- **Maintenance Awareness**: Visual indicators for hosts in maintenance mode
- **Auto-refresh**: Configurable automatic page refresh
- **Responsive Design**: Adapts to different screen sizes
- **Lunch Reminder**: Optional fun feature for office displays
- **Secure Authentication**: User login with encrypted credential storage
- **CSRF Protection**: Protection against cross-site request forgery attacks
- **XSS Prevention**: All output is properly escaped
- **Reverse Proxy Support**: Works behind reverse proxies with custom paths

## Requirements

- **PHP**: 7.4 or higher (8.0+ recommended)
- **PHP Extensions**:
  - curl
  - json
  - openssl
  - session
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Zabbix**: 4.0 or higher (API access required)
- **Composer** (optional, for .env file support)

## Installation

### 1. Clone or Download

```bash
cd /var/www
git clone https://github.com/yourusername/zabbix-wallboard.git
cd zabbix-wallboard
```

### 2. Set Permissions

```bash
sudo chown -R www-data:www-data /var/www/zabbix-wallboard
sudo chmod -R 755 /var/www/zabbix-wallboard
```

### 3. Configure Web Server

See [Configuration](#configuration) section below.

## Configuration

### Environment Variables

The application supports three methods for setting environment variables:

#### Required Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `ZABBIX_URL` | Full URL to Zabbix API endpoint | `https://zabbix.example.com/api_jsonrpc.php` |
| `ZABBIX_USERNAME` | Zabbix API user | `zbxwallboard` |
| `ZABBIX_PASSWORD` | Zabbix API password | `SuperSecretPass123` |
| `ZABBIX_BASIC_AUTH` | Enable HTTP Basic Auth (0 or 1) | `0` |

### Apache Setup

#### Method 1: Virtual Host (Recommended)

Create/edit Apache virtual host configuration:

```bash
sudo nano /etc/apache2/sites-available/zabbix-wallboard.conf
```

Add the following:

```apache
<VirtualHost *:80>
    ServerName wallboard.example.com
    ServerAdmin admin@example.com
    DocumentRoot /var/www/zabbix-wallboard

    # Environment Variables
    SetEnv ZABBIX_URL "https://zabbix.example.com/api_jsonrpc.php"
    SetEnv ZABBIX_USERNAME "zbxwallboard"
    SetEnv ZABBIX_PASSWORD "your_password_here"
    SetEnv ZABBIX_BASIC_AUTH "0"

    <Directory /var/www/zabbix-wallboard>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted

        # Security Headers
        Header always set X-Frame-Options "SAMEORIGIN"
        Header always set X-Content-Type-Options "nosniff"
        Header always set X-XSS-Protection "1; mode=block"
    </Directory>

    # Protect sensitive files
    <FilesMatch "^\." >
        Require all denied
    </FilesMatch>

    # Enable compression
    <IfModule mod_deflate.c>
        AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript
    </IfModule>

    ErrorLog ${APACHE_LOG_DIR}/wallboard_error.log
    CustomLog ${APACHE_LOG_DIR}/wallboard_access.log combined
</VirtualHost>
```

**Enable the site:**

```bash
sudo a2enmod env headers rewrite deflate
sudo a2ensite zabbix-wallboard
sudo systemctl restart apache2
```

#### Method 2: .htaccess

Create `.htaccess` in the application root:

```apache
# Environment Variables
SetEnv ZABBIX_URL "https://zabbix.example.com/api_jsonrpc.php"
SetEnv ZABBIX_USERNAME "zbxwallboard"
SetEnv ZABBIX_PASSWORD "your_password_here"
SetEnv ZABBIX_BASIC_AUTH "0"

# Security
<Files .env>
    Require all denied
</Files>

<FilesMatch "^\." >
    Require all denied
</FilesMatch>

# Rewrite rules
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [L,QSA]
</IfModule>
```

**Note**: Ensure `AllowOverride All` is set in your Apache configuration.

### Nginx Setup

Create/edit Nginx server block:

```bash
sudo nano /etc/nginx/sites-available/zabbix-wallboard
```

Add the following:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name wallboard.example.com;
    root /var/www/zabbix-wallboard;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Logging
    access_log /var/log/nginx/wallboard_access.log;
    error_log /var/log/nginx/wallboard_error.log;

    # Hide sensitive files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;

        # Environment Variables
        fastcgi_param ZABBIX_URL "https://zabbix.example.com/api_jsonrpc.php";
        fastcgi_param ZABBIX_USERNAME "zbxwallboard";
        fastcgi_param ZABBIX_PASSWORD "your_password_here";
        fastcgi_param ZABBIX_BASIC_AUTH "0";
    }

    # Static files caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Deny access to config files
    location ~ /config\.php$ {
        deny all;
    }
}
```

**Enable the site:**

```bash
sudo ln -s /etc/nginx/sites-available/zabbix-wallboard /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### Using .env File (Recommended for Security)

This method keeps sensitive credentials out of web server configuration files.

#### 1. Install PHP dotenv Library

```bash
cd /var/www/zabbix-wallboard
composer require vlucas/phpdotenv
```

#### 2. Create .env File

```bash
nano .env
```

Add your configuration:

```bash
# Zabbix API Configuration
ZABBIX_URL=https://zabbix.example.com/api_jsonrpc.php
ZABBIX_USERNAME=zbxwallboard
ZABBIX_PASSWORD=SuperSecretPassword123
ZABBIX_BASIC_AUTH=0
```

#### 3. Secure .env File

```bash
chmod 640 .env
chown www-data:www-data .env
```

#### 4. Update config.php

Add at the top of `config.php`:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

return [
    'ZABBIX' => [
        'URL' => $_ENV['ZABBIX_URL'] ?? 'https://zabbix.example.com/api_jsonrpc.php',
        'USERNAME' => $_ENV['ZABBIX_USERNAME'] ?? '',
        'PASSWORD' => $_ENV['ZABBIX_PASSWORD'] ?? '',
        'BASIC_AUTH' => (bool)($_ENV['ZABBIX_BASIC_AUTH'] ?? false),
        'ENABLED' => true,
        'VERIFY_SSL' => true,
        'TIMEOUT' => 30,
        'CONNECT_TIMEOUT' => 5
    ],
    // ... rest of configuration
];
```

#### 5. Add .env to .gitignore

```bash
echo ".env" >> .gitignore
echo "vendor/" >> .gitignore
```

### Additional Configuration Options

Edit `config.php` to customize:

```php
'DISPLAY' => [
    'TITLE' => 'Zabbix Wallboard',           // Page title
    'PROBLEM_COUNT_SHOW' => 0,                // Max problems to show (0 = unlimited)
    'LUNCH_REMINDER' => true,                  // Enable lunch reminder
    'LUNCH_REMINDER_START' => 1200,            // 12:00 PM
    'LUNCH_REMINDER_END' => 1230               // 12:30 PM
],

'SESSION' => [
    'LIFETIME' => 3600,                        // Session lifetime in seconds
    'COOKIE_HTTPONLY' => true,                 // HttpOnly flag
    'COOKIE_SECURE' => true,                   // Secure flag (HTTPS only)
    'COOKIE_SAMESITE' => 'Strict'              // SameSite policy
],

'ZABBIX' => [
    'VERIFY_SSL' => true,                      // Verify SSL certificates
    'TIMEOUT' => 30,                           // API timeout in seconds
    'CONNECT_TIMEOUT' => 5                     // Connection timeout in seconds
]
```

## Security Features

### Version 2.0 Security Improvements

1. **CSRF Protection**: All state-changing operations require valid CSRF tokens
2. **XSS Prevention**: All output is properly escaped using `htmlspecialchars()`
3. **Secure Password Storage**:
   - Upgraded from AES-256-CBC to AES-256-GCM (authenticated encryption)
   - Encryption keys stored in session, not cookies
4. **Input Validation**: All user inputs are validated and sanitized
5. **SSL Certificate Verification**: Enabled by default (configurable)
6. **Secure Session Configuration**:
   - HttpOnly cookies
   - Secure flag for HTTPS
   - SameSite=Strict
   - Strict mode enabled
7. **Environment Variables**: Sensitive credentials moved to environment variables
8. **Error Handling**: Errors logged, not displayed to users
9. **Type Safety**: Strict typing enabled throughout
10. **SQL Injection Prevention**: Parameterized queries (via Zabbix API)

### Security Best Practices

1. **Use HTTPS**: Always run behind HTTPS in production
2. **Keep PHP Updated**: Use PHP 8.0 or higher
3. **Restrict File Permissions**:
   ```bash
   chmod 640 config.php .env
   chmod 755 classes/
   chmod 644 classes/*.php
   ```
4. **Enable SSL Verification**: Set `VERIFY_SSL` to `true` in production
5. **Use Strong Passwords**: For Zabbix API user
6. **Limit API User Permissions**: Create dedicated read-only user for wallboard
7. **Regular Updates**: Keep all dependencies updated

## Usage

### Accessing the Wallboard

Navigate to: `http://wallboard.example.com`

### Filtering Options

#### By Host Group
- Click the host group dropdown in the menu
- Select a specific group or "All"
- Selection persists across page refreshes

#### By Severity
- Click the severity dropdown
- Choose minimum severity level (0-5)
- Only alerts at or above this level will display

#### Hide Acknowledged
- Click "Hide Acked" to filter out acknowledged alerts
- Click "Show Acked" to display all alerts

#### Hide Maintenance
- Click "Hide Maint" to filter out hosts in maintenance
- Click "Show Maint" to display all hosts

### User Authentication

#### Login
1. Click "Login" in the top menu
2. Enter Zabbix credentials
3. Logged-in users can acknowledge events

#### Acknowledge Alerts
1. Click on any alert tile
2. Event details dialog appears
3. Enter acknowledgement message
4. Click "Acknowledge"

#### Logout
- Click "Logout (username)" in the menu

### Auto-Refresh

Add JavaScript to enable auto-refresh (in `wallboard.js`):

```javascript
// Auto-refresh every 60 seconds
setTimeout(function() {
    location.reload();
}, 60000);
```

## Changelog

### Version 2.0.0 (2026-02-21) - Major Security & Modernization Release

#### Security Enhancements
- **[CRITICAL]** Added CSRF protection with secure token generation
- **[CRITICAL]** Implemented comprehensive XSS prevention with output escaping
- **[CRITICAL]** Upgraded encryption from AES-256-CBC to AES-256-GCM with authentication
- **[HIGH]** Moved encryption keys from cookies to server-side session storage
- **[HIGH]** Added input validation and sanitization for all user inputs
- **[HIGH]** Enabled SSL certificate verification by default
- **[MEDIUM]** Implemented secure session configuration (HttpOnly, Secure, SameSite)
- **[MEDIUM]** Added proper error logging instead of displaying errors
- **[MEDIUM]** Removed error suppression (@) operator
- **[LOW]** Added security headers (X-Frame-Options, X-XSS-Protection, etc.)

#### Code Quality Improvements
- **Renamed variables**: Changed from UPPERCASE to camelCase/PascalCase (PSR standards)
- **Type hints**: Added strict type declarations throughout
- **Constants**: Replaced magic numbers with named constants
- **Error codes**: Documented error code meanings
- **Separation of concerns**: Better organization of display logic
- **Method visibility**: Properly declared all methods as public/private
- **Code formatting**: Consistent indentation and spacing
- **Documentation**: Improved PHPDoc comments

#### Architecture Changes
- **Environment variables**: Support for credentials via env vars
- **Dependency injection**: Better constructor patterns
- **Exception handling**: Improved error handling with proper types
- **Session management**: Enhanced session security and lifecycle
- **API communication**: Better error handling for API failures
- **Configuration**: Moved to array-based config with validation

#### Feature Enhancements
- **Configurable SSL verification**: Can be disabled for development
- **Configurable timeouts**: API timeout and connection timeout settings
- **Better Zabbix version detection**: Improved compatibility handling
- **Enhanced event details**: Better formatting and user information
- **Improved responsive design**: Better text handling for long strings

#### Backward Compatibility
- **Breaking**: Configuration structure changed (see config.php)
- **Breaking**: Class method names changed (camelCase)
- **Breaking**: Cookie name changed from `zbxwallboard_pw_crypt_key` to session-only storage
- **Migration required**: Update web server configuration for env variables

#### Bug Fixes
- Fixed potential memory leaks in session handling
- Fixed improper boolean comparisons (== vs ===)
- Fixed missing validation on array inputs
- Fixed insecure CURL options
- Fixed potential timing attacks with string comparison
- Fixed missing null checks in various methods

### Version 1.0.0 (Original)

#### Initial Features
- Basic Zabbix API integration
- Trigger display in tile format
- Host group filtering
- Severity filtering
- Acknowledgement support
- Maintenance mode indicators
- User authentication
- Lunch reminder feature

## Troubleshooting

### Common Issues

#### "No active API login" Error
**Cause**: Invalid credentials or API user doesn't exist
**Solution**:
1. Verify environment variables are set correctly
2. Check Zabbix user exists and has API access
3. Check Zabbix API endpoint URL is correct

#### "API Error: Permission denied"
**Cause**: Zabbix user lacks necessary permissions
**Solution**:
1. Grant "Zabbix User" or higher role to API user
2. Ensure user has access to required host groups

#### Session Expires Immediately
**Cause**: Incorrect session configuration
**Solution**:
1. Check `COOKIE_SECURE` is `false` if not using HTTPS
2. Verify session directory is writable: `ls -la /var/lib/php/sessions`
3. Check PHP session configuration: `php -i | grep session`

#### Environment Variables Not Loading
**Cause**: Web server configuration not applied
**Solution**:

**Apache**:
```bash
sudo a2enmod env
sudo systemctl restart apache2
```

**Nginx**:
```bash
sudo nginx -t
sudo systemctl restart nginx
sudo systemctl restart php8.2-fpm
```

#### SSL Certificate Verification Failed
**Cause**: Self-signed certificate or CA not trusted
**Solution**:
1. For development: Set `VERIFY_SSL => false` in config
2. For production: Add CA certificate to system trust store
3. Alternative: Update `CURLOPT_CAINFO` with CA bundle path

#### "Invalid CSRF token" Error
**Cause**: Session expired or cookies blocked
**Solution**:
1. Check browser allows cookies
2. Increase session lifetime in config
3. Clear browser cookies and try again

### Debug Mode

Enable debugging in `index.php`:

```php
ini_set('display_errors', '1');
error_reporting(E_ALL);
```

Check error logs:

**Apache**:
```bash
tail -f /var/log/apache2/wallboard_error.log
```

**Nginx**:
```bash
tail -f /var/log/nginx/wallboard_error.log
tail -f /var/log/php8.2-fpm.log
```

### Performance Tuning

#### Enable OPcache

Edit `php.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
```

#### Enable Browser Caching

See Nginx/Apache configuration examples above for static file caching.

#### Database Connection Pooling

Zabbix API handles this internally, no configuration needed.

## Support

For issues, questions, or contributions:
- **Issues**: GitHub Issues
- **Email**: support@example.com
- **Documentation**: https://docs.example.com

## License

MIT License - See LICENSE file for details

## Credits

- Original Author: [Your Name]
- Contributors: [List contributors]
- Built with: PHP, jQuery, Metro UI CSS, Zabbix API

---

**Last Updated**: 2026-02-21
**Version**: 2.0.0
# Zabbix Wallboard

A secure, modern dashboard for displaying Zabbix monitoring alerts in a wallboard/TV display format.

The code itself is located in the src/ directory. The files in the root directory are used for unit testing, documentation, etc. This release is about 137 kb of filws. Compared to the v1 version whoch was about 2 Mb in size. No more weird javascript frameworks, external fonts, etc. The only external library is jQuery-4.0.0-min which is 88 kb in size. In the next release that will be deprecated too, bringing the codebase down to only 50 kB. Less is more!

![Screenshot with multiple alerts](https://github.com/Uber1337NL/zabbix-wallboard/blob/main/img/screen-multiple-errors.png)

## Version 2.x Major Security Improvements and Modernization

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
11. No more MetroUI: Removed the MetroUI JavaScript and Font libraries
12. Updated jQuery from 3.7.0 to 4.0.0
13. No more user/pass auth. API Token (Bearer) only.
14. But... we kept user/pass for basic http authentication ONLY.

### Security Enhancements

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

### Code Quality Improvements

- **Changed variables**: From UPPERCASE to camelCase/PascalCase (PSR standards)
- **Type hints**: Added strict type declarations throughout
- **Constants**: Replaced magic numbers with named constants
- **Error codes**: Documented error code meanings
- **Separation of concerns**: Better organization of display logic
- **Method visibility**: Properly declared all methods as public/private
- **Code formatting**: Consistent indentation and spacing
- **Documentation**: Improved PHPDoc comments
- Changed the minimum PHP requirement to PHP8.2. All older versions are EOL.

### Architecture Changes

- **Environment variables**: Support for credentials via env vars
- **Dependency injection**: Better constructor patterns
- **Exception handling**: Improved error handling with proper types
- **Session management**: Enhanced session security and lifecycle
- **API communication**: Better error handling for API failures
- **Configuration**: Moved to array-based config with validation
- Changed from username/password to Bearer API token only

### Feature Enhancements

- **Configurable SSL verification**: Can be disabled for development
- **Configurable timeouts**: API timeout and connection timeout settings
- **Better Zabbix version detection**: Improved compatibility handling
- **Enhanced event details**: Better formatting and user information
- **Improved responsive design**: Better text handling for long strings

### Backward Compatibility

- **Breaking**: Configuration structure changed (see config.php)
- **Breaking**: Class method names changed (camelCase)
- **Breaking**: Cookie name changed from `zbxwallboard_pw_crypt_key` to session-only storage
- **Migration required**: Update web server configuration for env variables

### Bug Fixes

- Fixed potential memory leaks in session handling
- Fixed improper boolean comparisons (== vs ===)
- Fixed missing validation on array inputs
- Fixed insecure CURL options
- Fixed potential timing attacks with string comparison
- Fixed missing null checks in various methods

## Support

For issues, questions, or contributions:

- **Issues**:[GitHub Issues](https://github.com/Uber1337NL/zabbix-wallboard/issues)
- **Documentation**: [GitHub Wiki](https://github.com/Uber1337NL/zabbix-wallboard/wiki)

## License

MIT License

## Credits

- Author: Uber1337NL
- Contributors: Original from @bfdeandrade 8 years ago. Now completely rewritten >92% codechange)
- Built with: PHP 8.2, jQuery 4.0, Zabbix 6.4+ API

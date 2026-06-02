# Mymetrades Login & Signup System Setup Guide

## Overview
This is a complete authentication system for Mymetrades with:
- Email-based password verification
- User registration and login
- SQL database integration
- Email notifications

## Prerequisites
1. **PHP** (7.4 or higher)
2. **MySQL/MariaDB** database
3. **Gmail account** with App Password enabled (for email sending)
4. **Web server** (Apache/Nginx with PHP support)

---

## Installation Steps

### Step 1: Database Setup

1. Open **phpMyAdmin** or MySQL command line
2. Copy all SQL from `backend/setup.sql`
3. Execute it to create the database and tables

OR run via command line:
```bash
mysql -u root -p < backend/setup.sql
```

### Step 2: Configure Settings

Edit `backend/config.php` and update:

```php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');  // Your MySQL password
define('DB_NAME', 'mymetrades_db');

// Gmail Configuration
define('GMAIL_EMAIL', 'your-email@gmail.com');
define('GMAIL_PASSWORD', 'your-app-password');  // NOT your regular Gmail password!
```

### Step 3: Generate Gmail App Password

Follow these steps to get your Gmail App Password:

1. Go to [Google Account](https://myaccount.google.com/)
2. Click **Security** (left sidebar)
3. Enable **2-Step Verification** (if not already enabled)
4. Under "App passwords", select:
   - App: **Mail**
   - Device: **Windows PC** (or your device)
5. Google will generate a 16-character password
6. Copy this password to `GMAIL_PASSWORD` in `backend/config.php`

⚠️ **IMPORTANT**: Use the **App Password**, NOT your regular Gmail password!

### Step 4: Test the Setup

1. Place all files on your web server
2. Visit `http://localhost/Mymetrades-main/login.html`
3. Try signing up with an email address
4. Check if email is received

---

## File Structure

```
Mymetrades-main/
├── login.html                 # Login & Signup page (frontend)
├── index.html                 # Main website
├── backend/
│   ├── config.php            # Configuration file
│   ├── db.php                # Database connection
│   ├── email.php             # Email handling
│   ├── signup.php            # Sign up logic
│   ├── login.php             # Login logic
│   ├── setup.sql             # Database schema
│   └── README.md             # This file
```

---

## How It Works

### Sign Up Flow:
1. User enters email → clicks "Send Verification Email"
2. System generates a password → sends it via email
3. User enters the password they received
4. Account is created with that password
5. Success message shown
6. User can now login

### Login Flow:
1. User enters email → clicks "Send Login Email"
2. System verifies email exists
3. User enters password from email
4. System verifies password
5. New password generated and emailed for next time
6. User is logged in and redirected

---

## Connecting the "Join Now" Button

Edit `index.html` and find the "Join Now" buttons:

Replace:
```html
<a href="">Join Now</a>
```

With:
```html
<a href="login.html">Join Now</a>
```

---

## Troubleshooting

### Email Not Sending?
1. Check Gmail App Password is correct
2. Verify 2-Step Verification is enabled in Google Account
3. Check PHP mail configuration
4. Review error logs in `backend/email.php`

### Database Connection Error?
1. Verify MySQL is running
2. Check database credentials in `config.php`
3. Run `setup.sql` again

### 404 or File Not Found?
1. Ensure all files are in correct folder
2. Check file paths in HTML/PHP
3. Verify web server document root

### Styling Issues?
1. Check CSS is loading (browser DevTools)
2. Verify image path for logo
3. Check CORS settings if using API

---

## Security Recommendations

1. **Change default credentials** in `config.php`
2. **Use HTTPS** in production (not HTTP)
3. **Add rate limiting** to prevent brute force attacks
4. **Validate all inputs** (already done, but review)
5. **Use environment variables** for sensitive data
6. **Keep PHP/MySQL updated**
7. **Regular backups** of database
8. **Monitor login attempts** in `login_history` table

---

## Production Checklist

- [ ] Update all credentials in `config.php`
- [ ] Configure MySQL backups
- [ ] Set up SSL certificate (HTTPS)
- [ ] Configure firewall rules
- [ ] Enable error logging
- [ ] Test all features thoroughly
- [ ] Set up email monitoring
- [ ] Create admin panel for user management
- [ ] Implement CSRF protection
- [ ] Add 2FA (Two-Factor Authentication) optional

---

## API Endpoints

### POST `/backend/signup.php`
```json
{
    "email": "user@example.com",
    "password": "password123"
}
```

Response:
```json
{
    "success": true/false,
    "message": "Success or error message"
}
```

### POST `/backend/login.php`
```json
{
    "email": "user@example.com",
    "password": "passwordFromEmail"
}
```

Response:
```json
{
    "success": true/false,
    "message": "Success or error message",
    "user_id": 1
}
```

---

## Support

For issues:
1. Check error logs
2. Verify all configuration settings
3. Test with simple credentials first
4. Review Google Account security settings

---

## License

This authentication system is part of Mymetrades.
© 2026 Mymetrades. All rights reserved.

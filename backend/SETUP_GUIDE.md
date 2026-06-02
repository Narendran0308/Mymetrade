# Mymetrades Authentication System - Setup & Troubleshooting

## Quick Fix for JSON Error

If you see: **"Error: Failed to execute 'json' on 'Response': Unexpected end of JSON input"**

This means your backend is not working. Follow these steps:

---

## Step 1: Create the Database

1. Open **phpMyAdmin** at `http://localhost/phpmyadmin`
2. Click on **SQL** tab
3. Copy everything from `backend/setup.sql`
4. Paste it into the SQL box
5. Click **Go**

✅ Database created successfully!

---

## Step 2: Configure Gmail

### Get Your App Password:
1. Go to [myaccount.google.com](https://myaccount.google.com)
2. Click **Security** (left sidebar)
3. Scroll down to **App passwords**
4. Select **Mail** and **Windows PC**
5. Google generates a 16-char password
6. Copy it

### Update config.php:
Edit `backend/config.php` and replace:
```php
define('GMAIL_EMAIL', 'your-email@gmail.com');
define('GMAIL_PASSWORD', 'your-16-char-app-password');
```

**⚠️ IMPORTANT:** 
- Use the **App Password**, NOT your Gmail password
- You must have **2-Step Verification enabled**

---

## Step 3: Test the Backend

1. Go to `http://localhost/Mymetrades-main/backend/test.php`
2. You should see: `{"test":"Backend is working"}`

If you see an error, your PHP/Apache is not set up correctly.

---

## Step 4: Test the Login Page

1. Go to `http://localhost/Mymetrades-main/login.html`
2. Click **Sign Up**
3. Enter your email
4. Click **Send Verification Email**
5. Check your email for the password (check spam folder)
6. Enter the password and complete signup

---

## Common Errors & Fixes

### Error 1: "Unexpected end of JSON input"
**Cause:** Backend file error or database not created
**Fix:**
- Check `backend/setup.sql` was executed
- Check `backend/config.php` has correct database name

### Error 2: "Email not found"
**Cause:** Database created but not populated
**Fix:**
- Make sure you ran the SQL from `setup.sql`
- Check database `mymetrades_db` exists

### Error 3: "Database connection error"
**Cause:** Wrong MySQL credentials
**Fix:**
- Update `backend/config.php` with correct MySQL password
- Default username is `root`, password is usually empty

### Error 4: "Failed to send email"
**Cause:** Gmail App Password is wrong or not set
**Fix:**
- Re-generate App Password from Google Account
- Use exact 16-character password from Google
- Verify 2-Step Verification is ON

### Error 5: Email not arriving
**Cause:** Marked as spam or sending failed silently
**Fix:**
- Check spam/promotions folder
- Try test.php to see if backend works
- Check Gmail settings allow "Less secure apps"

---

## Database Tables Created

The system creates these tables automatically:

1. **users** - Stores user accounts
2. **temp_verification** - Temporary signup passwords (15 min expiry)
3. **temp_login** - Temporary login passwords (15 min expiry)
4. **login_history** - Logs all login attempts
5. **subscriptions** - Tracks user plans
6. **transactions** - Payment records
7. **email_logs** - Email sending logs
8. **support_tickets** - Support requests

---

## File Structure

```
backend/
├── config.php                  # Configuration (UPDATE THIS)
├── db.php                     # Database connection
├── email.php                  # Email sending
├── setup.sql                  # Database schema
├── send_verification_email.php # Step 1: Send signup email
├── signup.php                 # Step 2: Complete signup
├── send_login_email.php       # Step 1: Send login email
├── login.php                  # Step 2: Complete login
└── test.php                   # Test backend
```

---

## Complete Setup Checklist

- [ ] Downloaded the project
- [ ] Created database from `setup.sql`
- [ ] Enabled Gmail 2-Step Verification
- [ ] Generated Gmail App Password
- [ ] Updated `backend/config.php`
- [ ] Tested `backend/test.php`
- [ ] Tested signup at `login.html`
- [ ] Received verification email
- [ ] Completed signup successfully

---

## How It Works

### Sign Up Process:
```
1. User enters email
   ↓
2. System sends password via email
   ↓
3. User enters password from email
   ↓
4. System verifies password matches
   ↓
5. Account created in database
   ↓
6. Success message shown
```

### Login Process:
```
1. User enters email
   ↓
2. System sends password via email
   ↓
3. User enters password from email
   ↓
4. System verifies password matches
   ↓
5. Session created, user logged in
   ↓
6. Redirected to index.html
```

---

## Security Features

✅ Passwords are hashed with bcrypt
✅ Verification passwords expire after 15 minutes
✅ One-time use passwords
✅ Email validation
✅ Database protects against SQL injection
✅ Session-based authentication
✅ HTTPS recommended for production

---

## Support

If still having issues:

1. Check browser console for errors (F12)
2. Check PHP error logs
3. Verify MySQL is running
4. Test database connection
5. Verify Gmail credentials
6. Check firewall/antivirus isn't blocking requests

---

Created: May 2026
Mymetrades Authentication System v1.0

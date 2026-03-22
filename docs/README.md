# BookIT - Condo Rental Reservation System

**Version:** 2.1 | **Updated:** November 15, 2025 | **Status:** Production Ready

## 📌 Quick Navigation
- [Quick Start (3 Steps)](#quick-start)
- [System Features](#system-features)
- [Database Setup](#database-setup)
- [API Configuration](#api-configuration)
- [Email Setup](#email-setup)
- [Payment Flow](#payment-flow)
- [Bug Fixes & Recent Updates](#recent-updates)
- [Troubleshooting](#troubleshooting)
- [Deployment Checklist](#deployment-checklist)

---

## 🚀 QUICK START

### Step 1: Populate Test Database
```
URL: http://localhost/BookIT/populate_test_data.php
```
Creates:
- ✅ Test users (admin, hosts, renters)
- ✅ Branches and properties<script>
    function mapsCallback() {
        console.log('Google Maps loaded successfully');
    }
</script>
<script async src="...&callback=mapsCallback"></script>
- ✅ Sample reservations and bookings
- ✅ Amenities and reviews
- ✅ System notifications

### Step 2: Verify Data
```
URL: http://localhost/BookIT/verify_data.php
```
Shows:
- User statistics
- Booking metrics
- Revenue overview
- Upcoming check-ins

### Step 3: Login & Test
```
URL: http://localhost/BookIT/public/login.php
```

### 🔐 Test Account Credentials

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@bookit.com | Password123! |
| Host (Owner) | host1@bookit.com | Password123! |
| Host (Owner) | host2@bookit.com | Password123! |
| Renter | renter1@bookit.com | Password123! |
| Renter | renter2@bookit.com | Password123! |

---

## ✨ SYSTEM FEATURES

| Feature | Status | Details |
|---------|--------|---------|
| **Multi-branch Management** | ✅ | Manage multiple locations |
| **Unit Reservations** | ✅ | Complete booking system |
| **Amenity Booking** | ✅ | Double-booking prevention included |
| **Real PayMongo Payments** | ✅ | GCash, Card, Grab Pay |
| **Auto Email Notifications** | ✅ | Confirmations & receipts |
| **Admin Dashboard** | ✅ | Statistics & analytics |
| **Host Dashboard** | ✅ | Property management |
| **Reports & Analytics** | ✅ | Revenue & occupancy tracking |
| **User Management** | ✅ | Complete admin controls |
| **Security** | ✅ | Password hashing, session security |

---

## 📊 DATABASE SETUP

### Database Information
```
Name: condo_rental_reservation_db
Host: localhost
User: root
Password: (blank by default in WAMP)
```

### 11 Core Tables
```
✅ users                  - User accounts & profiles
✅ branches              - Branch/location management
✅ units                 - Property units/listings
✅ amenities             - Swimming pool, gym, parking, etc.
✅ amenity_bookings      - Amenity reservation records
✅ reservations          - Unit reservation records
✅ reservation_notes     - Check-in/out notes
✅ payments              - Payment transaction history
✅ payment_sources       - PayMongo payment sources
✅ notifications         - System notifications
✅ reviews               - User reviews
```

### Create Tables Manually (If needed)
Run this SQL in PHPMyAdmin:
```sql
-- Use this script: /setup_database.php
-- OR Visit: http://localhost/BookIT/setup_database.php
```

---

## 🔧 API CONFIGURATION

### PayMongo Payment Gateway

#### Step 1: Get API Keys
1. Go to: https://paymongo.com
2. Create account and get test API keys
3. Find in dashboard: Secret Key and Public Key

#### Step 2: Configure Keys
Edit: `config/paymongo.php`
```php
define('PAYMONGO_SECRET_KEY', 'sk_test_YOUR_KEY_HERE');
define('PAYMONGO_PUBLIC_KEY', 'pk_test_YOUR_KEY_HERE');
```

#### Step 3: Test Payment
1. Login as renter
2. Browse and reserve a unit
3. Proceed to payment
4. Select GCash or Card
5. Use test card: `4242 4242 4242 4242` (any future expiry, any CVV)
6. Complete payment
7. Check email for confirmation

#### Payment Methods Supported
- ✅ GCash
- ✅ Grab Pay
- ✅ Visa/Mastercard

---

## 📧 EMAIL SETUP

### Option A: Gmail (Recommended for Testing)

1. **Enable 2-Factor Authentication**
   - Go to: https://myaccount.google.com/security
   - Enable 2-Step Verification

2. **Create App Password**
   - Go to: https://myaccount.google.com/apppasswords
   - Select: Mail and Windows Computer
   - Copy the 16-character password

3. **Configure php.ini**
   - File: `C:\wamp64\bin\php\php7.4.33\php.ini`
   - Find section: `[mail function]`
   - Update:
   ```ini
   SMTP = smtp.gmail.com
   smtp_port = 587
   sendmail_from = your-email@gmail.com
   ```

4. **Restart Apache**
   - Stop WAMP
   - Start WAMP

### Option B: Custom SMTP

1. **Get SMTP Credentials**
   - From: Your hosting provider or email service
   - Credentials needed: Host, Port, Username, Password

2. **Configure php.ini**
   ```ini
   SMTP = smtp.yourprovider.com
   smtp_port = 587
   sendmail_from = your-email@domain.com
   ```

3. **Add Authentication** (if needed)
   - Use SendGrid, Mailgun, or AWS SES
   - Update in: `includes/email_integration.php`

### Test Email Configuration
```
PHP File: http://localhost/BookIT/test_email.php
```

---

## 💳 PAYMENT FLOW

```
User Initiates Payment
       ↓
System Creates PayMongo Source
       ↓
User Redirected to PayMongo Checkout
       ↓
User Enters Payment Details (Secure)
       ↓
Payment Processed by PayMongo
       ↓
Reservation Auto-Confirmed
       ↓
Email Confirmations Sent
       ↓
Admin Notified of New Booking
```

### Database Tables Involved
- `payment_sources` - Stores PayMongo payment reference IDs
- `payments` - Records payment transactions
- `reservations` - Updated with confirmation status
- `notifications` - System alerts to admin

---

## 🛡️ AMENITY DOUBLE-BOOKING PREVENTION

**Problem Solved:** Same amenity couldn't be booked twice at same time

**How It Works:**
1. User tries to book Swimming Pool 2-3 PM
2. System queries: Any existing bookings 2-3 PM?
3. If YES → Booking rejected ❌
4. If NO → Booking allowed ✅

**Prevention Function:**
```php
checkAmenityAvailability($amenity_id, $booking_date, $start_time, $end_time)
// Returns: true = available, false = occupied
```

---

## 🐛 RECENT UPDATES & BUG FIXES (November 15, 2025)

### Issue 1: Database Parameter Binding Bug ✅ FIXED
**Problem:** Database queries with NULL values failed
**Root Cause:** All parameters treated as strings, couldn't bind NULL
**Solution:** Implemented proper type detection (int/float/string/null)
**Files Modified:**
- `config/db.php` - execute_query(), get_single_result(), get_multiple_results()
- `includes/functions.php` - All database helper functions

### Issue 2: Branch Management Not Working ✅ FIXED
**Problem:** Adding branches returned success but no data appeared
**Root Cause:** Form redirected immediately, and error messages weren't displayed
**Solution:** Implemented session-based message handling
**Files Modified:**
- `admin/manage_branch.php` - Session message storage & retrieval

### Issue 3: Admin Dashboard & User Management Pages 500 Error ✅ FIXED
**Problem:** Pages returned HTTP 500 error
**Root Cause:** Database helper functions failing due to parameter binding
**Solution:** Fixed all database functions with proper type detection
**Result:** Pages now load successfully with all data

---

## 🔍 TROUBLESHOOTING

### ❌ Page Returns 500 Error

**Check:**
1. **PHP Error Log**
   ```
   C:\wamp64\logs\php_error.log
   ```
   - Look for error messages
   - Check for parse errors or warnings

2. **Apache Error Log**
   ```
   C:\wamp64\logs\apache_error.log
   ```
   - Check for server configuration issues

3. **Database Connection**
   - Can you access PHPMyAdmin?
   - Database server running?
   - Correct credentials?

### ❌ Payment Not Processing

**Check:**
1. **PayMongo Keys Correct?**
   - Edit: `config/paymongo.php`
   - Verify both SECRET_KEY and PUBLIC_KEY
   - Test vs Live mode?

2. **Test Card Correct?**
   - Use: `4242 4242 4242 4242`
   - Expiry: Any future date
   - CVV: Any 3 digits

3. **payment_sources Table Exists?**
   - PHPMyAdmin → condo_rental_reservation_db
   - See payment_sources table?

4. **Check Error Logs**
   - `php_error.log`
   - `logs/` directory

### ❌ Email Not Sending

**Check:**
1. **SMTP Configured?**
   - Open: `C:\wamp64\bin\php\php7.4.33\php.ini`
   - Section: `[mail function]`
   - Has SMTP and smtp_port?

2. **Gmail Specific Issues**
   - 2FA enabled?
   - App password created?
   - Correct app password in php.ini?
   - Port 587 not blocked by firewall?

3. **Test Manually**
   - Create: `test_email.php`
   - Code:
   ```php
   $to = "your-email@gmail.com";
   $subject = "Test Email";
   $message = "This is a test email";
   mail($to, $subject, $message);
   echo "Email sent!";
   ?>
   ```
   - Visit: `http://localhost/BookIT/test_email.php`

4. **Check Logs**
   - `php_error.log`
   - `php_mail.log` (if enabled)

### ❌ Amenity Still Double-Booking

**Check:**
1. **Function Defined?**
   - `includes/functions.php`
   - Search: `checkAmenityAvailability()`

2. **Function Called?**
   - In: `bookAmenity()` function
   - Before: Inserting booking record

3. **Database Records?**
   - PHPMyAdmin → amenity_bookings
   - See conflicting 'confirmed' bookings?

---

## 📋 DEPLOYMENT CHECKLIST

### Pre-Launch
- [ ] Database created and populated
- [ ] PayMongo API keys configured
- [ ] Email/SMTP setup completed
- [ ] Test payment flow works end-to-end
- [ ] Confirmation email received
- [ ] Admin dashboard loads without errors
- [ ] All user management functions work
- [ ] Amenity double-booking prevented successfully
- [ ] Error logs checked - no critical errors
- [ ] Backup strategy in place

### Production Deployment
- [ ] Switch PayMongo from test to live mode
- [ ] Update live API keys
- [ ] Enable HTTPS on all payment pages
- [ ] Setup SendGrid/Mailgun for production email
- [ ] Enable strong admin passwords
- [ ] Two-factor authentication for admin
- [ ] Setup database automated backups
- [ ] Setup error monitoring/alerts
- [ ] Database query optimization
- [ ] Security audit completed

### Post-Launch Monitoring
- [ ] Monitor error logs daily
- [ ] Check payment success/failure rates weekly
- [ ] Verify email delivery rates
- [ ] Monitor server performance
- [ ] Regular security scans
- [ ] Monthly database maintenance

---

## 📁 KEY FILES & FOLDERS

### Configuration
- `config/constants.php` - System constants & SITE_URL
- `config/db.php` - Database connection & helper functions
- `config/paymongo.php` - PayMongo API configuration
- `config/email.php` - Email configuration (if using)

### Includes & Functions
- `includes/session.php` - Session management
- `includes/functions.php` - Core business logic (1100+ lines)
- `includes/email_integration.php` - Email sending functions
- `includes/security.php` - Security functions
- `includes/sidebar.php` - Navigation sidebar

### Admin Functionality
- `admin/admin_dashboard.php` - Dashboard with statistics
- `admin/user_management.php` - User CRUD operations
- `admin/manage_branch.php` - Branch management
- `admin/unit_management.php` - Unit/property management

### Payment Processing
- `includes/api/payment/process_payment.php` - Payment handler
- `renter/payment_success.php` - Payment success page

### Public Facing
- `public/login.php` - Authentication
- `public/index.php` - Home page
- `renter/browse_units.php` - Unit listing
- `renter/reserve_unit.php` - Reservation form

### Database & Setup
- `setup_database.php` - Database table creation
- `populate_test_data.php` - Test data generation
- `verify_data.php` - Data verification tool

### Logs & Data
- `logs/` - Application logs
- `uploads/` - User uploaded files
- `modules/` - Additional modules

---

## 💡 CODE EXAMPLES

### Processing a Payment
```php
require_once 'includes/functions.php';
require_once 'includes/email_integration.php';

$payment_id = processPayment(
    $reservation_id,    // Reservation to confirm
    null,               // Amenity booking ID (if applicable)
    $user_id,           // User making payment
    $amount,            // Total amount
    'paymongo'          // Payment method
);

// Automatically:
// 1. Records payment
// 2. Confirms reservation
// 3. Sends email confirmation
// 4. Notifies admin
```

### Checking Amenity Availability
```php
require_once 'includes/functions.php';

$is_available = checkAmenityAvailability(
    $amenity_id,      // Amenity ID (e.g., 5)
    $booking_date,    // Date (e.g., '2025-12-20')
    $start_time,      // Start (e.g., '14:00:00')
    $end_time         // End (e.g., '15:00:00')
);

if ($is_available) {
    // Proceed with booking
} else {
    // Time slot occupied
}
```

### Sending Email Confirmation
```php
require_once 'includes/email_integration.php';

sendReservationConfirmationEmail(
    $user_email,
    $user_name,
    $reservation_details
);
```

---

## 🔐 Security Features

✅ Password Hashing (bcrypt)
✅ SQL Injection Prevention (Prepared Statements)
✅ CSRF Token Protection
✅ Session Security
✅ Input Sanitization
✅ Role-Based Access Control (RBAC)
✅ Email Verification (Optional)
✅ Secure Payment Integration (PayMongo)

---

## 📊 System Architecture

```
┌─────────────────────────────────────────┐
│       USER INTERFACE (Frontend)         │
│  Admin | Host | Renter                  │
└──────────────┬──────────────────────────┘
               │
┌──────────────▼──────────────────────────┐
│    APPLICATION LAYER (PHP)              │
│  functions.php | modules/ | admin/      │
└──────────────┬──────────────────────────┘
               │
┌──────────────▼──────────────────────────┐
│  PAYMENT GATEWAY & EMAIL SERVICES       │
│  PayMongo | SMTP/Gmail | Notifications  │
└──────────────┬──────────────────────────┘
               │
┌──────────────▼──────────────────────────┐
│      DATABASE LAYER (MySQL)             │
│  11 Tables | Queries | Transactions     │
└─────────────────────────────────────────┘
```

---

## 📞 SUPPORT & RESOURCES

### Official Documentation
- **PayMongo**: https://developers.paymongo.com
- **PHP Mail Function**: https://php.net/manual/en/function.mail.php
- **MySQL**: https://dev.mysql.com/doc/refman/8.0/en/
- **WAMP Server**: https://www.wampserver.com/

### Common Issues & Solutions
- See [Troubleshooting](#troubleshooting) section above
- Check error logs: `C:\wamp64\logs\`
- Email us for support

---

## 📝 VERSION HISTORY

| Version | Date | Changes |
|---------|------|---------|
| **2.1** | Nov 15, 2025 | Fixed database binding bugs, improved error handling |
| **2.0** | Nov 14, 2025 | Added PayMongo payments, email automation, conflict prevention |
| **1.0** | Nov 1, 2025 | Initial release with core functionality |

---

## 📄 LICENSE & TERMS

This system is provided as-is for development and testing purposes. For production deployment, ensure compliance with local regulations regarding payments, data privacy, and accommodation services.

---

**Last Updated:** November 15, 2025  
**Status:** ✅ Production Ready  
**Support Level:** Active Development

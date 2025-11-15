# iSCHO Database Setup Guide

## Overview
This document provides complete instructions for recreating the iSCHO (Integrated Scholarship Application Portal) database from scratch.

## Database Structure

Your iSCHO system uses **12 main tables**:

### Core Tables:
1. **users** - User authentication and basic information
2. **users_info** - Extended applicant information
3. **user_personal** - Academic information (degree, course, current college)
4. **user_residency** - Address and residency details
5. **user_fam** - Family background information
6. **user_docs** - Document file paths (COR, Indigency, Voter ID, Profile Picture, Claim Photo)

### System Tables:
7. **otp_verifications** - Email OTP codes for registration
8. **password_reset_tokens** - Password reset functionality
9. **application_period** - Application deadline management
10. **notices** - Admin-to-user communications
11. **claim_tokens** - QR codes for scholarship claiming
12. **secret_keys** - Encrypted keys for superadmin operations

## Setup Instructions

### Option 1: Import Complete Schema

```bash
# Method 1: Using MySQL command line
mysql -u your_username -p ischo2 < ischo_database_schema.sql

# Method 2: Using phpMyAdmin
# - Login to phpMyAdmin
# - Select your database (ischo2)
# - Go to Import tab
# - Choose the ischo_database_schema.sql file
# - Click Go
```

### Option 2: Manual Setup

1. **Create Database:**
```sql
CREATE DATABASE IF NOT EXISTS ischo2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ischo2;
```

2. **Run the SQL Schema:**
Copy and execute the entire content of `ischo_database_schema.sql`

### Option 3: AWS RDS Setup

Since your `connection.php` shows AWS RDS configuration:
```php
$host = 'ischo.cd2yqo8eytpf.ap-southeast-1.rds.amazonaws.com';
$dbname = 'ischo2';
$user = 'admin';
$pass = 'X7!pL#e9Bz^W';
```

1. Connect to your AWS RDS MySQL instance
2. Run the complete schema SQL
3. Verify all tables are created

## Default Data

The schema includes:
- **Default Superadmin**: 
  - Email: `admin@ischo.com`
  - Password: `Admin123!` (hashed in database)
- **Sample Application Period**: Deadline set to `2025-12-31`

## File Upload Directories

Ensure these directories exist and are writable:
- `uploads/` - For user document uploads
- `claim_photos/` - For scholarship claim photos
- `qrcodes/` - For QR code generation
- `logs/` - For error logging

```bash
mkdir uploads claim_photos qrcodes logs
chmod 777 uploads claim_photos qrcodes logs
```

## Verification Steps

After importing the database:

1. **Check Tables Created:**
```sql
SHOW TABLES;
-- Should show 12 tables: users, users_info, user_personal, user_residency, user_fam, user_docs, otp_verifications, password_reset_tokens, application_period, notices, claim_tokens, secret_keys
```

2. **Test Superadmin Login:**
- Email: `admin@ischo.com`
- Password: `Admin123!`

3. **Check Application Status:**
```sql
SELECT application_deadline FROM application_period ORDER BY updated_at DESC LIMIT 1;
-- Should return: 2025-12-31
```

## Key Features Implemented

### Registration Flow:
- OTP email verification
- Philippine location validation (Zambales & Bataan provinces)
- Password strength requirements
- Email domain validation

### User Roles:
- **Applicant**: Can apply for scholarships
- **Admin**: Can review and approve/deny applications
- **Superadmin**: Full system administration

### File Management:
- Profile pictures
- COR (Certificate of Registration)
- Indigency certificates
- Voter ID/Certificate
- Claim photos for scholarship redemption

### Security Features:
- Password hashing (bcrypt)
- JWT token authentication
- SQL injection protection (PDO prepared statements)
- Email OTP verification
- Password reset tokens
- Encrypted secret keys for admin operations

### Communication System:
- In-app notices
- Email notifications
- Status updates for applicants

## Troubleshooting

### Common Issues:

1. **Connection Errors:**
   - Verify AWS RDS is accessible
   - Check firewall/security group settings
   - Confirm credentials in `connection.php`

2. **Missing Tables:**
   - Ensure complete SQL execution
   - Check for MySQL errors during import
   - Verify user privileges

3. **File Upload Issues:**
   - Check directory permissions
   - Verify upload paths in code
   - Ensure sufficient disk space

4. **OTP Not Working:**
   - Check SMTP configuration in PHP files
   - Verify Gmail app password: `wcep jxly qzwn ybud`
   - Check email domain validation

## Additional Notes

- All dates use Manila timezone (`Asia/Manila`)
- File paths use forward slashes (`/`)
- Database uses UTF8MB4 for full Unicode support
- Foreign key constraints ensure data integrity
- Indexes optimize query performance

## Contact Information

For technical support or questions about this database setup:
- Email: `ischobsit@gmail.com`
- Developers: Alan Michael Batac & Icon Zeus R. Gonzales

---

**Backup Recommendation**: Always backup your database regularly, especially before making schema changes!

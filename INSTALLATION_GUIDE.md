# NMS Accounting System - Installation & Setup Guide

## 1. Prerequisites
* **Server:** XAMPP (for Windows) or any PHP/MySQL environment.
* **PHP Version:** 7.4 or later.
* **Database:** MySQL / MariaDB.

---

## 2. Project Installation
1. **Locate Source:** Copy the `NMS` folder from `C:/Users/legal/AndroidStudioProjects/NMS`.
2. **Move to XAMPP:** Paste the folder into your XAMPP web directory: `C:/xampp/htdocs/NMS`.
3. **Logo:** Ensure your company logo is saved as `nms_logo.png` inside the `C:/xampp/htdocs/NMS` folder.

---

## 3. Database Configuration
1. **Start XAMPP:** Open XAMPP Control Panel and start **Apache** and **MySQL**.
2. **Create Database:**
   - Open your browser and go to: `http://localhost/phpmyadmin`
   - Click **New** in the left sidebar.
   - Database Name: `nms_accounting`
   - Click **Create**.

3. **Import Data:**
   Select the `nms_accounting` database, click the **Import** tab, and upload the following SQL files from the `sql/` folder in this **EXACT ORDER**:
   1. `schema.sql` (Core Structure)
   2. `auth.sql` (User System)
   3. `inventory.sql` (Stock/WAC Tracking)
   4. `audit_trail.sql` (Log System)
   5. `budgeting.sql` (Budgeting Module)
   6. `reconciliation.sql` (Banking Tools)
   7. `multi_currency.sql` (Multi-Currency Support)
   8. `recurring_invoices.sql` (Recurring Billing)
   9. `pakistan_tax_updates.sql` (GST/WHT Fields)
   10. `fix_schema_inconsistencies.sql` (Schema Fixes)
   11. **`nms_custom_setup.sql`** (NMS Branding & Chart of Accounts)

---

## 4. Application Configuration
The configuration is located at `includes/config.php`. It is already set to:
* **Host:** `localhost`
* **User:** `root`
* **Password:** `(empty)`
* **Database Name:** `nms_accounting`
* **Company Name:** `Naqeebs Multi Services`

---

## 5. Login Credentials
* **URL:** `http://localhost/NMS/login.php`
* **Default Username:** `admin`
* **Default Password:** `password`

---

## 6. Support for NMS Chart of Accounts
The system is pre-configured with your Pakistan-specific accounts:
* **Assets:** A001 (Cash), A002 (Petty Cash), A003 (Sulaman HBL), A004 (Khakan Bank).
* **Expenses:** E001 to E035 (Detailed expense tracking).
* **Liabilities:** L002 (Abdullah Khan Safi), L003 (Danish Munir).
* **Equity:** L0001 (Capital).

**Note:** All transactions use double-entry logic. Recording an expense automatically updates the relevant ledgers.

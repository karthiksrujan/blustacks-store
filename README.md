# AppStore Platform - PHP + MySQL Application

A production-ready, fully responsive, and mobile-first Microsoft Store/Play Store-like application platform where users discover, rate, download, and wishlist apps, developers publish listings, and administrators moderate submissions.

---

## Features

1. **Homepage (`index.php`)**: Hero banner, auto-playing featured apps carousel, responsive category tiles, trending grid, and a dark mode toggle.
2. **Browse & Category Page (`browse.php`)**: Grid layout with side filters (by category, rating, price, and sorting) and a live autocomplete search bar.
3. **App Detail Page (`app.php`)**: Carousel of screenshots, system requirements checklist, collapsible version history, reviews with rating distribution graph, and a Heart wishlist toggle.
4. **Developer Console (`developer/dashboard.php`)**: My Listings table, submission/edit forms (validating MIME-types and magic bytes, scanning for malware, and resetting status to pending upon edits), and graphic performance analytics.
5. **User Account Portal (`account.php`)**: Logged-out unified login/register panels (with login rate limiting and secure session settings), and logged-in tabbed settings (wishlist, download history, and written reviews list with deletion).
6. **Admin Control Panel (`admin/dashboard.php`)**: Pending submissions moderation (with reason for rejection modal), live approved catalog overview, categories CRUD builder, platform-wide metrics, and audit logs.
7. **Security Architecture**: Prepared statements, output sanitization (XSS protection), CSRF tokens, secure session cookie configs (30-min timeout), rate-limiting (login/upload), and protected uploads (`.htaccess`).

---

## Directory Structure

```
├── config/
│   └── db.php                  # Database connection (PDO) & session settings
├── includes/
│   ├── header.php              # Responsive Navigation & Autocomplete JS
│   ├── footer.php              # Shared site footer & Theme/Menu togglers
│   ├── auth.php                # Authentication helper & Authorization guards
│   ├── rate_limiter.php        # IP & User-based rate limiter (Database-backed)
│   ├── csrf.php                # CSRF Token generator and validator
│   └── functions.php           # Sanitization, visual ratings, and malware scanner
├── api/
│   ├── autocomplete.php        # AJAX search autocomplete returning JSON
│   ├── wishlist.php            # AJAX toggle for adding/removing wishlists
│   └── leave_review.php        # Endpoint to submit reviews
├── uploads/
│   ├── apps/                   # App files (.apk, .exe, .zip)
│   ├── icons/                  # App icons
│   ├── banners/                # App banners
│   ├── screenshots/            # App screenshots
│   └── .htaccess               # Secures uploads directory (blocks PHP execution)
├── index.php                   # Homepage
├── browse.php                  # Grid of apps with filter sidebar
├── app.php                     # App details, screenshots, and reviews
├── download.php                # File download tracker & secure downloader
├── account.php                 # Unified auth and user profile dashboard
├── logout.php                  # Secure logout and session destruction
├── developer/
│   ├── dashboard.php           # Developer portal dashboard
│   ├── submit.php              # Submission and edit form
│   └── delete.php              # App deletion handler
├── admin/
│   ├── dashboard.php           # Admin dashboard
│   ├── review.php              # Approve/Reject action handler
│   ├── categories.php          # Category CRUD manager
│   └── logs.php                # View system audit logs (embedded)
├── database.sql                # SQL database schema and seeds
└── README.md                   # Setup and deployment instructions
```

---

## Local Installation Setup

1. **Prerequisites**: Ensure you have PHP 7.4+ and a MySQL/MariaDB server running locally (e.g. using XAMPP, WampServer, MAMP, or Docker).
2. **Create Database**: Open phpMyAdmin or your MySQL CLI client and create a database named `app_store`:
   ```sql
   CREATE DATABASE app_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. **Import Schema**: Import the `database.sql` file into the newly created database:
   ```bash
   mysql -u root -p app_store < database.sql
   ```
4. **Configure Database**: Open `config/db.php` and configure your database parameters:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'app_store');
   define('DB_USER', 'root');
   define('DB_PASS', 'your_mysql_password');
   ```
5. **Base URL config**: If you are running the project in a subfolder (e.g., `http://localhost/app-store/`), edit the `BASE_URL` constant in `config/db.php`:
   ```php
   define('BASE_URL', '/app-store/');
   ```
   If running via a virtual host or php built-in server from the project root (`php -S localhost:8000`), keep it as:
   ```php
   define('BASE_URL', '/');
   ```
6. **VirusTotal Integration (Optional)**: To enable cloud malware scanning on uploads, add your API key in `config/db.php`:
   ```php
   define('VIRUSTOTAL_API_KEY', 'your_virustotal_api_key');
   ```
   If left blank, the system automatically falls back to local signature verification (verifying file extensions, MIME-types, and magic byte headers for ZIP, APK, and EXE).

---

## Testing Credentials

The database migration seeds three default accounts:

* **Administrator Role**:
  * Email: `admin@store.com`
  * Password: `AdminPassword123!`
* **Developer Role**:
  * Email: `dev@store.com`
  * Password: `DevPassword123!`
* **Standard User Role**:
  * Email: `user@store.com`
  * Password: `UserPassword123!`

---

## Deployment Guide for Infinity Free Hosting

### Step 1: Upload Files
1. Log in to your Infinity Free client area and open the **FTP details**.
2. Connect to the host using an FTP client (like FileZilla).
3. Upload all project folders and files (excluding `database.sql` and `README.md`) into the `/htdocs/` folder.

### Step 2: Set Folder Permissions
Make sure the `uploads/` directory has write permissions so developers can upload icons and binaries:
1. In your FTP client, right-click the `uploads/` folder.
2. Select **File permissions**.
3. Set the permission code to `755` (or `775` if `755` fails to write).
4. Check **Recurse into subdirectories** and select **Apply to directories only** so subfolders (`uploads/apps/`, `uploads/icons/`, etc.) get the same permissions.

### Step 3: Setup Remote Database
1. Go to the Infinity Free control panel and click on **MySQL Databases**.
2. Create a new database.
3. Note the Hostname, Username, and Password provided in the panel.
4. Click on **Admin** (which opens phpMyAdmin for that database).
5. Click **Import**, select `database.sql` from your local files, and submit.

### Step 4: Configure db.php on Infinity Free
Edit `config/db.php` in your FTP client (or via the Infinity Free Online File Manager) with the remote database details:
```php
define('DB_HOST', 'sqlxxx.epizy.com'); // Insert your remote db host
define('DB_NAME', 'epiz_xxx_app_store'); // Insert your remote db name
define('DB_USER', 'epiz_xxx'); // Insert your remote db user
define('DB_PASS', 'your_panel_password'); // Insert your remote panel password
define('BASE_URL', '/'); // Set to your site path
```

---

## Infinity Free Limitations & Workarounds

### 1. Upload Size Limits (Critical)
* **Limitation**: Infinity Free sets `upload_max_filesize` to **2MB - 10MB** by default in their PHP configuration. This cannot be modified because custom `.user.ini` or `.htaccess` `php_value` changes are blocked on free hosting plans.
* **Workaround**: Our platform implements a dual file-source model:
  * When submitting/editing an app, developers can choose between uploading a physical file (which runs signature checks) OR providing an **External Download URL** (such as a link to Google Drive, Dropbox, or GitHub Releases).
  * External links bypass php upload constraints, allowing developers to list large apps (e.g. 50MB APKs or 200MB EXE packages) seamlessly.

### 2. Output Buffering / Session Cookie errors
* **Limitation**: Shared hosts occasionally buffer headers, triggering "headers already sent" errors when attempting redirects or session starts.
* **Solution**: `config/db.php` handles session creation securely before output begins. In addition, `download.php` performs an `ob_end_clean()` flush before streaming files to prevent corrupted binary packages.

### 3. CPU/Epiz Limit Throttling
* **Limitation**: CPU usage is capped on free hosting. If your database queries do not use indexes, daily limits can be quickly exhausted.
* **Solution**: Our database schema implements indexes on frequently accessed columns (`app_id`, `user_id`, `category_id`, `status`), and utilizes cached columns (such as `average_rating` and `download_count` in the `apps` table) to avoid executing heavy `JOIN` and `AVG()` queries on index lists.

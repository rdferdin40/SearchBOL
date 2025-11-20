# BOLSearch - Bills of Lading Search Portal

A production-ready PHP 8.2 application for searching and viewing Bills of Lading PDFs with OCR-powered full-text search.

## Features

- **Public Search Interface**: No login required for searching and viewing BOL documents
- **Full-Text Search**: Powered by Meilisearch with fuzzy matching
- **OCR Support**: Automatic text extraction from scanned PDFs using Tesseract
- **Advanced Filtering**: Filter by BOL#, carrier, shipper, consignee, dates, and more
- **Admin Panel**: Manage indexing jobs, view errors, and monitor system health
- **Metadata Extraction**: Automatically extracts BOL metadata from documents
- **Duplicate Detection**: Identifies duplicate documents via SHA-256 hashing

## System Requirements

- **OS**: Ubuntu Server 20.04+ (running in Hyper-V on Windows Server 2022)
- **Web Server**: Apache 2.4+
- **PHP**: 8.2+
- **Database**: MariaDB 10.5+ or MySQL 8.0+
- **Search Engine**: Meilisearch 1.0+
- **OCR**: Tesseract 4.0+ with poppler-utils

## Quick Start Installation

### 1. Install System Dependencies

```bash
sudo apt update
sudo apt install -y apache2 mariadb-server php8.2 php8.2-cli php8.2-mysql \
    php8.2-curl php8.2-mbstring php8.2-xml php8.2-zip \
    tesseract-ocr tesseract-ocr-eng poppler-utils rsync cifs-utils git
```

### 2. Install Meilisearch

```bash
curl -L https://install.meilisearch.com | sh
sudo mv ./meilisearch /usr/local/bin/

# Create systemd service
sudo tee /etc/systemd/system/meilisearch.service > /dev/null <<EOF
[Unit]
Description=Meilisearch
After=network.target

[Service]
Type=simple
User=www-data
ExecStart=/usr/local/bin/meilisearch --http-addr 127.0.0.1:7700 --env production
Restart=on-failure

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable meilisearch
sudo systemctl start meilisearch
```

### 3. Create Database

```bash
sudo mysql -u root -p
```

```sql
CREATE DATABASE bolsearch CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'bolsearch_user'@'localhost' IDENTIFIED BY 'YOUR_SECURE_PASSWORD';
GRANT ALL PRIVILEGES ON bolsearch.* TO 'bolsearch_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 4. Clone and Deploy Application

```bash
cd /var/www/html
sudo git clone [YOUR_REPO_URL] BOLSearch
cd BOLSearch

# Set ownership
sudo chown -R www-data:www-data /var/www/html/BOLSearch

# Create logs directory
sudo mkdir -p logs
sudo chown www-data:www-data logs
```

### 5. Import Database Schema

```bash
mysql -u bolsearch_user -p bolsearch < schema.sql
```

### 6. Configure Application

Edit `config/config.php` and update the database password and paths:

```php
'db' => [
    'password' => 'YOUR_SECURE_PASSWORD',
],
```

### 7. Set Up PDF Storage

```bash
sudo mkdir -p /srv/bols
sudo chown www-data:www-data /srv/bols
```

### 8. Configure Apache

```bash
sudo nano /etc/apache2/sites-available/bolsearch.conf
```

Add:

```apache
<VirtualHost *:80>
    ServerName 192.168.0.20
    DocumentRoot /var/www/html/BOLSearch/public

    <Directory /var/www/html/BOLSearch/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <Directory /var/www/html/BOLSearch/config>
        Require all denied
    </Directory>

    <Directory /var/www/html/BOLSearch/src>
        Require all denied
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/bolsearch_error.log
    CustomLog \${APACHE_LOG_DIR}/bolsearch_access.log combined
</VirtualHost>
```

```bash
sudo a2ensite bolsearch
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### 9. Set Up Cron Jobs

```bash
sudo crontab -e
```

Add:

```
# Sync PDFs from Windows (hourly)
0 * * * * /var/www/html/BOLSearch/sync_bols.sh

# Reindex documents (every 10 minutes)
*/10 * * * * /usr/bin/php /var/www/html/BOLSearch/tasks/reindex.php >> /var/log/bolsearch_reindex.log 2>&1
```

## Default Admin Credentials

**⚠️ CHANGE IMMEDIATELY AFTER FIRST LOGIN!**

- **Username**: `admin`
- **Password**: `admin123`

### Generate New Password Hash

```bash
php -r "echo password_hash('your_new_password', PASSWORD_DEFAULT);"
```

Update in database:

```sql
UPDATE admin_users SET password_hash = 'YOUR_HASH' WHERE username = 'admin';
```

## Usage

- **Public Search**: http://192.168.0.20/BOLSearch/
- **Admin Panel**: http://192.168.0.20/BOLSearch/admin/

## File Structure

```
/BOLSearch/
├── config/          # Configuration files
├── src/             # PHP classes
├── public/          # Web-accessible files
│   ├── admin/       # Admin panel
│   └── assets/      # CSS/JS/images
├── tasks/           # CLI scripts
├── logs/            # Application logs
├── schema.sql       # Database schema
├── sync_bols.sh     # PDF sync script
└── README.md        # This file
```

## Troubleshooting

### Check Meilisearch

```bash
sudo systemctl status meilisearch
curl http://127.0.0.1:7700/health
```

### Check Logs

```bash
tail -f logs/app.log
tail -f /var/log/apache2/bolsearch_error.log
```

### Test OCR

```bash
tesseract --version
pdftotext -v
```

## Security Checklist

- [ ] Change default admin password
- [ ] Update database password in config
- [ ] Set proper file permissions (no 777)
- [ ] Configure firewall (ufw)
- [ ] Consider adding HTTPS/SSL
- [ ] Review Apache security settings

## Support

For issues, check application logs and ensure all dependencies are installed.

## License

Proprietary - Internal Use Only

---

**Note**: Additional files (remaining PHP source files, public pages, admin pages, CSS/JS) are being added to the repository. Check the repository for the complete codebase.

# FestaLaurea - Guida al Deployment

## Panoramica
FestaLaurea è una piattaforma web per la prenotazione di locali per feste di laurea, sviluppata in PHP con MySQL e frontend responsive.

## Requisiti del Server

### Requisiti Minimi
- **PHP**: 8.0 o superiore
- **MySQL**: 5.7 o superiore (o MariaDB 10.2+)
- **Apache**: 2.4 o superiore con mod_rewrite abilitato
- **Spazio Disco**: 500MB minimo
- **RAM**: 512MB minimo

### Estensioni PHP Richieste
```
- php-mysql
- php-curl
- php-mbstring
- php-json
- php-openssl
- php-zip
```

## Installazione

### 1. Preparazione del Server
```bash
# Aggiorna il sistema
sudo apt update && sudo apt upgrade -y

# Installa Apache, PHP e MySQL
sudo apt install apache2 php php-mysql php-curl php-mbstring php-json php-openssl php-zip mysql-server -y

# Abilita mod_rewrite
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### 2. Configurazione Database
```sql
-- Crea il database
CREATE DATABASE festalaurea CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Crea l'utente
CREATE USER 'festalaurea_user'@'localhost' IDENTIFIED BY 'password_sicura_qui';
GRANT ALL PRIVILEGES ON festalaurea.* TO 'festalaurea_user'@'localhost';
FLUSH PRIVILEGES;

-- Importa lo schema
mysql -u festalaurea_user -p festalaurea < schema.sql
```

### 3. Configurazione Applicazione
```bash
# Copia i file nella directory web
sudo cp -r * /var/www/html/

# Imposta i permessi
sudo chown -R www-data:www-data /var/www/html/
sudo chmod -R 755 /var/www/html/
sudo chmod -R 777 /var/www/html/logs/ # Se esiste

# Installa le dipendenze Composer
cd /var/www/html/
composer install --no-dev --optimize-autoloader
```

### 4. Configurazione Environment
```bash
# Copia e modifica il file di configurazione
cp config/config.php.example config/config.php

# Modifica le credenziali del database
nano config/config.php
```

### 5. Configurazione Apache
```apache
<VirtualHost *:80>
    ServerName festalaurea.eu
    ServerAlias www.festalaurea.eu
    DocumentRoot /var/www/html
    
    <Directory /var/www/html>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/festalaurea_error.log
    CustomLog ${APACHE_LOG_DIR}/festalaurea_access.log combined
</VirtualHost>
```

## Configurazione SSL (Produzione)

### Con Let's Encrypt
```bash
# Installa Certbot
sudo apt install certbot python3-certbot-apache -y

# Ottieni il certificato SSL
sudo certbot --apache -d festalaurea.eu -d www.festalaurea.eu

# Verifica il rinnovo automatico
sudo certbot renew --dry-run
```

## Configurazioni di Sicurezza

### 1. File .htaccess
Il file `.htaccess` include già:
- Protezione file sensibili
- Headers di sicurezza
- Compressione GZIP
- Cache browser
- Rewrite rules per API

### 2. Configurazione PHP (php.ini)
```ini
# Sicurezza
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log

# Limiti
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 30
memory_limit = 256M

# Sessioni
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
```

### 3. Configurazione MySQL
```sql
-- Rimuovi utenti anonimi
DELETE FROM mysql.user WHERE User='';

-- Rimuovi database di test
DROP DATABASE IF EXISTS test;

-- Aggiorna privilegi
FLUSH PRIVILEGES;
```

## Monitoraggio e Manutenzione

### 1. Log Files
- **Apache Error Log**: `/var/log/apache2/festalaurea_error.log`
- **Apache Access Log**: `/var/log/apache2/festalaurea_access.log`
- **PHP Error Log**: `/var/log/php_errors.log`
- **MySQL Error Log**: `/var/log/mysql/error.log`

### 2. Backup Automatico
```bash
#!/bin/bash
# Script di backup giornaliero

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backup/festalaurea"

# Backup database
mysqldump -u festalaurea_user -p festalaurea > $BACKUP_DIR/db_$DATE.sql

# Backup files
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /var/www/html/

# Rimuovi backup vecchi (>30 giorni)
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete
```

### 3. Aggiornamenti
```bash
# Aggiorna dipendenze Composer
composer update --no-dev --optimize-autoloader

# Aggiorna il sistema
sudo apt update && sudo apt upgrade -y

# Riavvia servizi se necessario
sudo systemctl restart apache2
sudo systemctl restart mysql
```

## Performance Optimization

### 1. Cache OpCode PHP
```bash
# Installa e configura OPcache
sudo apt install php-opcache -y

# Aggiungi a php.ini:
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
```

### 2. Compressione e Cache
- Il file `.htaccess` include già configurazioni per:
  - Compressione GZIP
  - Cache browser per risorse statiche
  - Headers di performance

### 3. Database Optimization
```sql
-- Ottimizza tabelle regolarmente
OPTIMIZE TABLE venues, users, bookings, reviews;

-- Analizza query lente
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;
```

## Troubleshooting

### Problemi Comuni

#### 1. Errore 500 - Internal Server Error
```bash
# Controlla i log di Apache
sudo tail -f /var/log/apache2/festalaurea_error.log

# Controlla i permessi
sudo chown -R www-data:www-data /var/www/html/
sudo chmod -R 755 /var/www/html/
```

#### 2. Errori di Connessione Database
```bash
# Verifica che MySQL sia in esecuzione
sudo systemctl status mysql

# Testa la connessione
mysql -u festalaurea_user -p -h localhost festalaurea
```

#### 3. Problemi di Rewrite
```bash
# Verifica che mod_rewrite sia abilitato
sudo a2enmod rewrite
sudo systemctl restart apache2

# Controlla la configurazione Apache
sudo apache2ctl configtest
```

#### 4. Problemi di Performance
```bash
# Monitora l'uso delle risorse
htop
iotop

# Controlla le query lente
sudo tail -f /var/log/mysql/slow.log
```

## Configurazioni Avanzate

### 1. Load Balancing (per traffico elevato)
- Configurare più server web
- Utilizzare un load balancer (nginx/HAProxy)
- Database master-slave replication

### 2. CDN Integration
- Configurare CloudFlare o AWS CloudFront
- Ottimizzare delivery di immagini e assets statici

### 3. Monitoring
- Installare strumenti come Nagios o Zabbix
- Configurare alerting per downtime
- Monitorare metriche di performance

## Sicurezza Avanzata

### 1. Firewall
```bash
# Configura UFW
sudo ufw enable
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
```

### 2. Fail2Ban
```bash
# Installa Fail2Ban
sudo apt install fail2ban -y

# Configura per Apache
sudo cp /etc/fail2ban/jail.conf /etc/fail2ban/jail.local
# Modifica jail.local per abilitare apache-auth, apache-badbots, etc.
```

### 3. Aggiornamenti di Sicurezza
```bash
# Configura aggiornamenti automatici di sicurezza
sudo apt install unattended-upgrades -y
sudo dpkg-reconfigure unattended-upgrades
```

## Contatti e Supporto

Per supporto tecnico o domande sul deployment:
- **Email**: tech@festalaurea.eu
- **Documentazione**: Questo file e i commenti nel codice
- **Repository**: [Link al repository se disponibile]

---

**Nota**: Questa documentazione deve essere aggiornata regolarmente con nuove funzionalità e modifiche alla piattaforma.

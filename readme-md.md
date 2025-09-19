# 🎓 FestaLaurea - Sistema Completo per Organizzazione Feste di Laurea

## 📋 Panoramica
FestaLaurea è una piattaforma completa per l'organizzazione di feste di laurea con:
- 🔐 Login con email e social (Google/Facebook)
- 💳 Pagamenti online (Stripe/PayPal)
- 📊 Dashboard utente e amministratore
- 📱 Design responsive mobile-first
- 🔍 Sistema di ricerca e prenotazione locali
- 👥 Gestione invitati e pagamenti condivisi
- ⭐ Sistema recensioni e rating
- 💬 Messaggistica interna

## 🚀 Installazione Rapida

### Prerequisiti
- PHP 7.4+ con estensioni: mysqli, pdo, json, mbstring
- MySQL 5.7+ o MariaDB 10.3+
- Apache con mod_rewrite abilitato
- Composer (opzionale per dipendenze avanzate)

### Step 1: Struttura File
Crea la seguente struttura di cartelle nel tuo hosting:

```
festalaurea/
├── index.html
├── register.html
├── dashboard.html
├── .htaccess
├── .env
├── api/
│   └── auth/
│       ├── login.php
│       └── register.php
├── assets/
│   ├── css/
│   │   └── style.css
│   └── js/
│       ├── app.js
│       ├── auth.js
│       └── dashboard.js
├── classes/
│   ├── Database.php
│   ├── Auth.php
│   └── JWT.php
├── config/
│   └── config.php
├── database/
│   └── schema.sql
├── uploads/
└── logs/
```

### Step 2: Configurazione Database

1. **Accedi a phpMyAdmin** dal tuo hosting
2. **Crea un nuovo database** chiamato `festalaurea_db`
3. **Importa** il file `database/schema.sql`
4. **Crea un utente MySQL** con tutti i privilegi sul database

### Step 3: Configurazione Environment

1. **Copia** `.env.example` in `.env`
2. **Modifica** `.env` con i tuoi dati:

```bash
# Database (inserisci i tuoi dati)
DB_HOST=localhost
DB_NAME=tuonome_festalaurea_db
DB_USER=tuonome_dbuser
DB_PASS=tua_password_sicura

# Email (per SiteGround)
SMTP_HOST=mail.festalaurea.eu
SMTP_USERNAME=noreply@festalaurea.eu
SMTP_PASSWORD=tua_password_email

# Stripe (registrati su stripe.com)
STRIPE_PUBLISHABLE_KEY=pk_test_tuachiave
STRIPE_SECRET_KEY=sk_test_tuachiave

# Google OAuth (da console.cloud.google.com)
GOOGLE_CLIENT_ID=tuoid.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=tuosegreto

# Facebook OAuth (da developers.facebook.com)
FACEBOOK_APP_ID=tuoappid
FACEBOOK_APP_SECRET=tuosegreto
```

### Step 4: Configurazione API Keys

#### Stripe (Pagamenti)
1. Vai su [stripe.com](https://stripe.com) e registrati
2. Dashboard → Developers → API Keys
3. Copia le chiavi test nel file `.env`

#### Google OAuth
1. Vai su [console.cloud.google.com](https://console.cloud.google.com)
2. Crea nuovo progetto → API e servizi → Credenziali
3. Crea credenziali OAuth 2.0
4. Aggiungi URI di reindirizzamento: `https://festalaurea.eu/api/auth/google-callback`

#### Facebook OAuth
1. Vai su [developers.facebook.com](https://developers.facebook.com)
2. Crea nuova app → Tipo: Consumer
3. Aggiungi prodotto "Facebook Login"
4. Imposta URI di reindirizzamento OAuth

### Step 5: Upload su SiteGround

#### Via File Manager (cPanel)
1. Accedi a **Site Tools** di SiteGround
2. Vai a **File Manager**
3. Naviga in `public_html`
4. **Upload** tutti i file mantenendo la struttura
5. Imposta permessi:
   - Cartelle: 755
   - File PHP: 644
   - Cartella uploads: 777

#### Via FTP
```bash
# Connetti via FTP
ftp ftp.festalaurea.eu
# Username e password dal tuo hosting

# Upload tutti i file
put -r festalaurea/* /public_html/

# Imposta permessi
chmod -R 755 /public_html/
chmod -R 777 /public_html/uploads/
```

### Step 6: Configurazione Email

1. **cPanel** → **Email Accounts**
2. Crea: `noreply@festalaurea.eu`
3. Annota la password
4. Aggiorna `.env` con le credenziali

### Step 7: Test Iniziale

1. Visita `https://festalaurea.eu`
2. Clicca su "Registrati"
3. Crea un account di test
4. Verifica che ricevi l'email di conferma
5. Accedi alla dashboard

## 🔧 Configurazioni Avanzate

### SSL/HTTPS
SiteGround include Let's Encrypt gratuito:
1. Site Tools → Security → SSL Manager
2. Installa Let's Encrypt
3. Forza HTTPS nel `.htaccess`

### Cron Jobs (per email automatiche)
```bash
# Invia promemoria 24h prima dell'evento
0 10 * * * /usr/bin/php /home/user/public_html/cron/send-reminders.php

# Pulizia sessioni scadute
0 3 * * * /usr/bin/php /home/user/public_html/cron/cleanup.php
```

### Backup Automatici
SiteGround include backup giornalieri, ma puoi configurare extra:
```bash
# Backup database giornaliero
mysqldump -u user -p festalaurea_db > backup_$(date +%Y%m%d).sql
```

## 📱 Test Mobile
1. Apri Chrome DevTools (F12)
2. Toggle device toolbar (Ctrl+Shift+M)
3. Testa su varie dimensioni schermo

## 🐛 Troubleshooting

### Errore 500
- Controlla `.htaccess` syntax
- Verifica permessi file
- Controlla error log: `logs/error.log`

### Database connection failed
- Verifica credenziali in `.env`
- Controlla che utente MySQL sia assegnato al database
- Verifica host (potrebbe essere diverso da localhost)

### Email non inviate
- Verifica credenziali SMTP
- Controlla che porta 587 sia aperta
- Prova con webmail per verificare account

### Login social non funziona
- Verifica API keys corrette
- Controlla URL callback configurati
- Assicurati HTTPS sia attivo

## 📈 Monitoraggio

### Google Analytics
1. Crea property su analytics.google.com
2. Aggiungi tracking code in `index.html`:
```html
<script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
```

### Performance
- GTmetrix: https://gtmetrix.com
- PageSpeed: https://pagespeed.web.dev
- Uptime: https://uptimerobot.com

## 🔒 Sicurezza

### Checklist Sicurezza
- [ ] Cambia password admin default
- [ ] Aggiorna JWT_SECRET in .env
- [ ] Abilita HTTPS ovunque
- [ ] Configura firewall SiteGround
- [ ] Abilita 2FA per account hosting
- [ ] Regular security scan con Sucuri

### Headers Sicurezza
Già configurati in `.htaccess`:
- X-Frame-Options
- X-Content-Type-Options  
- X-XSS-Protection
- Referrer-Policy

## 📊 Database Schema

### Tabelle Principali
- `users` - Utenti (studenti, locali, admin)
- `venues` - Locali/Ristoranti
- `bookings` - Prenotazioni
- `payments` - Pagamenti
- `reviews` - Recensioni
- `guests` - Invitati alle feste
- `messages` - Messaggi interni
- `notifications` - Notifiche

## 🎨 Personalizzazione

### Colori Brand
Modifica in `assets/css/style.css`:
```css
:root {
    --primary: #6366f1;     /* Colore principale */
    --secondary: #f59e0b;   /* Colore secondario */
    --success: #10b981;     /* Successo/Conferma */
    --danger: #ef4444;      /* Errore/Cancella */
}
```

### Logo
Sostituisci in:
- `assets/images/logo.png`
- Favicon: `favicon.ico`

## 🚀 Deployment Checklist

- [ ] Database importato e funzionante
- [ ] File .env configurato con dati produzione
- [ ] HTTPS attivo e forzato
- [ ] Email SMTP configurate e testate
- [ ] Stripe in modalità live (non test)
- [ ] Google/Facebook OAuth configurati
- [ ] Backup automatici attivi
- [ ] Monitoring attivo
- [ ] DEBUG_MODE = false in produzione
- [ ] Error reporting disabilitato
- [ ] Cache abilitata
- [ ] CDN configurata (Cloudflare)

## 📞 Supporto

### Documentazione
- PHP: https://www.php.net/manual
- MySQL: https://dev.mysql.com/doc
- Stripe: https://stripe.com/docs
- Bootstrap: https://getbootstrap.com/docs

### Contatti SiteGround
- Chat Live: 24/7
- Ticket: Area clienti
- Knowledge Base: https://www.siteground.com/kb

## 📄 Licenza
Copyright © 2024 FestaLaurea. Tutti i diritti riservati.

---

**Sviluppato con ❤️ per i laureandi italiani**
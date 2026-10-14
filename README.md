# Lukida Essential — Installationsanleitung

> Getestet auf esx-32.gbv.de (Debian/Ubuntu, Apache 2.4, PHP 8.4, PostgreSQL 16)  
> Stand: Oktober 2026

Lukida Essential ist die kostenlose Open-Source-Version von Lukida Pro. Folgende Features sind nur in der Pro-Version enthalten:

- **Admin-Modul** � Verwaltungsoberfl�che f�r Administratoren
- **Hosting-Modul** � Multi-Mandanten-Betrieb mit mehreren Datenbanken
- **Push-Nachrichten** � Benachrichtigungen nach dem Login
- **Wallet, Bausteine, Fragen** � Erweiterte Benutzer-Module
- **PDF-Export** � Drucken und Exportieren via MPDF
- **Klassifikationssysteme** � Suche in Normdaten und Klassifikationen
- **Server-seitige Favoriten/Suchen/Einstellungen** � In Essential werden diese nur lokal im Browser gespeichert
- **Vereinfachte Authentifizierung** � Essential verwendet API-Key-Auth statt JWT-Tokens
- **Rate-Limiting / Brute-Force-Schutz** � In Essential nicht enthalten

---

> **Hinweis zu Pfaden:** Alle Pfade mit `/home/<benutzer>/` sind Beispiele und müssen durch den tatsächlichen Benutzerpfad ersetzt werden (z. B. `/home/ali/` oder `/var/www/`). Entscheidend ist nur die **relative Struktur**: `configs/` muss immer eine Ebene **über** dem Repo-Verzeichnis liegen.

---

## 1. Voraussetzungen

| Komponente | Version |
|---|---|
| PHP | 8.4 |
| PostgreSQL | 16 |
| Apache | 2.4 (mit mod_rewrite, mod_proxy, mod_proxy_http) |
| Node.js | 20+ (nur für UI-Build) |
| Git | beliebig |

---

## 2. Repository klonen

```bash
cd /home/<benutzer>
git clone https://github.com/Ali-afk-dot/lukida-essential lukida-essential
```

**Wichtig — Verzeichnisstruktur:** `main.php` sucht Konfigurationsdateien immer
im Ordner `configs/` **eine Ebene über** dem Repo. Liegt das Repo in
`/home/<benutzer>/lukida-essential/`, muss der Configs-Ordner in `/home/<benutzer>/configs/`
liegen — der Name `configs` ist fest vorgegeben.

---

## 3. Config-Verzeichnis anlegen

```bash
mkdir -p /home/<benutzer>/configs/production
```

### 3.1 `application.ini`

Datei: `/home/<benutzer>/configs/application.ini`

```ini
[routes]
POST /@api/@module/@action=@module->@action

[application]
name         = Lukida Essential
type         = production
apikey       = <32-stelliger-hex-api-key>
path         = /home/<benutzer>/lukida-essential/5/
versionmain  = 5
startyear    = <startjahr>

[author]
name         = <name>
mail         = <email>
company      = <organisation>
homepage     = <homepage>
pepper       = lukida-essential-2026
```

### 3.2 `production/database.ini`

Datei: `/home/<benutzer>/configs/production/database.ini`

```ini
[db]
driver=pgsql
database=lukida_essential

[module]

[security]
app=token
user=none
```

### 3.3 `production/user.ini`

Datei: `/home/<benutzer>/configs/production/user.ini`

```ini
[db]
driver=pgsql
host=localhost
name=lukida_essential
user=lukida_essential
pass=<passwort>

[smtp]
host=
auth=false
user=
pass=
port=587
from=<absender-email>
fromname=Lukida Essential

[appdebug]
sqq=true
sqd=true
```

---

## 4. Logs- und Caches-Verzeichnisse anlegen

Die API schreibt Logs und Caches in den `configs/`-Ordner. Apache läuft als
`www-data` und braucht Schreibrechte:

```bash
mkdir -p /home/<benutzer>/configs/logs
mkdir -p /home/<benutzer>/configs/caches
sudo chown -R www-data:www-data /home/<benutzer>/configs/logs
sudo chown -R www-data:www-data /home/<benutzer>/configs/caches
```

---

## 5. Datenbank anlegen

### 5.1 DB und User anlegen

```bash
sudo -u postgres psql
```

```sql
CREATE USER lukida_essential WITH PASSWORD '<passwort>';
CREATE DATABASE lukida_essential OWNER lukida_essential;
\q
```

### 5.2 Schema und Demo-Daten einspielen

Die SQL-Dateien liegen im Repo unter `lukida-essential/sql/`:
- `schema.sql` — erstellt alle Tabellen
- `demo-data.sql` — füllt die DB mit Testdaten

```bash
psql -h localhost -U lukida_essential -d lukida_essential \
  -f /home/<benutzer>/lukida-essential/sql/schema.sql
psql -h localhost -U lukida_essential -d lukida_essential \
  -f /home/<benutzer>/lukida-essential/sql/demo-data.sql
```

Alternativ über **Adminer**:
1. Einloggen: System = PostgreSQL, Server = localhost, User = lukida_essential,
   Passwort = `<passwort>`, Datenbank = lukida_essential
2. SQL-Dateien nacheinander über „SQL-Befehl ausführen" einspielen

---

## 6. Vue-UI bauen

```bash
cd /home/<benutzer>/lukida-essential/ui
npm install
npm run build
```

Das erzeugt `ui/dist/` — das Verzeichnis das Apache für die UI ausliefert.

---

## 7. Apache konfigurieren

### 7.1 Apache-Module aktivieren

```bash
sudo a2enmod rewrite proxy proxy_http
sudo systemctl restart apache2
```

`mod_proxy` und `mod_proxy_http` sind zwingend erforderlich damit die UI
API-Requests weiterleiten kann.

### 7.2 VirtualHosts hinzufügen

In `/etc/apache2/sites-enabled/lukida.conf` anhängen (Ports anpassen falls belegt):

```apache
Listen 9055
<VirtualHost *:9055>
    ServerName    "LukidaEssential"
    SetEnv MODULE "user"
    SetEnv CLIENT "Essential"
    SetEnv MODE   "production"
    DocumentRoot  "/home/<benutzer>/lukida-essential/"
    <Directory    "/home/<benutzer>/lukida-essential/">
        DirectoryIndex index.php
        AllowOverride All
        Require all granted
        RewriteEngine On
        RewriteBase /
        RewriteRule ^\.htaccess$ - [F]
        RewriteRule ^.*\.ini$ - [F]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_URI} !robots.txt
        RewriteRule ^(.*)$ index.php?/$1 [L]
    </Directory>
</VirtualHost>

Listen 9056
<VirtualHost *:9056>
    ServerName    "LukidaEssentialUI"
    DocumentRoot  "/home/<benutzer>/lukida-essential/ui/dist/"
    <Directory    "/home/<benutzer>/lukida-essential/ui/dist/">
        Options -Indexes +FollowSymLinks
        AllowOverride None
        Require all granted
        FallbackResource /index.html
    </Directory>
    ProxyPass        /api http://localhost:9055
    ProxyPassReverse /api http://localhost:9055
</VirtualHost>
```

**Warum zwei VirtualHosts?**
- Port **9055** — PHP-API. `SetEnv` setzt MODE/MODULE/CLIENT — funktioniert nur
  über Apache, nicht mit `php -S`.
- Port **9056** — Vue-UI (statische Dateien). `ProxyPass /api → 9055` leitet
  API-Requests weiter. Im Dev-Modus (`npm run dev`) macht das Vite selbst;
  im Build muss Apache es übernehmen.

### 7.3 Apache neu laden

```bash
sudo apache2ctl configtest
sudo systemctl reload apache2
```

---

## 8. Testen

| URL | Was |
|---|---|
| `http://<server>:9055/api/user/search?q=test` | API direkt (GET → Fehlermeldung normal, POST erwartet) |
| `http://<server>:9056` | Vue-UI |

Im Browser Port 9056 öffnen → Suchbegriff eingeben → Treffer erscheinen.

---

## 9. Verzeichnisstruktur (Zusammenfassung)

```
/home/<benutzer>/
├── lukida-essential/            ← Git-Repo (Clone von GitHub)
│   ├── 5/                       ← PHP-API (appl/ + base/)
│   ├── ui/                      ← Vue 3 Frontend
│   │   └── dist/                ← Build-Output (nach npm run build)
│   ├── sql/                     ← schema.sql + demo-data.sql
│   ├── index.php
│   └── main.php
│
└── configs/                     ← Configs (AUSSERHALB des Repos, Name ist fest!)
    ├── application.ini
    ├── logs/                    ← Eigentümer: www-data
    ├── caches/                  ← Eigentümer: www-data
    └── production/
        ├── database.ini
        └── user.ini
```

---

## 10. Häufige Fehler

| Symptom | Ursache | Lösung |
|---|---|---|
| `configs directory missing!` | Configs-Ordner heißt nicht `configs` | Ordner muss exakt `configs` heißen, eine Ebene über dem Repo |
| `MODE` undefined / leere API-Antwort | `php -S` statt Apache | Apache VirtualHost mit `SetEnv` verwenden |
| 500 bei `/api/...` aus der UI | `mod_proxy_http` nicht aktiv | `sudo a2enmod proxy proxy_http` |
| `Failed to open stream: Keine Berechtigung` | logs/caches nicht www-data | `sudo chown -R www-data:www-data /home/<benutzer>/configs/logs` |
| UI lädt, Suche liefert nichts | ProxyPass fehlt | `ProxyPass /api http://localhost:9055` im Port-9056-Block |
| `5/` leer nach git clone | Submodule-Problem | `git rm --cached 5`, `.git` in `5/` entfernen, neu hinzufügen |

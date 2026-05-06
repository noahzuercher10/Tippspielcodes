# tippspiel_projekt_blj
Ein Tippspiel das wir fuer die Projektphase des ZbW erstellen.
# Hajde be big Sinan

---

## Inhalt der Codebase

```
Tippspielcodes/
├── index.php            Login-Seite
├── register.php         Registrierungs-Seite
├── config/db.php        PDO-Connection (HOST/USER/PASS hier anpassen)
├── includes/
│   ├── auth.php         Login / Logout / Session
│   ├── functions.php    Punkteberechnung + Geldlogik
│   ├── header.php       gemeinsamer Seitenkopf (Profil, Modus, Nav)
│   └── footer.php
├── api/                 alle PHP-Endpunkte (JSON)
│   ├── me.php           Daten des eingeloggten Users
│   ├── sports.php       Liste der Sportarten
│   ├── leagues.php      Ligen einer Sportart
│   ├── matches.php      Spiele eines Tages mit eigenem Tipp
│   ├── bets.php         Tipp speichern (Punkte- oder Geld-Modus)
│   ├── groups.php       Gruppen anlegen / beitreten / verlassen / Detail
│   ├── leaderboard.php  globale Rangliste
│   ├── admin.php        Admin-Aktionen (Sportart, Liga, Team, Spiel, Resultat)
│   └── logout.php
├── pages/               UI-Seiten
│   ├── home.php
│   ├── groups.php
│   ├── leaderboard.php
│   ├── sports.php       (Sportart -> Liga -> Tag -> Spiele tippen)
│   ├── profile.php
│   └── admin.php
├── css/style.css        globales Stylesheet (dunkles Theme)
├── js/
│   ├── app.js           Helper (fetch, Modus-Dropdown, Toasts)
│   ├── sports.js
│   ├── groups.js
│   └── admin.js
└── sql/tippspiel.sql    DB-Schema + Beispieldaten
```

---

## Setup mit XAMPP (Windows)

### 1. XAMPP installieren
Lade XAMPP von <https://www.apachefriends.org> und installiere es.
Starte im XAMPP Control Panel **Apache** und **MySQL**.

### 2. Projekt in den Webserver legen
Kopiere den ganzen Ordner `Tippspielcodes` nach
`C:\xampp\htdocs\` und benenne ihn in **`tippspiel`** um:

```
C:\xampp\htdocs\tippspiel\
```

(Wenn du einen anderen Ordnernamen wahlst, musst du in den
PHP/JS-Dateien die Pfade `/tippspiel/...` anpassen.)

### 3. Datenbank importieren
1. Oeffne <http://localhost/phpmyadmin>
2. Klicke links auf **"Neu"** und erstelle die Datenbank
   `tippspiel` mit Kollation `utf8mb4_unicode_ci` *(optional, das Skript
   legt sie selber an)*.
3. Klicke auf den Reiter **"Importieren"**.
4. Waehle die Datei `sql/tippspiel.sql` und klicke auf **"OK"**.

### 4. DB-Zugang konfigurieren
Standardmaessig ist im XAMPP der MySQL-Login `root` ohne Passwort.
Falls dein Setup anders ist, oeffne `config/db.php` und passe an:

```php
const DB_HOST = '127.0.0.1';
const DB_NAME = 'tippspiel';
const DB_USER = 'root';
const DB_PASS = '';
```

### 5. App im Browser oeffnen
Gehe auf <http://localhost/tippspiel/>.
Mit den Beispiel-Logins kannst du sofort starten:

| Username | Passwort  | Rolle  |
| -------- | --------- | ------ |
| admin    | admin123  | admin  |
| noah     | admin123  | user   |
| sinan    | admin123  | user   |

> Hinweis: alle Beispiel-Konten haben das gleiche bcrypt-Passwort
> `admin123`. Nach dem ersten Login bitte aendern.

---

## Verbindung zur DB im Detail

Die App nutzt **PDO** mit prepared statements. Verbindungsdaten stehen
ausschliesslich in `config/db.php`. Jede PHP-Datei holt sich die
Connection ueber den globalen Helper `db()`:

```php
require_once __DIR__ . '/config/db.php';
$stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
```

So musst du DB-Zugangsdaten nur an *einer* Stelle anpassen.

---

## Spielmodi (laut Doku)

* **Punktemodus**
  * Genau richtiger Tipp = 10 Punkte
  * Richtiger Sieger / Unentschieden = 5 Punkte
  * Richtige Anzahl Heimtore = 1 Punkt
  * Richtige Anzahl Auswaertstore = 1 Punkt
  * Richtige Tordifferenz (nur bei richtigem Sieger-Tipp) = 3 Punkte
* **Geldmodus**
  * Falscher Tipp -> Einsatz weg.
  * Genau richtiger Tipp -> Einsatz wird verdoppelt.
  * Maximaler Einsatz pro Tipp = 25 % des aktuellen Guthabens (mind. 10).

---

## Rollen

* **user** - kann Tipps abgeben, Gruppen erstellen (wird dabei Gruppen-Admin),
  Gruppen beitreten via Beitrittscode, Rangliste sehen.
* **admin** - alles, plus: Sportarten, Ligen, Teams, Spiele anlegen,
  Resultate eintragen (loest automatisch Punkte- und Geldverteilung aus),
  User loeschen.

---

## Bekannte To-dos / mögliche Erweiterungen

* API-Anbindung an externen Spielplan (siehe Doku - "API-Informieren")
* Push-Benachrichtigungen bei ausstehenden Tipps
* Profilbild-Upload
* Pott-Modus (10.- pro Tipp -> Tagespott aufteilen)
* Mehrsprachigkeit / Dark-Light-Mode-Toggle
* Chat unter Tippern

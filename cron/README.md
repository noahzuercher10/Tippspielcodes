# Automatischer Sync (Cron)

## Welche API liefert was?

| Sportart    | Quelle                            | Liga-Abdeckung                                          |
|-------------|-----------------------------------|---------------------------------------------------------|
| Fussball    | TheSportsDB                       | Super League CH, BL, PL, La Liga, Serie A, Champions L. |
| Eishockey   | TheSportsDB                       | NHL, Schweizer National League                          |
| Basketball  | TheSportsDB                       | NBA, EuroLeague                                         |
| Tennis      | TheSportsDB                       | ATP Tour                                                |
| Formel 1    | **Jolpica Ergast** (api.jolpi.ca) | komplette F1-Saison + Resultate                         |

Die F1-Klasse ueberschreibt `syncLeague()` und nutzt eine andere API als alle
anderen Sportarten - das ist die Vererbungs-Architektur in Aktion.

## Erstbefuellung (EINMALIG, vor allem  )

Doppelklick auf **`sync_full.bat`** - das holt den kompletten Spielplan
ALLER Ligen bis 10.08.2026 in die Datenbank. Dauert je nach Internet
1-3 Minuten und schreibt das Ergebnis ins Konsolenfenster.

## Taegliche Updates 07:00 + 15:00

Damit jeden Morgen (07:00) und Nachmittag (15:00) Resultate vergangener
Spiele eingetragen und neue Spiele dazukommen, muss `sync_all.bat`
zweimal pro Tag automatisch laufen.

### Einrichten (einmalig im Windows Aufgabenplaner)

1. Windows-Suche: "Aufgabenplanung" oeffnen
2. Rechte Spalte: "Aufgabe erstellen..."
3. Reiter **Allgemein**:
   - Name: `Tippspiel Sync`
   - "Unabhaengig von der Benutzeranmeldung ausfuehren"
4. Reiter **Trigger** -> **Neu** (zwei Trigger anlegen):
   - Trigger 1: Taeglich, Beginn 07:00
   - Trigger 2: Taeglich, Beginn 15:00
5. Reiter **Aktionen** -> **Neu**:
   - Programm: `C:\xampp\htdocs\Tippspiel\cron\sync_all.bat`
6. Speichern

### Logfile

`C:\xampp\htdocs\Tippspiel\cron\sync.log`

## Was beim Sync passiert

Pro Liga:
1. Komplette Saison von der API holen (mehrere Endpoints kombiniert)
2. Vergangene Tage filtern (`<= 10.08.2026`)
3. Vergangene Spiele NUR aktualisieren wenn sie schon in der DB sind
   (Resultat eintragen, Tipps automatisch auswerten -> Punkte / Geld
   werden auf User und Gruppen verbucht)
4. Neue zukuenftige Spiele einfuegen

# Disconnection Umfrage Tool

Ein einfaches Umfrage-System, das mit PHP, MySQL, AJAX und Bootstrap funktioniert.  
Es bietet dynamische Echtzeit-Aktualisierung von Umfrageergebnissen, ein Admin-Backend zum Verwalten von Umfragen und flexible Optionen-Verwaltung.

## Inhaltsverzeichnis

- [Funktionen](#funktionen)
- [Verwendete Technologien](#verwendete-technologien)
- [Installation](#installation)

---

## Funktionen

- **Benutzer-Umfragen**: Besucher können an Umfragen teilnehmen und sehen die Ergebnisse live aktualisiert.
- **Admin-Backend**: Sichere Login-Funktion, um neue Umfragen zu erstellen, bestehende zu bearbeiten oder zu löschen.
- **Dynamische Optionsverwaltung**: Administratoren können Umfrageoptionen (Antworten) dynamisch hinzufügen oder entfernen, mit einer Mindestanzahl von zwei nicht-leeren Optionen.
- **Archivierung**: Administratoren können Umfragen archivieren, um sie vor der Öffentlichkeit zu verbergen, ohne sie zu löschen. Archivierte Umfragen können jederzeit wiederhergestellt werden.

---

## Verwendete Technologien

- **Backend**: PHP, MySQL
- **Frontend**: HTML, CSS, JavaScript, jQuery, Bootstrap 5

---

## Installation

### 1. Repository klonen

```bash
git clone https://github.com/jonasradke/discopoll
cd discopoll
```

### 2. Datenbank einrichten

Erstelle eine MySQL-Datenbank, zum Beispiel `pollvotes`

```
CREATE TABLE IF NOT EXISTS polls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    archived TINYINT(1) NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS poll_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT NOT NULL,
    option_text VARCHAR(255) NOT NULL,
    FOREIGN KEY (poll_id) REFERENCES polls(id)
);

CREATE TABLE IF NOT EXISTS poll_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT NOT NULL,
    option_id INT NOT NULL,
    voted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (poll_id) REFERENCES polls(id),
    FOREIGN KEY (option_id) REFERENCES poll_options(id)
);
```

Lege ein admin user und passwort fest. Hier ein Beispiel mit User: admin und Passwort: admin. Hier ein hashing tool https://bcrypt.online/

### 3. Archivierung aktivieren (für bestehende Installationen)

Falls du bereits eine bestehende Installation hast, führe folgende SQL-Befehle aus, um die Archivierungsfunktion zu aktivieren:

```sql
ALTER TABLE polls ADD COLUMN archived TINYINT(1) NOT NULL DEFAULT 0;
CREATE INDEX idx_polls_archived ON polls(archived);
```

## Archivierung

Das System unterstützt jetzt die Archivierung von Umfragen:

- **Archivieren**: Umfragen können archiviert werden, um sie vor der Öffentlichkeit zu verbergen
- **Wiederherstellen**: Archivierte Umfragen können jederzeit wiederhergestellt werden
- **Separate Ansicht**: Administratoren können archivierte Umfragen in einer separaten Ansicht verwalten
- **Sicherheit**: Archivierte Umfragen sind nicht für Abstimmungen verfügbar und werden nicht in der öffentlichen Ansicht angezeigt

```
INSERT INTO `admin_users` (`id`, `username`, `password_hash`) VALUES
(3, 'admin', '$2y$10$gnFPoXkf5WWYPtQCiRomKexrJsj3LmQY/.WcPVaS4Qoj8DKieQiM6');
```

### 3. Config

Öffne `config.php` und passe die Datenbank-Zugangsdaten an:

```
$host   = 'localhost';
$dbName = 'pollvotes';
$user   = 'db_benutzer';
$pass   = 'db_passwort';
```

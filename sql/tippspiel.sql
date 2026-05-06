-- ===========================================================
-- Tippspiel-App (ZbW Projekt 2608)
-- Datenbank-Schema fuer MySQL / MariaDB
-- ===========================================================
-- Import via phpMyAdmin:
--   1. Datenbank "tippspiel" anlegen (utf8mb4_unicode_ci)
--   2. Diese Datei importieren
-- ===========================================================

DROP DATABASE IF EXISTS tippspiel;
CREATE DATABASE tippspiel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tippspiel;

-- -----------------------------------------------------------
-- Benutzer
-- -----------------------------------------------------------
CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50)  NOT NULL UNIQUE,
    email           VARCHAR(120) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    first_name      VARCHAR(60)  NOT NULL,
    last_name       VARCHAR(60)  NOT NULL,
    role            ENUM('user','admin') NOT NULL DEFAULT 'user',
    points_total    INT NOT NULL DEFAULT 0,
    money_balance   DECIMAL(12,2) NOT NULL DEFAULT 1000.00, -- imaginaeres Startgeld
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- Sportarten
-- -----------------------------------------------------------
CREATE TABLE sports (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(60) NOT NULL UNIQUE,
    type        ENUM('team','single') NOT NULL DEFAULT 'team',
    icon        VARCHAR(80) DEFAULT NULL
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- Ligen / Wettbewerbe / Turniere
-- -----------------------------------------------------------
CREATE TABLE leagues (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    sport_id    INT NOT NULL,
    name        VARCHAR(120) NOT NULL,
    season      VARCHAR(20)  DEFAULT NULL,
    FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- Teams / Athleten
-- -----------------------------------------------------------
CREATE TABLE teams (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    sport_id    INT NOT NULL,
    name        VARCHAR(120) NOT NULL,
    short_name  VARCHAR(10)  DEFAULT NULL,
    FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- Spiele
-- -----------------------------------------------------------
CREATE TABLE matches (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    league_id       INT NOT NULL,
    home_team_id    INT NOT NULL,
    away_team_id    INT NOT NULL,
    match_datetime  DATETIME NOT NULL,
    home_score      INT DEFAULT NULL,
    away_score      INT DEFAULT NULL,
    status          ENUM('upcoming','finished','cancelled') NOT NULL DEFAULT 'upcoming',
    FOREIGN KEY (league_id)    REFERENCES leagues(id) ON DELETE CASCADE,
    FOREIGN KEY (home_team_id) REFERENCES teams(id),
    FOREIGN KEY (away_team_id) REFERENCES teams(id)
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- Gruppen (von Usern erstellt)
-- -----------------------------------------------------------
CREATE TABLE groups_t (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(80)  NOT NULL,
    join_code   VARCHAR(20)  NOT NULL UNIQUE,
    mode        ENUM('points','money') NOT NULL DEFAULT 'points',
    league_id   INT NOT NULL,
    admin_id    INT NOT NULL, -- Gruppen-Admin (User der erstellt hat)
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (league_id) REFERENCES leagues(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id)  REFERENCES users(id)
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- Gruppen-Mitgliedschaften
-- -----------------------------------------------------------
CREATE TABLE group_members (
    group_id    INT NOT NULL,
    user_id     INT NOT NULL,
    points      INT NOT NULL DEFAULT 0,
    money       DECIMAL(12,2) NOT NULL DEFAULT 1000.00,
    joined_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (group_id, user_id),
    FOREIGN KEY (group_id) REFERENCES groups_t(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- Tipps
-- -----------------------------------------------------------
CREATE TABLE bets (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    match_id        INT NOT NULL,
    group_id        INT DEFAULT NULL,                -- Tipp innerhalb einer Gruppe
    mode            ENUM('points','money') NOT NULL,
    tip_home        INT NOT NULL,
    tip_away        INT NOT NULL,
    stake           DECIMAL(12,2) DEFAULT 0.00,      -- nur Geldmodus
    points_earned   INT DEFAULT 0,
    money_earned    DECIMAL(12,2) DEFAULT 0.00,
    evaluated       TINYINT(1) DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_match_group_mode (user_id, match_id, group_id, mode),
    FOREIGN KEY (user_id)  REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (match_id) REFERENCES matches(id)  ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES groups_t(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- Beispiel-Daten
-- -----------------------------------------------------------
-- Default Admin-Account: admin / admin123
INSERT INTO users (username,email,password_hash,first_name,last_name,role,money_balance) VALUES
 ('admin','admin@tippspiel.local', '$2y$10$wH5x3eYpZbQeV8oAIQk2xeyZk8zvDfEJ5DkDlFzj1fE6qw0oZGq2u', 'Admin','User','admin',1000.00),
 ('noah','noah@example.com',       '$2y$10$wH5x3eYpZbQeV8oAIQk2xeyZk8zvDfEJ5DkDlFzj1fE6qw0oZGq2u', 'Noah','Zuercher','user',1000.00),
 ('sinan','sinan@example.com',     '$2y$10$wH5x3eYpZbQeV8oAIQk2xeyZk8zvDfEJ5DkDlFzj1fE6qw0oZGq2u', 'Sinan','Boss','user',1000.00);

INSERT INTO sports (name,type) VALUES
 ('Fussball','team'),
 ('Eishockey','team'),
 ('Basketball','team'),
 ('Tennis','single'),
 ('Formel 1','single');

INSERT INTO leagues (sport_id,name,season) VALUES
 (1,'Super League','2025/26'),
 (1,'WM 2026','2026'),
 (2,'National League','2025/26'),
 (3,'NBA','2025/26'),
 (4,'ATP Tour','2026'),
 (5,'Formel 1 Saison','2026');

INSERT INTO teams (sport_id,name,short_name) VALUES
 (1,'FC Basel','BAS'),(1,'FC Zuerich','FCZ'),(1,'BSC Young Boys','YB'),(1,'FC St. Gallen','FCSG'),
 (1,'Schweiz','SUI'),(1,'Deutschland','GER'),(1,'Italien','ITA'),(1,'Frankreich','FRA'),
 (2,'SC Bern','SCB'),(2,'ZSC Lions','ZSC'),(2,'EV Zug','EVZ'),(2,'HC Davos','HCD'),
 (3,'LA Lakers','LAL'),(3,'Boston Celtics','BOS');

INSERT INTO matches (league_id,home_team_id,away_team_id,match_datetime) VALUES
 (1, 1, 2, '2026-05-10 18:00:00'),
 (1, 3, 4, '2026-05-11 20:30:00'),
 (1, 1, 3, '2026-05-15 18:00:00'),
 (2, 5, 6, '2026-06-12 21:00:00'),
 (2, 7, 8, '2026-06-13 21:00:00'),
 (3, 9,10, '2026-05-12 19:45:00'),
 (4,13,14, '2026-05-14 02:00:00');

INSERT INTO groups_t (name,join_code,mode,league_id,admin_id) VALUES
 ('Big Sinan Crew','SINAN26','points', 1, 2),
 ('Cash Kings',    'MONEY01','money',  1, 3);

INSERT INTO group_members (group_id,user_id) VALUES
 (1,2),(1,3),(2,2),(2,3);

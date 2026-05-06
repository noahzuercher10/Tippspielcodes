-- ===========================================================
-- Tippspiel-App (ZbW Projekt 2608)
-- Datenbank-Schema fuer MySQL / MariaDB
-- ===========================================================

DROP DATABASE IF EXISTS tippspiel;
CREATE DATABASE tippspiel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tippspiel;

-- -----------------------------------------------------------
-- Benutzer
-- -----------------------------------------------------------
CREATE TABLE users (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    username          VARCHAR(50)  NOT NULL UNIQUE,
    email             VARCHAR(120) NOT NULL UNIQUE,
    password_hash     VARCHAR(255) NOT NULL,
    first_name        VARCHAR(60)  NOT NULL,
    last_name         VARCHAR(60)  NOT NULL,
    role              ENUM('user','admin') NOT NULL DEFAULT 'user',
    points_total      INT NOT NULL DEFAULT 0,
    money_balance     DECIMAL(12,2) NOT NULL DEFAULT 2500.00,  -- Startkapital
    background_image  VARCHAR(255) DEFAULT NULL,               -- Pfad rel. zu /img/backgrounds/
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- Sportarten (laut Doku, mit API-Bindung-tauglichen Sportarten)
-- -----------------------------------------------------------
CREATE TABLE sports (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    name   VARCHAR(60) NOT NULL UNIQUE,
    type   ENUM('team','single') NOT NULL DEFAULT 'team',
    icon   VARCHAR(80) DEFAULT NULL
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- Ligen / Wettbewerbe
-- -----------------------------------------------------------
CREATE TABLE leagues (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    sport_id  INT NOT NULL,
    name      VARCHAR(120) NOT NULL,
    season    VARCHAR(20)  DEFAULT NULL,
    api_id    VARCHAR(40)  DEFAULT NULL, -- ID der externen API (z.B. TheSportsDB)
    FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- Teams
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
    api_event_id    VARCHAR(40) DEFAULT NULL,
    FOREIGN KEY (league_id)    REFERENCES leagues(id) ON DELETE CASCADE,
    FOREIGN KEY (home_team_id) REFERENCES teams(id),
    FOREIGN KEY (away_team_id) REFERENCES teams(id)
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- Gruppen
-- -----------------------------------------------------------
CREATE TABLE groups_t (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(80)  NOT NULL,
    join_code   VARCHAR(20)  NOT NULL UNIQUE,
    mode        ENUM('points','money') NOT NULL DEFAULT 'points',
    league_id   INT NOT NULL,
    admin_id    INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (league_id) REFERENCES leagues(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id)  REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE group_members (
    group_id    INT NOT NULL,
    user_id     INT NOT NULL,
    points      INT NOT NULL DEFAULT 0,
    money       DECIMAL(12,2) NOT NULL DEFAULT 2500.00,
    joined_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (group_id, user_id),
    FOREIGN KEY (group_id) REFERENCES groups_t(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- Tipps
-- -----------------------------------------------------------
-- Punktemodus: tip_home / tip_away (exaktes Resultat)
-- Geldmodus  : tip_winner ('home','draw','away'), nur Sieger
-- -----------------------------------------------------------
CREATE TABLE bets (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    match_id        INT NOT NULL,
    group_id        INT DEFAULT NULL,
    mode            ENUM('points','money') NOT NULL,
    tip_home        INT DEFAULT NULL,
    tip_away        INT DEFAULT NULL,
    tip_winner      ENUM('home','draw','away') DEFAULT NULL,
    stake           DECIMAL(12,2) DEFAULT 0.00,
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
-- BEISPIELDATEN
-- Default-Passwort fuer alle Beispiel-Accounts: admin123
-- (Hash: $2y$10$wH5x3eYpZbQeV8oAIQk2xeyZk8zvDfEJ5DkDlFzj1fE6qw0oZGq2u)
-- -----------------------------------------------------------
INSERT INTO users (username,email,password_hash,first_name,last_name,role,money_balance) VALUES
 ('admin','admin@tippspiel.local','$2y$10$wH5x3eYpZbQeV8oAIQk2xeyZk8zvDfEJ5DkDlFzj1fE6qw0oZGq2u','Admin','User','admin', 2500.00),
 ('noah', 'noah@example.com',     '$2y$10$wH5x3eYpZbQeV8oAIQk2xeyZk8zvDfEJ5DkDlFzj1fE6qw0oZGq2u','Noah','Zuercher','user', 2500.00),
 ('sinan','sinan@example.com',    '$2y$10$wH5x3eYpZbQeV8oAIQk2xeyZk8zvDfEJ5DkDlFzj1fE6qw0oZGq2u','Sinan','Boss','user',     2500.00);


-- Sportarten laut Doku (API-bindefaehig)
INSERT INTO sports (name,type) VALUES
 ('Fussball','team'),
 ('Eishockey','team'),
 ('Basketball','team'),
 ('Tennis','single'),
 ('Formel 1','single');

-- Ligen/Turniere je Sportart
INSERT INTO leagues (sport_id,name,season,api_id) VALUES
 (1,'Schweizer Super League','2025/26','4344'),
 (1,'Bundesliga','2025/26','4331'),
 (1,'Premier League','2025/26','4328'),
 (1,'La Liga','2025/26','4335'),
 (1,'WM 2026','2026','4429'),
 (2,'National League','2025/26','4380'),
 (2,'NHL','2025/26','4380'),
 (3,'NBA','2025/26','4387'),
 (3,'EuroLeague','2025/26','4408'),
 (4,'ATP Tour','2026',NULL),
 (4,'Grand Slam','2026',NULL),
 (5,'Formel 1 Saison','2026','4370');

-- Teams
INSERT INTO teams (sport_id,name,short_name) VALUES
 -- Fussball Schweiz
 (1,'FC Basel','BAS'),(1,'FC Zuerich','FCZ'),(1,'BSC Young Boys','YB'),(1,'FC St. Gallen','FCSG'),
 (1,'FC Luzern','FCL'),(1,'Servette FC','SER'),
 -- Bundesliga
 (1,'Bayern Muenchen','FCB'),(1,'Borussia Dortmund','BVB'),(1,'RB Leipzig','RBL'),(1,'Bayer Leverkusen','B04'),
 -- Premier League
 (1,'Manchester City','MCI'),(1,'Liverpool','LIV'),(1,'Arsenal','ARS'),(1,'Chelsea','CHE'),
 -- La Liga
 (1,'Real Madrid','RMA'),(1,'FC Barcelona','BAR'),(1,'Atletico Madrid','ATM'),
 -- WM
 (1,'Schweiz','SUI'),(1,'Deutschland','GER'),(1,'Italien','ITA'),(1,'Frankreich','FRA'),
 (1,'Brasilien','BRA'),(1,'Argentinien','ARG'),(1,'England','ENG'),(1,'Spanien','ESP'),
 -- Eishockey
 (2,'SC Bern','SCB'),(2,'ZSC Lions','ZSC'),(2,'EV Zug','EVZ'),(2,'HC Davos','HCD'),
 (2,'Toronto Maple Leafs','TOR'),(2,'Boston Bruins','BOS'),(2,'Edmonton Oilers','EDM'),
 -- Basketball
 (3,'LA Lakers','LAL'),(3,'Boston Celtics','BOS'),(3,'Golden State Warriors','GSW'),(3,'Miami Heat','MIA'),
 (3,'Real Madrid (BB)','RMA'),(3,'FC Barcelona (BB)','BAR'),
 -- Tennis (Einzel)
 (4,'Roger Federer','FED'),(4,'Rafael Nadal','NAD'),(4,'Novak Djokovic','DJO'),(4,'Carlos Alcaraz','ALC'),
 (4,'Jannik Sinner','SIN'),(4,'Stefanos Tsitsipas','TSI'),
 -- Formel 1
 (5,'Max Verstappen','VER'),(5,'Lewis Hamilton','HAM'),(5,'Charles Leclerc','LEC'),(5,'Lando Norris','NOR'),
 (5,'George Russell','RUS'),(5,'Carlos Sainz','SAI');

-- Spiele (nur passend zur jeweiligen Sportart/Liga)
INSERT INTO matches (league_id,home_team_id,away_team_id,match_datetime) VALUES
 -- Super League
 (1, 1, 2, '2026-05-10 18:00:00'),
 (1, 3, 4, '2026-05-11 20:30:00'),
 (1, 1, 3, '2026-05-15 18:00:00'),
 (1, 5, 6, '2026-05-17 16:00:00'),
 -- Bundesliga
 (2, 7, 8, '2026-05-09 15:30:00'),
 (2, 9,10, '2026-05-10 18:30:00'),
 -- Premier League
 (3,11,12, '2026-05-12 21:00:00'),
 (3,13,14, '2026-05-13 21:00:00'),
 -- La Liga
 (4,15,16, '2026-05-14 22:00:00'),
 (4,17,15, '2026-05-19 22:00:00'),
 -- WM 2026
 (5,18,19, '2026-06-12 21:00:00'),
 (5,20,21, '2026-06-13 21:00:00'),
 (5,22,23, '2026-06-14 21:00:00'),
 (5,24,25, '2026-06-15 21:00:00'),
 -- National League (Eishockey CH) - Teams 26..29
 (6,26,27, '2026-05-12 19:45:00'),
 (6,28,29, '2026-05-13 19:45:00'),
 -- NHL - Teams 30..32
 (7,30,31, '2026-05-14 02:00:00'),
 (7,32,30, '2026-05-15 02:00:00'),
 -- NBA - Teams 33..36
 (8,33,34, '2026-05-12 03:00:00'),
 (8,35,36, '2026-05-13 03:00:00'),
 -- EuroLeague - Teams 37..38
 (9,37,38, '2026-05-15 20:00:00'),
 -- ATP Tour - Teams 39..42
 (10,39,40, '2026-05-11 14:00:00'),
 (10,41,42, '2026-05-12 14:00:00'),
 -- Grand Slam - Teams 43..44
 (11,43,44, '2026-05-25 14:00:00'),
 -- F1 - "Teams" sind Fahrer; Heim = Sieger-Slot
 (12,45,46, '2026-05-10 15:00:00'),
 (12,47,48, '2026-05-24 15:00:00'),
 (12,49,50, '2026-06-07 15:00:00');

-- Beispielgruppen
INSERT INTO groups_t (name,join_code,mode,league_id,admin_id) VALUES
 ('Big Sinan Crew','SINAN26','points', 1, 2),
 ('Cash Kings',    'MONEY01','money',  1, 3);

INSERT INTO group_members (group_id,user_id) VALUES
 (1,2),(1,3),
 (2,2),(2,3);

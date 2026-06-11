-- ===========================================================
-- Tippspiel – vollständiges DB-Schema v2
-- MySQL / MariaDB (utf8mb4)
-- Passwort für Beispiel-Accounts: admin123
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
    money_balance     DECIMAL(12,2) NOT NULL DEFAULT 2500.00,
    profile_picture   VARCHAR(255) DEFAULT NULL,
    theme             ENUM('light','dark') NOT NULL DEFAULT 'light',
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE sports (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(60) NOT NULL UNIQUE,
    type       ENUM('team','single') NOT NULL DEFAULT 'team',
    api_class  VARCHAR(60) NOT NULL,
    icon       VARCHAR(80) DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE leagues (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    sport_id   INT NOT NULL,
    name       VARCHAR(120) NOT NULL,
    season     VARCHAR(20)  DEFAULT NULL,
    api_id     VARCHAR(40)  DEFAULT NULL,
    last_sync  DATETIME     DEFAULT NULL,
    FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE matches (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    league_id       INT NOT NULL,
    home_name       VARCHAR(120) NOT NULL,
    away_name       VARCHAR(120) NOT NULL,
    home_short      VARCHAR(20)  DEFAULT NULL,
    away_short      VARCHAR(20)  DEFAULT NULL,
    match_datetime  DATETIME NOT NULL,
    home_score      INT DEFAULT NULL,
    away_score      INT DEFAULT NULL,
    status          ENUM('upcoming','finished','cancelled') NOT NULL DEFAULT 'upcoming',
    api_event_id    VARCHAR(40) DEFAULT NULL,
    home_badge      VARCHAR(255) DEFAULT NULL,
    away_badge      VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY uniq_api_event (api_event_id),
    INDEX idx_league_date (league_id, match_datetime),
    FOREIGN KEY (league_id) REFERENCES leagues(id) ON DELETE CASCADE
) ENGINE=InnoDB;

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
    extra_data      TEXT DEFAULT NULL,
    evaluated       TINYINT(1) DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_match_group_mode (user_id, match_id, group_id, mode),
    FOREIGN KEY (user_id)  REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (match_id) REFERENCES matches(id)  ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES groups_t(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Beispieldaten
INSERT INTO users (username,email,password_hash,first_name,last_name,role,money_balance) VALUES
 ('admin','admin@tippspiel.local','$2b$10$xeV8JrpyBXsVVkCCzoEPGeKNbTkuSgyeGQ8dRaYEOShi6GecOzTrG','Admin','User','admin', 2500.00),
 ('noah', 'noah@example.com',     '$2b$10$xeV8JrpyBXsVVkCCzoEPGeKNbTkuSgyeGQ8dRaYEOShi6GecOzTrG','Noah','Zuercher','user', 2500.00),
 ('sinan','sinan@example.com',    '$2b$10$xeV8JrpyBXsVVkCCzoEPGeKNbTkuSgyeGQ8dRaYEOShi6GecOzTrG','Sinan','Boss','user',     2500.00);

-- Sportarten (api_class = PHP-Klassenname unter includes/sports/)
INSERT INTO sports (name,type,api_class) VALUES
 ('Fussball',   'team',   'FootballSport'),
 ('Eishockey',  'team',   'IceHockeySport'),
 ('Basketball', 'team',   'BasketballSport'),
 ('Tennis',     'single', 'TennisSport'),
 ('Formel 1',   'single', 'Formula1Sport'),
 ('WM 2026',    'team',   'WorldCupSport');

-- Ligen mit echten TheSportsDB-Liga-IDs
INSERT INTO leagues (sport_id,name,season,api_id) VALUES
 -- Fussball
 (1,'Schweizer Super League','2025-2026','4344'),
 (1,'Bundesliga',             '2025-2026','4331'),
 (1,'Premier League',         '2025-2026','4328'),
 (1,'La Liga',                '2025-2026','4335'),
 (1,'Serie A',                '2025-2026','4332'),
 (1,'Champions League',       '2025-2026','4480'),
 -- Eishockey
 (2,'NHL',                    '2025-2026','4380'),
 (2,'Schweizer National League','2025-2026','4423'),
 -- Basketball
 (3,'NBA',                    '2025-2026','4387'),
 (3,'EuroLeague',             '2025-2026','4408'),
 -- Tennis: Grand Slams (search: prefix) + ATP Tour
 (4,'Australian Open',        '2026','search:Australian Open'),
 (4,'Roland Garros',          '2026','search:Roland Garros'),
 (4,'Wimbledon',              '2026','search:Wimbledon'),
 (4,'US Open',                '2026','search:US Open'),
 -- Formel 1
 (5,'Formel 1 Saison',        '2026','4370'),
 -- WM 2026 (FIFA World Cup – TheSportsDB Liga-ID 4453)
 (6,'FIFA WM 2026',           '2026','4453');

-- Gruppen
INSERT INTO groups_t (name,join_code,mode,league_id,admin_id) VALUES
 ('Big Sinan Crew','SINAN26','points', 1, 2),
 ('Cash Kings',    'MONEY01','money',  1, 3);

INSERT INTO group_members (group_id,user_id) VALUES
 (1,2),(1,3),
 (2,2),(2,3);

-- ===========================================================
-- Tippspiel Migration v2
-- Ausfuehren via phpMyAdmin oder mysql CLI:
--   USE tippspiel; SOURCE /path/to/migrate_v2.sql;
-- ===========================================================

-- Profilbild + Theme fuer User
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS theme ENUM('light','dark') NOT NULL DEFAULT 'light';

-- Team-Wappen / Spieler-Foto in Matches
ALTER TABLE matches
  ADD COLUMN IF NOT EXISTS home_badge VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS away_badge VARCHAR(255) DEFAULT NULL;

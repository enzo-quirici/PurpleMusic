-- ==========================================================
--  Purple Music / Amethyst Music — Migration 1 : Albums
--  À exécuter une seule fois sur une base existante :
--    mysql --default-character-set=utf8mb4 -u root -p purple_music < update-db/update-1.sql
--
--  Nécessite MySQL 8.0.29+ ou MariaDB 10.5.2+ (support de
--  "ADD COLUMN IF NOT EXISTS"). Sur une version plus ancienne,
--  retirez le "IF NOT EXISTS" de l'ALTER TABLE ci-dessous après
--  avoir vérifié que la colonne `album_id` n'existe pas déjà.
-- ==========================================================

USE purple_music;

-- 1. Table des albums
CREATE TABLE IF NOT EXISTS `albums` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(255)    NOT NULL,
    `cover`         VARCHAR(255)    DEFAULT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_album_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Rattachement des morceaux à un album (NULL = pas d'album, comportement par défaut)
ALTER TABLE `tracks`
    ADD COLUMN IF NOT EXISTS `album_id` INT UNSIGNED DEFAULT NULL AFTER `genre`;

-- 3. Index + clé étrangère (ignorée si déjà présente)
ALTER TABLE `tracks`
    ADD KEY IF NOT EXISTS `idx_album` (`album_id`);

SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tracks'
      AND CONSTRAINT_NAME = 'fk_tracks_album'
);

SET @add_fk_sql = IF(@fk_exists = 0,
    'ALTER TABLE `tracks` ADD CONSTRAINT `fk_tracks_album` FOREIGN KEY (`album_id`) REFERENCES `albums` (`id`) ON DELETE SET NULL',
    'SELECT ''fk_tracks_album already exists'''
);

PREPARE stmt FROM @add_fk_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ==========================================================
--  FIN — Vérification rapide
-- ==========================================================

SELECT 'Migration 1 (albums) appliquée avec succès ✔' AS statut;
SHOW COLUMNS FROM tracks LIKE 'album_id';
SHOW TABLES LIKE 'albums';

-- ==========================================================
--  Purple Music — Script de création MySQL
--  Exécuter en tant que root : mysql -u root -p < setup_purple_music.sql
-- ==========================================================

-- 1. Création de la base de données
CREATE DATABASE IF NOT EXISTS purple_music
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- 2. Création de l'utilisateur dédié
--    ⚠ Remplacez 'VotreMotDePasseIci' par un mot de passe fort !
CREATE USER IF NOT EXISTS 'purple_music_user'@'localhost'
    IDENTIFIED BY 'VotreMotDePasseIci';

-- 3. Attribution des droits (uniquement sur cette base)
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP
    ON purple_music.*
    TO 'purple_music_user'@'localhost';

FLUSH PRIVILEGES;

-- 4. Sélection de la base
USE purple_music;

-- ==========================================================
--  TABLES
-- ==========================================================

CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key`   VARCHAR(100)    NOT NULL,
    `value`         TEXT            NOT NULL,
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `username`      VARCHAR(60)     NOT NULL,
    `password`      VARCHAR(255)    NOT NULL,
    `is_admin`      TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `genres` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(100)    NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_genre_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `albums` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(255)    NOT NULL,
    `cover`         VARCHAR(255)    DEFAULT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_album_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tracks` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `filename`      VARCHAR(255)    NOT NULL,
    `title`         VARCHAR(255)    NOT NULL,
    `artist`        VARCHAR(255)    NOT NULL DEFAULT 'Artiste inconnu',
    `cover`         VARCHAR(255)    NOT NULL DEFAULT 'default.png',
    `genre`         VARCHAR(100)    NOT NULL DEFAULT 'Autre',
    `album_id`      INT UNSIGNED    DEFAULT NULL,
    `uploader_id`   INT UNSIGNED    NOT NULL,
    `upload_date`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `play_count`    INT UNSIGNED    NOT NULL DEFAULT 0,
    `duration`      INT UNSIGNED    NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_play_count` (`play_count`),
    KEY `idx_uploader`   (`uploader_id`),
    KEY `idx_album`      (`album_id`),
    CONSTRAINT `fk_tracks_user`
        FOREIGN KEY (`uploader_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_tracks_album`
        FOREIGN KEY (`album_id`) REFERENCES `albums` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `playlists` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(255)    NOT NULL,
    `creator_id`    INT UNSIGNED    NOT NULL,
    `song_ids`      TEXT,
    `is_public`     TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_creator` (`creator_id`),
    CONSTRAINT `fk_playlists_user`
        FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
--  DONNÉES PAR DÉFAUT — Genres & Settings de base
-- ==========================================================

INSERT IGNORE INTO `genres` (`name`) VALUES
    ('Phonk/Funk'),
    ('Rap'),
    ('Pop'),
    ('Rock'),
    ('Electro'),
    ('Hyperpop'),
    ('Nightcore'),
    ('Qualité inférieure'),
    ('Autre');

INSERT IGNORE INTO `settings` (`setting_key`, `value`) VALUES
    ('site_name',           'Purple Music'),
    ('color_bg',            '#0f0c1d'),
    ('color_panel',         '#1b1429'),
    ('color_primary',       '#8e44ad'),
    ('color_accent',        '#bb86fc'),
    ('color_text',          '#e0e0e0'),
    ('color_text_muted',    '#a196b4'),
    ('color_border',        '#3d2b56'),
    ('color_search_bg',     '#241b36'),
    ('color_header_bg',     'rgba(27, 20, 41, 0.85)'),
    ('color_player_bg',     'rgba(30, 24, 45, 0.85)'),
    ('color_mob_nav_bg',    'rgba(21, 16, 32, 0.95)'),
    ('color_fp_gradient_1', '#302b63'),
    ('color_fp_gradient_2', '#0f0c29'),
    ('default_cover',       'default.png'),
    ('favicon',             'favicon.png');

-- ==========================================================
--  FIN — Vérification rapide
-- ==========================================================

SELECT 'Base purple_music créée avec succès ✔' AS statut;
SHOW TABLES;

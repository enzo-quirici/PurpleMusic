-- ==========================================================
--  Purple Music / Amethyst Music — Migration 2 : Visibilité des playlists
--  À exécuter une seule fois sur une base existante :
--    mysql --default-character-set=utf8mb4 -u root -p purple_music < update-db/update-2.sql
--
--  Nécessite MySQL 8.0.29+ ou MariaDB 10.5.2+ (support de
--  "ADD COLUMN IF NOT EXISTS"). Sur une version plus ancienne,
--  retirez le "IF NOT EXISTS" de l'ALTER TABLE ci-dessous après
--  avoir vérifié que la colonne `is_public` n'existe pas déjà.
-- ==========================================================

USE purple_music;

-- Chaque créateur peut choisir si sa playlist est visible par tous
-- (1, valeur par défaut — préserve le comportement actuel où toutes
-- les playlists sont publiques) ou seulement par lui-même / un admin (0).
ALTER TABLE `playlists`
    ADD COLUMN IF NOT EXISTS `is_public` TINYINT(1) NOT NULL DEFAULT 1 AFTER `song_ids`;

-- ==========================================================
--  FIN — Vérification rapide
-- ==========================================================

SELECT 'Migration 2 (visibilité des playlists) appliquée avec succès ✔' AS statut;
SHOW COLUMNS FROM playlists LIKE 'is_public';

-- ==========================================================
--  Purple Music / Amethyst Music — Migration 3 : Historique d'écoute
--  À exécuter une seule fois sur une base existante :
--    mysql --default-character-set=utf8mb4 -u root -p purple_music < update-db/update-3.sql
--
--  Base du moteur de recommandation (action=recommend dans api.php) :
--  chaque lecture d'un morceau par un utilisateur connecté est
--  enregistrée ici (voir action=increment_play), puis utilisée pour
--  déduire une affinité de genre/artiste/album pondérée par récence.
-- ==========================================================

USE purple_music;

CREATE TABLE IF NOT EXISTS `listen_history` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED    NOT NULL,
    `track_id`      INT UNSIGNED    NOT NULL,
    `played_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lh_user`  (`user_id`),
    KEY `idx_lh_track` (`track_id`),
    CONSTRAINT `fk_listen_history_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_listen_history_track`
        FOREIGN KEY (`track_id`) REFERENCES `tracks` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
--  FIN — Vérification rapide
-- ==========================================================

SELECT 'Migration 3 (historique d''écoute) appliquée avec succès ✔' AS statut;
SHOW TABLES LIKE 'listen_history';

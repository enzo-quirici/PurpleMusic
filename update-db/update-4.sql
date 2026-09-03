-- ==========================================================
--  Purple Music / Amethyst Music — Migration 4 : Index pour
--  l'historique d'écoute à grande échelle
--  À exécuter une seule fois sur une base existante :
--    mysql --default-character-set=utf8mb4 -u root -p purple_music < update-db/update-4.sql
--
--  Contrairement aux migrations précédentes, celle-ci n'utilise PAS la
--  syntaxe raccourcie "... IF NOT EXISTS" sur CREATE INDEX / ADD COLUMN /
--  ADD KEY : cette syntaxe est une extension MariaDB (10.1+/10.5+) que
--  MySQL (y compris 8.0 et 9.x) rejette avec une pure erreur de syntaxe —
--  c'est d'ailleurs ce qui faisait planter TOUTE requête à api.php sur une
--  base MySQL avant le correctif de la migration précédente. On utilise
--  donc ici la même technique portable que la migration 1 (vérification
--  via information_schema + SQL dynamique), qui fonctionne à l'identique
--  sur MySQL et sur MariaDB.
-- ==========================================================

USE purple_music;

-- ----------------------------------------------------------
-- 1. Index composite couvrant (user_id, played_at, track_id) sur
--    listen_history.
--
--    Cette table grossit d'une ligne à chaque lecture et n'a aucune limite
--    naturelle : sur un historique "hors norme" (beaucoup d'utilisateurs
--    actifs sur une longue période), les deux requêtes qui la lisent
--    (action=recommend et action=history dans api.php) dégénèrent en scan
--    complet de la table sans un index adapté.
--
--    - action=recommend fait :
--        WHERE user_id = ? ORDER BY played_at DESC LIMIT 200, SELECT track_id
--      Avec cet index, c'est un scan d'index pur (covering index) : MySQL
--      trouve la plage du bon user_id, la parcourt déjà triée par
--      played_at, et lit track_id directement dans l'index sans jamais
--      toucher la table.
--    - action=history fait :
--        WHERE user_id = ? GROUP BY track_id ORDER BY MAX(played_at) DESC
--      L'index accélère fortement le WHERE user_id=? (la partie coûteuse à
--      grande échelle), même si le regroupement final se fait ensuite en
--      mémoire sur un nombre de lignes borné par le nombre de pistes
--      distinctes de cet utilisateur, pas par la taille totale de la table.
-- ----------------------------------------------------------

SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'listen_history'
      AND INDEX_NAME = 'idx_lh_user_played'
);

SET @add_idx_sql = IF(@idx_exists = 0,
    'ALTER TABLE `listen_history` ADD INDEX `idx_lh_user_played` (`user_id`, `played_at`, `track_id`)',
    'SELECT ''idx_lh_user_played already exists'''
);

PREPARE stmt FROM @add_idx_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------
-- 2. Suppression de l'ancien index simple idx_lh_user (user_id), devenu
--    redondant : tout ce qu'il permettait est déjà couvert par le nouvel
--    index composite ci-dessus (qui commence aussi par user_id). Le garder
--    ne servirait qu'à ralentir chaque INSERT (une ligne par lecture) pour
--    rien. N'existe que sur les bases provisionnées avant cette migration
--    (le nouveau schéma, dans setup.sql et l'auto-migration de api.php, ne
--    le crée plus).
-- ----------------------------------------------------------

SET @old_idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'listen_history'
      AND INDEX_NAME = 'idx_lh_user'
);

SET @drop_idx_sql = IF(@old_idx_exists > 0,
    'ALTER TABLE `listen_history` DROP INDEX `idx_lh_user`',
    'SELECT ''idx_lh_user already absent'''
);

PREPARE stmt FROM @drop_idx_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ==========================================================
--  FIN — Vérification rapide
-- ==========================================================

SELECT 'Migration 4 (index historique d''écoute) appliquée avec succès ✔' AS statut;
SHOW INDEX FROM listen_history;

-- ==========================================================
--  Purple Music / Amethyst Music — Migration 5 : Correction d'un
--  double encodage UTF-8 sur le genre "Qualité inférieure"
--  À exécuter une seule fois sur une base existante :
--    mysql --default-character-set=utf8mb4 -u root -p purple_music < update-db/update-5.sql
--
--  setup.sql contient un seul genre par défaut avec des caractères
--  accentués : "Qualité inférieure". Si la base a été initialisée avec
--  `mysql -u root -p < setup.sql` (sans --default-character-set=utf8mb4),
--  le client mysql se connecte en latin1 par défaut et corrompt ce nom en
--  un double encodage UTF-8 dès l'insertion ("QualitÃ© infÃ©rieure" à
--  l'affichage), même si la base et les colonnes sont bien en utf8mb4.
--  Cette migration corrige la ligne si elle est affectée ; elle est sans
--  effet si le genre est déjà correct (déjà corrigé, ou base initialisée
--  correctement dès le départ).
--
--  On compare/écrit en UNHEX(...) plutôt qu'en littéral texte pour rester
--  correct quel que soit le jeu de caractères du client qui exécute ce
--  fichier (évite de reproduire le même bug en corrigeant le précédent).
-- ==========================================================

USE purple_music;

UPDATE `genres`
SET `name` = CONVERT(UNHEX('5175616C6974C3A920696E66C3A9726965757265') USING utf8mb4)
WHERE HEX(`name`) = '5175616C6974C383C2A920696E66C383C2A9726965757265';

-- ==========================================================
--  FIN — Vérification rapide
-- ==========================================================

SELECT 'Migration 5 (correction encodage genre) appliquée avec succès ✔' AS statut;
SELECT `name`, HEX(`name`) AS hex_name FROM `genres` WHERE `name` LIKE '%ualit%';

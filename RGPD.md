# Politique de confidentialité (RGPD)

*Dernière mise à jour : à compléter lors de la mise en production.*

Cette page décrit quelles données personnelles Purple Music (Amethyst Music)
collecte, pourquoi, et quels droits vous avez sur ces données, conformément
au Règlement Général sur la Protection des Données (RGPD — UE 2016/679).

## 1. Responsable du traitement

*À compléter par l'administrateur de cette instance* (nom, e-mail ou moyen de
contact du responsable du site). Chaque déploiement de Purple Music est
opéré indépendamment ; c'est la personne ou l'organisation qui héberge cette
instance qui est responsable du traitement des données, pas les auteurs du
logiciel.

## 2. Données collectées

| Donnée | Où | Pourquoi |
|---|---|---|
| Nom d'utilisateur, mot de passe (haché) | table `users` | Créer un compte et s'authentifier |
| Morceaux écoutés et date d'écoute | table `listen_history` | Construire vos recommandations personnalisées ("Recommandé pour vous") et votre historique |
| Playlists créées et leur contenu | table `playlists` | Fonctionnalité de playlists |
| Fichiers audio et pochettes envoyés | dossiers `music/`, `covers/` | Fonctionnement du service de streaming |
| Adresse IP | table `login_attempts` | Limiter les tentatives de connexion abusives (sécurité) |
| Préférences d'affichage (volume, thème, genres masqués…) | stockage local de votre navigateur (`localStorage`), jamais transmis au serveur | Confort d'utilisation, ne quitte pas votre appareil |

Purple Music ne partage aucune de ces données avec un tiers, ne fait pas de
publicité et n'utilise pas de traceurs publicitaires.

## 3. Base légale et finalité

Le traitement de ces données repose sur :
- **l'exécution du service** que vous avez demandé en créant un compte
  (authentification, lecture, playlists, recommandations) ;
- **l'intérêt légitime** de l'opérateur du site à assurer la sécurité du
  service (limitation des tentatives de connexion).

## 4. Durée de conservation

Vos données sont conservées tant que votre compte existe. La suppression
d'un compte entraîne la suppression des morceaux que vous avez importés,
de vos playlists et de votre historique d'écoute associés à ce compte.

## 5. Vos droits

Conformément aux articles 15 à 22 du RGPD, vous disposez d'un droit d'accès,
de rectification, d'effacement, de limitation, d'opposition et de
portabilité de vos données. Pour exercer ces droits, contactez le
responsable du traitement de cette instance (voir section 1). Vous pouvez
également introduire une réclamation auprès de votre autorité de contrôle
(la CNIL en France, cnil.fr).

## 6. Cookies et stockage local

Purple Music utilise uniquement un cookie de session technique (nécessaire à
la connexion) et le `localStorage` de votre navigateur pour retenir vos
préférences d'affichage. Aucun cookie de mesure d'audience ou publicitaire
n'est déposé.

---

*Ce document est un modèle générique fourni avec le projet Purple Music /
Amethyst Music. Il doit être adapté (notamment la section « Responsable du
traitement ») par la personne qui héberge une instance de ce logiciel avant
mise en production.*

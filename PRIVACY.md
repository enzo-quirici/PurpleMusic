# Privacy Policy (GDPR)

*Last updated: to be completed before going into production.*

This page describes what personal data Purple Music (Amethyst Music)
collects, why, and what rights you have over that data, in accordance with
the General Data Protection Regulation (GDPR — EU 2016/679).

## 1. Data controller

*To be completed by the administrator of this instance* (name, e-mail or
other contact method for the person responsible for this site). Each
deployment of Purple Music is operated independently; the person or
organization hosting this instance is the data controller, not the authors
of the software.

## 2. Data collected

| Data | Where | Why |
|---|---|---|
| Username, password (hashed) | `users` table | Create an account and authenticate |
| Tracks played and when | `listen_history` table | Build your personalized recommendations ("Recommended for you") and your listening history |
| Playlists you create and their contents | `playlists` table | Playlist feature |
| Uploaded audio files and cover art | `music/`, `covers/` folders | Streaming functionality |
| IP address | `login_attempts` table | Rate-limit abusive login attempts (security) |
| Display preferences (volume, theme, hidden genres…) | your browser's local storage (`localStorage`), never sent to the server | User comfort, never leaves your device |

Purple Music does not share any of this data with third parties, does not
run advertising, and does not use advertising trackers.

## 3. Legal basis and purpose

Processing of this data is based on:
- **performance of the service** you requested by creating an account
  (authentication, playback, playlists, recommendations);
- **legitimate interest** of the site operator in keeping the service
  secure (limiting login attempts).

## 4. Retention period

Your data is kept for as long as your account exists. Deleting an account
also deletes the tracks you uploaded, your playlists, and your listening
history associated with that account.

## 5. Your rights

Under Articles 15 to 22 of the GDPR, you have the right to access,
rectify, erase, restrict, object to, and port your data. To exercise these
rights, contact the data controller of this instance (see section 1). You
may also lodge a complaint with your local data protection authority (in
France, the CNIL — cnil.fr).

## 6. Cookies and local storage

Purple Music only uses a technical session cookie (required to stay logged
in) and your browser's `localStorage` to remember your display
preferences. No analytics or advertising cookie is set.

---

*This document is a generic template provided with the Purple Music /
Amethyst Music project. It must be adapted (in particular the "Data
controller" section) by whoever hosts an instance of this software before
going into production.*

# Tableau de bord ROOTS, version partagée (PHP et MySQL)

Chaque membre de l'équipe a son compte. Valider, marquer publié ou reporter une publication est enregistré pour tout le monde, avec le nom de la personne et l'heure. La page se rafraîchit toute seule toutes les 30 secondes.

## Ce qu'il faut

1. Un hébergement avec PHP 8 ou plus et MySQL (cPanel convient).
2. Un certificat HTTPS actif sur l'adresse choisie (par exemple tableau.roots-co.fr).

## Installation en 6 étapes (environ 10 minutes)

1. **Créer la base.** Dans cPanel, ouvrir « Bases de données MySQL » : créer une base, créer un utilisateur avec un mot de passe long, ajouter l'utilisateur à la base avec tous les privilèges. Noter les trois noms.
2. **Envoyer les fichiers.** Dans le Gestionnaire de fichiers (ou par FTP), envoyer tout le contenu du dossier `partage_php` dans le dossier de l'adresse choisie (par exemple `public_html/tableau`).
3. **Créer la configuration.** Copier `config.sample.php` en `config.php`, puis remplir : nom de la base, utilisateur, mot de passe, une phrase d'installation longue (`install_key`). Conseil : mettre `data_dir` hors du dossier public (par exemple `/home/votre_compte/tdb_data`) et y copier `data/data.php`.
4. **Installer.** Ouvrir `https://votre-adresse/install.php`, saisir la clé d'installation, le nom, l'adresse e-mail et le mot de passe (12 caractères minimum) du premier administrateur.
5. **Supprimer `install.php`** du serveur, tout de suite après.
6. **Créer les comptes de l'équipe.** Se connecter, ouvrir « Équipe » (en bas du menu) : ajouter chaque personne avec le rôle Équipe (elle peut valider et publier) ou Lecture seule. Transmettre le mot de passe provisoire de vive voix ou par un gestionnaire de mots de passe.

## Mettre à jour les données

Après chaque travail, le fichier `donnees_a_envoyer_data.json` est régénéré. Un administrateur l'envoie dans « Équipe », section « Mettre à jour les données ». Les statuts et l'historique déjà saisis sont conservés.

## Sécurité prévue

1. Un compte par personne, mots de passe stockés hachés (bcrypt via PHP), 12 caractères minimum.
2. 8 essais ratés par adresse IP puis blocage de 15 minutes.
3. Jeton anti falsification (CSRF) sur chaque modification, session de 8 heures, cookie HttpOnly et Secure.
4. Rôles : administrateur, équipe, lecture seule. Le serveur refuse toute modification pour la lecture seule.
5. Les données ne sont jamais servies en direct : le fichier de données commence par une instruction qui bloque sa lecture, même si la configuration du serveur est mal réglée.
6. En-têtes de sécurité (CSP, anti cadre, pas d'indexation), redirection HTTPS dans `.htaccess`.

## Fichiers

`index.php` connexion et page, `api.php` enregistrement des statuts, `equipe.php` comptes et données, `install.php` installation unique, `lib.php` fonctions communes, `app.html` interface, `schema.sql` tables (créées automatiquement), `config.sample.php` modèle de configuration.

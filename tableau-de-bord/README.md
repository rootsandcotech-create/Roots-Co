# Tableau de bord ROOTS

Page de pilotage de ROOTS & Co : publications (texte, affiche, fiche, validation), projets et objectifs, reste à faire, radar d'événements, canaux, sécurité.

Deux versions :

1. `index.html` : version de consultation, données chiffrées (AES-GCM) par un mot de passe partagé, statuts gardés dans le navigateur. Publiée sur GitHub Pages.
2. `partage_php/` : version partagée par toute l'équipe (PHP et MySQL), un compte par personne, statuts et historique communs avec le nom de la personne. Mode d'emploi dans `partage_php/README.md`.

Les affiches viennent du dépôt `roots-affiches`. `schema.sql` : tables MySQL de la version partagée.

Mise à jour : après chaque travail, `index.html` et `donnees_a_envoyer_data.json` sont régénérés. Le premier est poussé sur GitHub, le second est envoyé par un administrateur dans la page Équipe de la version partagée.

<?php
/* Copier ce fichier en config.php et remplir les valeurs. Ne jamais publier config.php. */
return [
    // Base MySQL créée dans cPanel (Bases de données MySQL)
    'db_dsn'       => 'mysql:host=localhost;dbname=NOM_DE_LA_BASE;charset=utf8mb4',
    'db_user'      => 'UTILISATEUR_MYSQL',
    'db_pass'      => 'MOT_DE_PASSE_MYSQL',
    // Clé demandée une seule fois par install.php (inventer une longue phrase)
    'install_key'  => 'CHANGER_CETTE_CLE_LONGUE_ET_UNIQUE',
    // Dossier des données, de préférence HORS du dossier public (ex. /home/compte/tdb_data)
    'data_dir'     => __DIR__ . '/data',
    'session_name' => 'roots_tdb',
];

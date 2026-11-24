<?php
declare(strict_types=1);
/* Installation unique : crée les tables et le premier compte administrateur.
   À SUPPRIMER du serveur dès que l'installation est faite. */
require __DIR__ . '/lib.php';
secure_headers();
start_session();
$c = cfg();
$db = db();
$mysql = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
$ai = $mysql ? 'INTEGER PRIMARY KEY AUTO_INCREMENT' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
$tail = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';

$existe = false;
try { $existe = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0; } catch (Throwable $e) { $existe = false; }

page_open('Installation');
if ($existe) {
    echo '<div class="b"><h1>Déjà installé</h1><p>Un compte existe déjà. Supprimez le fichier install.php du serveur.</p></div></body></html>';
    exit;
}
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim((string)($_POST['nom'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $mdp = (string)($_POST['mdp'] ?? '');
    if (!csrf_ok($_POST['csrf'] ?? null)) $msg = 'Session expirée, réessayez.';
    elseif (empty($c['install_key']) || !hash_equals((string)$c['install_key'], (string)($_POST['cle'] ?? ''))) $msg = 'Clé d\'installation incorrecte.';
    elseif ($nom === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $msg = 'Nom ou adresse e-mail invalide.';
    elseif (mb_strlen($mdp) < 12) $msg = 'Le mot de passe doit faire au moins 12 caractères.';
    else {
        $db->exec("CREATE TABLE IF NOT EXISTS users (id $ai, nom VARCHAR(120) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE, mdp_hash VARCHAR(255) NOT NULL, role VARCHAR(12) NOT NULL DEFAULT 'equipe', actif INTEGER NOT NULL DEFAULT 1, cree_le VARCHAR(25) NOT NULL, derniere_connexion VARCHAR(25) NULL)$tail");
        $db->exec("CREATE TABLE IF NOT EXISTS statuts (pub_id VARCHAR(40) NOT NULL PRIMARY KEY, statut VARCHAR(40) NOT NULL, par_nom VARCHAR(120) NOT NULL, maj_le VARCHAR(25) NOT NULL)$tail");
        $db->exec("CREATE TABLE IF NOT EXISTS historique (id $ai, pub_id VARCHAR(40) NOT NULL, titre VARCHAR(300) NOT NULL, canal VARCHAR(30) NOT NULL, ancien VARCHAR(40) NOT NULL, nouveau VARCHAR(40) NOT NULL, par_nom VARCHAR(120) NOT NULL, le VARCHAR(25) NOT NULL)$tail");
        $db->exec("CREATE TABLE IF NOT EXISTS controles (pub_id VARCHAR(40) NOT NULL, cle VARCHAR(60) NOT NULL, ok INTEGER NOT NULL DEFAULT 0, par_nom VARCHAR(120) NOT NULL, maj_le VARCHAR(25) NOT NULL, PRIMARY KEY (pub_id, cle))$tail");
        $db->exec("CREATE TABLE IF NOT EXISTS tentatives (id $ai, ip VARCHAR(45) NOT NULL, le INTEGER NOT NULL)$tail");
        $db->prepare('INSERT INTO users (nom, email, mdp_hash, role, actif, cree_le) VALUES (?, ?, ?, ?, 1, ?)')
            ->execute([$nom, $email, password_hash($mdp, PASSWORD_DEFAULT), 'admin', now_iso()]);
        echo '<div class="b"><h1>Installation terminée</h1><p>Le compte administrateur est créé. <b>Supprimez maintenant le fichier install.php du serveur</b>, puis connectez-vous.</p><a class="btn" href="index.php">Ouvrir le tableau de bord</a></div></body></html>';
        exit;
    }
}
echo '<form class="b" method="post"><img class="l" src="logo.png" alt="ROOTS"><h1>Installation</h1><p>Crée les tables et le premier compte administrateur.</p>'
    . '<input type="hidden" name="csrf" value="' . h(csrf()) . '">'
    . '<label>Clé d\'installation (dans config.php)</label><input name="cle" type="password" required>'
    . '<label>Nom affiché</label><input name="nom" required>'
    . '<label>Adresse e-mail</label><input name="email" type="email" required>'
    . '<label>Mot de passe (12 caractères minimum)</label><input name="mdp" type="password" required minlength="12" autocomplete="new-password">'
    . '<button type="submit">Installer</button>' . ($msg ? '<div class="e">' . h($msg) . '</div>' : '') . '</form></body></html>';

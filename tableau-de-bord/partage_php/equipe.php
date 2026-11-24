<?php
declare(strict_types=1);
/* Gestion de l'équipe et des données (administrateurs seulement). */
require __DIR__ . '/lib.php';
secure_headers();
start_session();
header('Cache-Control: no-store');
$db = db();
$u = me($db);
if (!$u || $u['role'] !== 'admin') { http_response_code(403); header('Location: index.php'); exit; }

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok($_POST['csrf'] ?? null)) { $err = 'Session expirée, rechargez la page.'; }
    else {
        $a = (string)($_POST['a'] ?? '');
        if ($a === 'ajout') {
            $nom = trim((string)($_POST['nom'] ?? ''));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $role = (string)($_POST['role'] ?? 'equipe');
            $mdp = (string)($_POST['mdp'] ?? '');
            if ($nom === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !isset(ROLES[$role])) $err = 'Nom, adresse ou rôle invalide.';
            elseif (mb_strlen($mdp) < 12) $err = 'Mot de passe provisoire : 12 caractères minimum.';
            else {
                try {
                    $db->prepare('INSERT INTO users (nom, email, mdp_hash, role, actif, cree_le) VALUES (?, ?, ?, ?, 1, ?)')->execute([$nom, $email, password_hash($mdp, PASSWORD_DEFAULT), $role, now_iso()]);
                    $msg = 'Compte créé pour ' . $nom . '. Transmettez le mot de passe provisoire de vive voix ou par un gestionnaire de mots de passe.';
                } catch (Throwable $e) { $err = 'Cette adresse a déjà un compte.'; }
            }
        } elseif ($a === 'actif' || $a === 'mdp' || $a === 'role') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id === (int)$u['id'] && $a !== 'mdp') $err = 'Vous ne pouvez pas modifier votre propre compte ici.';
            elseif ($a === 'actif') { $db->prepare('UPDATE users SET actif = 1 - actif WHERE id = ?')->execute([$id]); $msg = 'Compte mis à jour.'; }
            elseif ($a === 'role' && isset(ROLES[(string)($_POST['role'] ?? '')])) { $db->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([(string)$_POST['role'], $id]); $msg = 'Rôle mis à jour.'; }
            elseif ($a === 'mdp') {
                $m = (string)($_POST['mdp'] ?? '');
                if (mb_strlen($m) < 12) $err = 'Mot de passe : 12 caractères minimum.';
                else { $db->prepare('UPDATE users SET mdp_hash = ? WHERE id = ?')->execute([password_hash($m, PASSWORD_DEFAULT), $id]); $msg = 'Mot de passe changé.'; }
            }
        } elseif ($a === 'donnees') {
            $f = $_FILES['fichier'] ?? null;
            if (!$f || $f['error'] !== UPLOAD_ERR_OK || $f['size'] > 8 * 1024 * 1024) $err = 'Fichier absent ou trop gros (8 Mo maximum).';
            else {
                $raw = (string)file_get_contents($f['tmp_name']);
                $j = json_decode($raw, true);
                if (!is_array($j) || !isset($j['posts'], $j['proj'], $j['todo'])) $err = 'Ce fichier n\'est pas un data.json valide.';
                else {
                    $dir = data_dir();
                    if (!is_dir($dir)) mkdir($dir, 0750, true);
                    file_put_contents($dir . '/data.php', DATA_HEADER . $raw, LOCK_EX);
                    $msg = 'Données mises à jour : ' . count($j['posts']) . ' publications, version du ' . (string)($j['maj'] ?? '?') . '.';
                }
            }
        }
    }
}
$users = $db->query('SELECT id, nom, email, role, actif, derniere_connexion FROM users ORDER BY id')->fetchAll();
$d = load_data();

page_open('Équipe');
echo '<div class="b w"><img class="l" src="logo.png" alt="ROOTS"><h1>Équipe et données</h1><p><a href="index.php">Retour au tableau de bord</a></p>';
if ($msg) echo '<div class="ok">' . h($msg) . '</div>';
if ($err) echo '<div class="e">' . h($err) . '</div>';
$tok = '<input type="hidden" name="csrf" value="' . h(csrf()) . '">';
echo '<h1 style="font-size:17px;margin-top:22px">Comptes</h1><table><tr><th>Nom</th><th>Adresse</th><th>Rôle</th><th>Dernière connexion</th><th>Actions</th></tr>';
foreach ($users as $r) {
    echo '<tr><td>' . h($r['nom']) . '</td><td>' . h($r['email']) . '</td><td>'
        . '<form method="post" style="display:flex;gap:6px;align-items:center">' . $tok . '<input type="hidden" name="a" value="role"><input type="hidden" name="id" value="' . (int)$r['id'] . '"><select name="role" style="padding:6px;width:auto">';
    foreach (ROLES as $k => $v) echo '<option value="' . $k . '"' . ($k === $r['role'] ? ' selected' : '') . '>' . h($v) . '</option>';
    echo '</select><button class="sm" type="submit">Appliquer</button></form></td><td>' . h((string)($r['derniere_connexion'] ?? 'jamais')) . '</td><td>'
        . '<form method="post" style="display:inline">' . $tok . '<input type="hidden" name="a" value="actif"><input type="hidden" name="id" value="' . (int)$r['id'] . '"><button class="sm" type="submit">' . ((int)$r['actif'] ? 'Désactiver' : 'Réactiver') . '</button></form> '
        . '<form method="post" style="display:inline">' . $tok . '<input type="hidden" name="a" value="mdp"><input type="hidden" name="id" value="' . (int)$r['id'] . '"><input name="mdp" type="password" placeholder="Nouveau mot de passe" minlength="12" style="width:170px;padding:6px" autocomplete="new-password"> <button class="sm" type="submit">Changer</button></form>'
        . '</td></tr>';
}
echo '</table><h1 style="font-size:17px;margin-top:26px">Ajouter un membre</h1><form method="post">' . $tok . '<input type="hidden" name="a" value="ajout">'
    . '<label>Nom affiché</label><input name="nom" required><label>Adresse e-mail</label><input name="email" type="email" required>'
    . '<label>Rôle</label><select name="role">';
foreach (ROLES as $k => $v) echo '<option value="' . $k . '"' . ($k === 'equipe' ? ' selected' : '') . '>' . h($v) . '</option>';
echo '</select><label>Mot de passe provisoire (12 caractères minimum)</label><input id="pm" name="mdp" type="text" required minlength="12" autocomplete="off">'
    . '<button type="button" class="sm" style="margin-top:8px" onclick="var a=\'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789\',v=new Uint32Array(16),s=\'\';crypto.getRandomValues(v);v.forEach(function(x){s+=a[x%a.length]});document.getElementById(\'pm\').value=s">Générer</button> '
    . '<button type="submit">Créer le compte</button></form>'
    . '<h1 style="font-size:17px;margin-top:26px">Mettre à jour les données</h1><p>Version actuelle : ' . h((string)($d['maj'] ?? 'aucune')) . ' (' . count($d['posts'] ?? []) . ' publications). Envoyez le fichier data.json fourni après chaque mise à jour (il est stocké sous une forme que le serveur ne diffuse jamais).</p>'
    . '<form method="post" enctype="multipart/form-data">' . $tok . '<input type="hidden" name="a" value="donnees"><input type="file" name="fichier" accept=".json,application/json" required><br><button type="submit">Envoyer le fichier</button></form></div></body></html>';

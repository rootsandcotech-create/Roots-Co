<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
secure_headers();
start_session();
header('Cache-Control: no-store');

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: index.php');
    exit;
}

$db = db();
$u = me($db);
$err = '';

if (!$u && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $db->prepare('DELETE FROM tentatives WHERE le < ?')->execute([time() - 900]);
    $n = $db->prepare('SELECT COUNT(*) FROM tentatives WHERE ip = ?');
    $n->execute([$ip]);
    if (!csrf_ok($_POST['csrf'] ?? null)) {
        $err = 'Session expirée, réessayez.';
    } elseif ((int)$n->fetchColumn() >= 8) {
        $err = 'Trop d\'essais. Patientez 15 minutes.';
    } else {
        $st = $db->prepare('SELECT id, mdp_hash, actif FROM users WHERE email = ?');
        $st->execute([strtolower(trim((string)($_POST['email'] ?? '')))]);
        $r = $st->fetch();
        // comparaison à temps constant même si le compte n'existe pas
        $hash = $r['mdp_hash'] ?? password_hash('x', PASSWORD_DEFAULT);
        if ($r && (int)$r['actif'] === 1 && password_verify((string)($_POST['mdp'] ?? ''), $hash)) {
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$r['id'];
            $_SESSION['t'] = time();
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            $db->prepare('DELETE FROM tentatives WHERE ip = ?')->execute([$ip]);
            $db->prepare('UPDATE users SET derniere_connexion = ? WHERE id = ?')->execute([now_iso(), (int)$r['id']]);
            header('Location: index.php');
            exit;
        }
        $db->prepare('INSERT INTO tentatives (ip, le) VALUES (?, ?)')->execute([$ip, time()]);
        $err = 'Adresse ou mot de passe incorrect.';
    }
}

if (!$u) {
    page_open('Tableau de bord ROOTS');
    echo '<form class="b" method="post" autocomplete="on"><img class="l" src="logo.png" alt="ROOTS"><h1>Tableau de bord ROOTS</h1>'
        . '<p>Accès réservé à l\'équipe. Connectez-vous avec votre compte personnel.</p>'
        . '<input type="hidden" name="csrf" value="' . h(csrf()) . '">'
        . '<label for="email">Adresse e-mail</label><input id="email" name="email" type="email" required autocomplete="username">'
        . '<label for="mdp">Mot de passe</label><input id="mdp" name="mdp" type="password" required autocomplete="current-password">'
        . '<button type="submit">Se connecter</button>' . ($err ? '<div class="e">' . h($err) . '</div>' : '') . '</form></body></html>';
    exit;
}

$tpl = (string)file_get_contents(__DIR__ . '/app.html');
$srv = ['user' => $u['nom'], 'role' => $u['role'], 'csrf' => csrf(), 'data' => load_data(), 'state' => state($db)];
$json = json_encode($srv, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
$tpl = str_replace('<!--SRV-->', '<script>window.__SRV=' . $json . ';</script>', $tpl);
$tpl = str_replace('<div id="gate">', '<div id="gate" style="display:none">', $tpl);
echo $tpl;

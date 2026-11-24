<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
secure_headers();
start_session();

$db = db();
$u = me($db);
if (!$u) json_out(['error' => 'Session expirée, reconnectez-vous.'], 401);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (($_GET['a'] ?? '') === 'state') json_out(['state' => state($db)]);
    json_out(['error' => 'Requête inconnue.'], 400);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'Méthode refusée.'], 405);
if (!csrf_ok($_SERVER['HTTP_X_CSRF'] ?? null)) json_out(['error' => 'Jeton de sécurité invalide, rechargez la page.'], 403);
if ($u['role'] === 'lecture') json_out(['error' => 'Votre compte est en lecture seule.'], 403);

$b = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($b)) json_out(['error' => 'Données invalides.'], 400);
$id = (string)($b['id'] ?? '');
if (!preg_match('/^[A-Za-z0-9\-]{1,40}$/', $id)) json_out(['error' => 'Publication inconnue.'], 400);

$post = null;
foreach (load_data()['posts'] ?? [] as $p) {
    if (($p['id'] ?? '') === $id) { $post = $p; break; }
}
if (!$post) json_out(['error' => 'Publication inconnue.'], 404);

$a = (string)($b['a'] ?? '');
if ($a === 'status') {
    $s = (string)($b['s'] ?? '');
    if (!in_array($s, STATUTS, true)) json_out(['error' => 'Statut refusé.'], 400);
    $db->beginTransaction();
    $st = $db->prepare('SELECT statut FROM statuts WHERE pub_id = ?');
    $st->execute([$id]);
    $old = $st->fetchColumn();
    if ($old === false) $old = (string)($post['statut'] ?? 'Anticipé');
    $now = now_iso();
    $db->prepare('REPLACE INTO statuts (pub_id, statut, par_nom, maj_le) VALUES (?, ?, ?, ?)')->execute([$id, $s, $u['nom'], $now]);
    $db->prepare('INSERT INTO historique (pub_id, titre, canal, ancien, nouveau, par_nom, le) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$id, (string)($post['titre'] ?? ''), (string)($post['canal'] ?? ''), (string)$old, $s, $u['nom'], $now]);
    $db->commit();
} elseif ($a === 'check') {
    $k = (string)($b['k'] ?? '');
    if (!in_array($k, CONTROLES, true)) json_out(['error' => 'Contrôle inconnu.'], 400);
    $db->prepare('REPLACE INTO controles (pub_id, cle, ok, par_nom, maj_le) VALUES (?, ?, ?, ?, ?)')
        ->execute([$id, $k, !empty($b['v']) ? 1 : 0, $u['nom'], now_iso()]);
} else {
    json_out(['error' => 'Action inconnue.'], 400);
}
json_out(['ok' => true, 'state' => state($db)]);

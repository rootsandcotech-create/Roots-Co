<?php
declare(strict_types=1);

const ROLES = ['admin' => 'Administrateur', 'equipe' => 'Équipe', 'lecture' => 'Lecture seule'];
const STATUTS = ['Validé', 'Publié', 'Reporté', 'Anticipé', 'Rédigé', 'En attente de validation'];
const CONTROLES = ['Texte relu', 'Visuel conforme', 'Numéro WhatsApp et liens vérifiés', 'Heure de départ confirmée'];

function cfg(): array
{
    static $c = null;
    if ($c === null) {
        $f = __DIR__ . '/config.php';
        if (!is_file($f)) {
            http_response_code(503);
            exit('Configuration absente : copier config.sample.php en config.php et la remplir.');
        }
        $c = require $f;
    }
    return $c;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = cfg();
        $pdo = new PDO($c['db_dsn'], $c['db_user'] ?? null, $c['db_pass'] ?? null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function data_dir(): string
{
    $d = cfg()['data_dir'] ?? (__DIR__ . '/data');
    return rtrim($d, '/\\');
}

function secure_headers(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' https://raw.githubusercontent.com data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; font-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000');
    }
    header('X-Robots-Tag: noindex, nofollow');
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name(cfg()['session_name'] ?? 'roots_tdb');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function me(PDO $db): ?array
{
    if (empty($_SESSION['uid'])) return null;
    if (time() - (int)($_SESSION['t'] ?? 0) > 8 * 3600) { $_SESSION = []; return null; }
    $st = $db->prepare('SELECT id, nom, email, role, actif FROM users WHERE id = ?');
    $st->execute([(int)$_SESSION['uid']]);
    $u = $st->fetch();
    if (!$u || (int)$u['actif'] !== 1) { $_SESSION = []; return null; }
    $_SESSION['t'] = time();
    return $u;
}

function csrf(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_ok(?string $t): bool
{
    return is_string($t) && hash_equals($_SESSION['csrf'] ?? '', $t);
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_out(array $d, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

const DATA_HEADER = "<?php http_response_code(403); exit; ?>
";

function now_iso(): string
{
    return gmdate('Y-m-d\TH:i:s\Z');
}

function load_data(): array
{
    $f = data_dir() . '/data.php';
    if (!is_file($f)) return ['maj' => '', 'posts' => [], 'proj' => [], 'todo' => ['auj' => [], 'sem' => [], 'plus' => []], 'sec' => [], 'can' => [], 'evr' => []];
    $raw = (string)file_get_contents($f);
    $raw = substr($raw, (int)strpos($raw, "?>
") + 3);   // l'en-tête PHP bloque la lecture directe du fichier
    $j = json_decode($raw, true);
    return is_array($j) ? $j : [];
}

function state(PDO $db): array
{
    $st = [];
    foreach ($db->query('SELECT pub_id, statut, par_nom, maj_le FROM statuts') as $r) {
        $st[$r['pub_id']] = ['s' => $r['statut'], 'at' => $r['maj_le'], 'par' => $r['par_nom']];
    }
    $hist = [];
    foreach ($db->query('SELECT pub_id, titre, canal, ancien, nouveau, par_nom, le FROM historique ORDER BY id DESC LIMIT 500') as $r) {
        $hist[] = ['id' => $r['pub_id'], 'titre' => $r['titre'], 'canal' => $r['canal'], 'de' => $r['ancien'], 'a' => $r['nouveau'], 'par' => $r['par_nom'], 'at' => $r['le']];
    }
    $ck = [];
    foreach ($db->query('SELECT pub_id, cle FROM controles WHERE ok = 1') as $r) {
        $ck[$r['pub_id']][$r['cle']] = true;
    }
    return ['st' => (object)$st, 'hist' => $hist, 'ck' => (object)$ck];
}

function page_open(string $titre): void
{
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">'
        . '<title>' . h($titre) . '</title><style>@font-face{font-family:Manrope;src:url(m.woff2) format("woff2");font-weight:200 800}'
        . '*{box-sizing:border-box}body{margin:0;font-family:Manrope,Arial,sans-serif;background:#f4f7fa;color:#0c2848;min-height:100vh;display:grid;place-items:center;padding:24px}'
        . '.b{width:100%;max-width:440px;background:#fff;border-radius:18px;padding:34px;box-shadow:0 20px 60px rgba(12,40,72,.14)}.w{max-width:900px}'
        . 'img.l{height:46px;margin-bottom:18px}h1{font-size:22px;margin:0 0 6px}p{color:#51617a;line-height:1.5;margin:0 0 16px;font-size:14px}'
        . 'label{display:block;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#51617a;margin:12px 0 5px}'
        . 'input,select{width:100%;padding:12px 14px;border:1.5px solid #d5dde8;border-radius:10px;font:inherit;font-size:15px}'
        . 'button,.btn{margin-top:16px;padding:12px 18px;border:0;border-radius:10px;background:#0c2848;color:#fff;font:inherit;font-weight:700;font-size:14px;cursor:pointer;text-decoration:none;display:inline-block}'
        . '.e{color:#b4232a;font-size:13px;margin-top:10px}.ok{color:#17803d;font-size:13px;margin-top:10px}table{width:100%;border-collapse:collapse;font-size:14px}'
        . 'th,td{text-align:left;padding:9px 8px;border-bottom:1px solid #e3e9f0}th{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#51617a}'
        . '.sm{padding:6px 10px;margin:0;font-size:12px;background:#e3eef8;color:#0c2848}a{color:#0f92b8}</style></head><body>';
}

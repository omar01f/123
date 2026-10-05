<?php
ini_set('display_errors', '0');
$root = dirname(__DIR__);
require "$root/src/payments.php"; require "$root/src/Calc.php";
if (is_file("$root/.env")) foreach (file("$root/.env", FILE_IGNORE_NEW_LINES) as $l)
    if (preg_match('/^\s*([A-Z_]+)\s*=\s*(.*?)\s*$/', $l, $m) && getenv($m[1]) === false) putenv("$m[1]=$m[2]");
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'lifetime' => 2592000]);
session_start();
header('Content-Type: application/json');

const DAY = 86400000;
$F = "$root/data/db.json";
function load() { global $F; return is_file($F) ? json_decode(file_get_contents($F), true) : ['users' => []]; }
function store($d) { global $F; @mkdir(dirname($F), 0775, true); file_put_contents($F, json_encode($d), LOCK_EX); }
function out($code, $o) { http_response_code($code); echo json_encode($o); exit; }
function now() { return (int) (microtime(true) * 1000); }
function subActive($u) { return !empty($u['sub']) && $u['sub']['end'] > now(); }
function pub($u) { return ['name' => $u['name'], 'email' => $u['email'], 'credits' => $u['credits'], 'total' => count($u['calcs']),
    'sub' => ['active' => subActive($u), 'end' => $u['sub']['end'] ?? null, 'cancelled' => !empty($u['sub']['cancelled'])]]; }
function cut($s, $n) { return preg_match("/^.{0,$n}/us", $s, $m) ? $m[0] : ''; }
function pwOk($p) { return is_string($p) && strlen($p) >= 8 && preg_match('/[A-Za-z]/', $p) && preg_match('/\d/', $p); }

$r = $_GET['r'] ?? '';
$b = json_decode(file_get_contents('php://input'), true) ?: [];
$db = load();
$cur = null;
foreach ($db['users'] as $k => $x) if (($_SESSION['uid'] ?? '') === $x['id']) { $cur = &$db['users'][$k]; break; }

if ($r === 'signup') {
    $email = strtolower(trim((string) ($b['email'] ?? ''))); $name = cut(trim((string) ($b['name'] ?? '')), 60);
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) out(400, ['error' => 'Enter a valid name and email']);
    if (!pwOk($b['password'] ?? null)) out(400, ['error' => 'Password needs 8+ characters with a letter and a number']);
    foreach ($db['users'] as $x) if ($x['email'] === $email) out(409, ['error' => 'Email already registered']);
    $nu = ['id' => bin2hex(random_bytes(8)), 'name' => $name, 'email' => $email, 'pw' => password_hash($b['password'], PASSWORD_DEFAULT), 'credits' => 0, 'sub' => null, 'calcs' => [], 'payments' => []];
    $db['users'][] = $nu; store($db); session_regenerate_id(true); $_SESSION['uid'] = $nu['id']; out(200, pub($nu));
}
if ($r === 'login') {
    $email = strtolower(trim((string) ($b['email'] ?? '')));
    foreach ($db['users'] as $x) if ($x['email'] === $email && password_verify((string) ($b['password'] ?? ''), $x['pw'])) {
        session_regenerate_id(true); $_SESSION['uid'] = $x['id']; out(200, pub($x));
    }
    out(401, ['error' => 'Invalid email or password']);
}
if ($r === 'logout') { session_destroy(); out(200, []); }
if ($r === 'me') $cur ? out(200, pub($cur)) : out(401, ['error' => 'Not signed in']);
if (!$cur) out(401, ['error' => 'Please log in']);

switch ($r) {
case 'calc':
    try { $res = Calc::evaluate((string) ($b['expression'] ?? '')); } catch (Exception $e) { out(400, ['error' => $e->getMessage()]); }
    $sub = subActive($cur);
    if (!$sub && $cur['credits'] < 1) out(402, ['error' => 'Payment required']);
    if (!$sub) $cur['credits']--;
    array_unshift($cur['calcs'], ['expression' => cut((string) $b['expression'], 200), 'result' => $res, 'at' => now(), 'paid' => $sub ? 'subscription' : 'credit']);
    store($db); out(200, ['result' => $res, 'user' => pub($cur)]);
case 'history':
    out(200, ['calcs' => array_slice($cur['calcs'], 0, 100), 'payments' => array_slice($cur['payments'], 0, 100)]);
case 'pay':
    $plan = $b['plan'] ?? '';
    if (!isset(PLANS[$plan])) out(400, ['error' => 'Unknown plan']);
    try { $c = charge($plan); } catch (Exception $e) { out(500, ['error' => $e->getMessage()]); }
    if ($plan === 'credit') $cur['credits']++;
    else $cur['sub'] = ['end' => max(now(), subActive($cur) ? $cur['sub']['end'] : 0) + 30 * DAY, 'cancelled' => false];
    array_unshift($cur['payments'], ['id' => $c['id'], 'plan' => PLANS[$plan]['label'], 'amount' => PLANS[$plan]['amount'], 'at' => now(), 'status' => $c['status']]);
    store($db); out(200, pub($cur));
case 'cancel': case 'resume':
    if (!subActive($cur)) out(400, ['error' => 'No active subscription']);
    $cur['sub']['cancelled'] = ($r === 'cancel'); store($db); out(200, pub($cur));
case 'settings':
    if (isset($b['name'])) { $n = cut(trim((string) $b['name']), 60); if ($n === '') out(400, ['error' => 'Name required']); $cur['name'] = $n; }
    if (!empty($b['newPassword'])) {
        if (!password_verify((string) ($b['currentPassword'] ?? ''), $cur['pw'])) out(401, ['error' => 'Current password is wrong']);
        if (!pwOk($b['newPassword'])) out(400, ['error' => 'Password needs 8+ characters with a letter and a number']);
        $cur['pw'] = password_hash($b['newPassword'], PASSWORD_DEFAULT);
    }
    store($db); out(200, pub($cur));
}
out(404, ['error' => 'Not found']);

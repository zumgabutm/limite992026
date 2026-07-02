<?php
if (PHP_SAPI === 'cli') return;

$LOGIN_USER = 'admin';
$LOGIN_PASS = 'admin';

$acao_atual = $_GET['acao'] ?? '';
$uri_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

$public_paths = [
    '/hls_cleaner.php',
];

$public_regex = [
    '#^/play/[a-zA-Z0-9_-]+\.m3u8$#',
    '#^/seg/[a-zA-Z0-9_-]+/[^/]+\.ts$#',
    '#^/vod/[a-zA-Z0-9_-]+\.[a-zA-Z0-9]{2,5}$#',
    '#^/amigo/[a-zA-Z0-9_-]+/[a-zA-Z0-9_-]+\.m3u$#',
];

$public_actions = [
    'm3u_restream',
    'play',
    'seg',
    'vod',
    'aguarde_ts'
];

$eh_publico = in_array($uri_path, $public_paths, true) || in_array($acao_atual, $public_actions, true);

foreach ($public_regex as $rx) {
    if (preg_match($rx, $uri_path)) {
        $eh_publico = true;
        break;
    }
}

if ($eh_publico) return;

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

if (isset($_GET['logout_painel'])) {
    unset($_SESSION['painel_principal_ok']);
    header('Location: /');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['painel_user'], $_POST['painel_pass'])) {
    if ($_POST['painel_user'] === $LOGIN_USER && $_POST['painel_pass'] === $LOGIN_PASS) {
        $_SESSION['painel_principal_ok'] = 'sim';
        header('Location: /');
        exit;
    }
    $erro_login_painel = 'Usuário ou senha inválido';
}

if (($_SESSION['painel_principal_ok'] ?? '') !== 'sim') {
    if ($acao_atual !== '') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['erro' => 'Login necessário'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Limiter99 Restream</title>
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#07111f,#0b1a2e);color:#eaf3ff;font-family:Arial,Helvetica,sans-serif;padding:20px}
.card{width:100%;max-width:390px;background:#0b1a2e;border:1px solid #1e4778;border-radius:20px;padding:24px;box-shadow:0 25px 70px rgba(0,0,0,.45)}
h1{margin:0 0 8px;font-size:25px}
p{margin:0 0 18px;color:#9fc3ec}
label{display:block;font-size:13px;color:#9fc3ec;margin:12px 0 6px}
input{width:100%;background:#061225;color:#fff;border:1px solid #244e7c;border-radius:12px;padding:13px;font-size:15px;outline:none}
button{width:100%;margin-top:16px;border:0;border-radius:12px;background:#2563eb;color:white;padding:13px;font-weight:900;cursor:pointer}
.erro{background:#7f1d1d;border:1px solid #ef4444;color:#fff;padding:10px;border-radius:10px;margin-bottom:12px;font-size:13px}
</style>
</head>
<body>
<form class="card" method="post">
<h1>🚀 Limiter99 Restream</h1>
<p>Acesso protegido.</p>
'.(isset($erro_login_painel) ? '<div class="erro">'.$erro_login_painel.'</div>' : '').'
<label>Usuário</label>
<input name="painel_user" value="admin" autocomplete="username">
<label>Senha</label>
<input name="painel_pass" type="password" autocomplete="current-password">
<button type="submit">Entrar</button>
</form>
</body>
</html>';
    exit;
}

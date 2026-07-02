<?php
error_reporting(0);
ini_set('display_errors', 0);
set_time_limit(0);
session_start();

$USER_OK = 'admin';
$PASS_OK = 'Pacep1@#$%';

$BASE = __DIR__;
$DIRS = [
    'hls' => $BASE . '/hls',
    'streams' => $BASE . '/streams',
    'logs' => $BASE . '/logs'
];

$TOKENS_FILE = $BASE . '/area_tokens_m3u.json';
$REVOKED_FILE = $BASE . '/area_tokens_revogados.json';

function ssd_bytes_dir($dir) {
    $total = 0;
    $qtd = 0;
    if (!is_dir($dir)) return ['bytes' => 0, 'files' => 0];
    $files = glob(rtrim($dir, '/\\') . '/*');
    if (is_array($files)) {
        foreach ($files as $f) {
            if (is_file($f)) {
                $total += filesize($f);
                $qtd++;
            }
        }
    }
    return ['bytes' => $total, 'files' => $qtd];
}

function ssd_fmt($bytes) {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

function ssd_status($dirs) {
    $out = [];
    $total = 0;
    $files = 0;
    foreach ($dirs as $nome => $dir) {
        $s = ssd_bytes_dir($dir);
        $out[$nome] = $s;
        $total += $s['bytes'];
        $files += $s['files'];
    }
    return [$out, $total, $files];
}

function ssd_limpar_logs($dirs) {
    $apagados = 0;
    $bytes = 0;
    if (!is_dir($dirs['logs'])) return [0, 0];
    $files = glob(rtrim($dirs['logs'], '/\\') . '/*');
    if (is_array($files)) {
        foreach ($files as $f) {
            if (is_file($f)) {
                $tam = filesize($f);
                if (@unlink($f)) {
                    $apagados++;
                    $bytes += $tam;
                }
            }
        }
    }
    return [$apagados, $bytes];
}

function ssd_limpar_hls_total($dirs) {
    $apagados = 0;
    $bytes = 0;

    if (function_exists('exec')) {
        @exec('pkill -f "ffmpeg.*IP_8088_RESTREAM" 2>/dev/null');
    }

    foreach ($dirs as $dir) {
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        $files = glob(rtrim($dir, '/\\') . '/*');

        if (is_array($files)) {
            foreach ($files as $f) {
                if (is_file($f)) {
                    $tam = filesize($f);
                    if (@unlink($f)) {
                        $apagados++;
                        $bytes += $tam;
                    }
                }
            }
        }
        @chmod($dir, 0777);
    }

    return [$apagados, $bytes];
}

function token_load_json($file) {
    if (!file_exists($file)) return [];
    $j = json_decode((string)file_get_contents($file), true);
    return is_array($j) ? $j : [];
}

function token_save_json($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function token_hash($token) {
    return sha1((string)$token);
}

function token_decode_payload($token) {
    $p = explode('.', (string)$token);
    if (count($p) !== 3) return [];
    $data = strtr($p[1], '-_', '+/');
    $pad = strlen($data) % 4;
    if ($pad) $data .= str_repeat('=', 4 - $pad);
    $json = base64_decode($data);
    $arr = json_decode((string)$json, true);
    return is_array($arr) ? $arr : [];
}

function token_revogar($token, $motivo = 'manual') {
    global $REVOKED_FILE;
    $token = trim((string)$token);
    if ($token === '') return false;

    $rev = token_load_json($REVOKED_FILE);
    $rev[token_hash($token)] = [
        'token' => $token,
        'motivo' => $motivo,
        'revogado_em' => date('Y-m-d H:i:s')
    ];
    token_save_json($REVOKED_FILE, $rev);
    return true;
}

function token_remover_um($hash) {
    global $TOKENS_FILE;
    $tokens = token_load_json($TOKENS_FILE);
    if (!isset($tokens[$hash])) return false;

    $token = $tokens[$hash]['token'] ?? '';
    token_revogar($token, 'removido pelo painel');
    unset($tokens[$hash]);
    token_save_json($TOKENS_FILE, $tokens);
    return true;
}

function token_remover_todos() {
    global $TOKENS_FILE;
    $tokens = token_load_json($TOKENS_FILE);
    $qtd = 0;

    foreach ($tokens as $hash => $info) {
        if (!empty($info['token'])) {
            token_revogar($info['token'], 'limpar todos');
            $qtd++;
        }
    }

    token_save_json($TOKENS_FILE, []);
    return $qtd;
}

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: /hls_cleaner.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_user'], $_POST['login_pass'])) {
    if ($_POST['login_user'] === $USER_OK && $_POST['login_pass'] === $PASS_OK) {
        $_SESSION['ssd_ok'] = 'sim';
        header('Location: /hls_cleaner.php');
        exit;
    }
    $erro = 'Usuário ou senha inválido';
}

if (($_SESSION['ssd_ok'] ?? '') !== 'sim') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Área dos Estudos</title>
<style>
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#07111f;color:#eaf3ff;font-family:Arial;padding:20px}
.card{width:100%;max-width:380px;background:#0b1a2e;border:1px solid #1e4778;border-radius:18px;padding:22px}
h1{margin:0 0 8px}
p{color:#9fc3ec}
input{width:100%;background:#061225;color:#fff;border:1px solid #244e7c;border-radius:10px;padding:12px;margin:6px 0 12px}
button{width:100%;border:0;border-radius:10px;background:#2563eb;color:#fff;padding:12px;font-weight:900}
.err{background:#7f1d1d;padding:10px;border-radius:10px;margin-bottom:10px}
</style>
</head>
<body>
<form class="card" method="post">
<h1>🧹 Área dos Estudos</h1>
<p>Monitor de espaço e tokens</p>
'.(isset($erro) ? '<div class="err">'.$erro.'</div>' : '').'
<label>Usuário</label>
<input name="login_user" value="admin">
<label>Senha</label>
<input name="login_pass" type="password">
<button>Entrar</button>
</form>
</body>
</html>';
    exit;
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'limpar_logs') {
        [$q, $b] = ssd_limpar_logs($DIRS);
        $msg = 'Logs limpos: ' . $q . ' arquivos / ' . ssd_fmt($b) . ' liberados.';
    }

    if ($acao === 'limpar_total') {
        [$q, $b] = ssd_limpar_hls_total($DIRS);
        $msg = 'HLS/streams/logs limpos: ' . $q . ' arquivos / ' . ssd_fmt($b) . ' liberados. Tokens e cache da lista mantidos.';
    }

    if ($acao === 'token_delete_one') {
        $hash = preg_replace('/[^a-f0-9]/', '', $_POST['hash'] ?? '');
        $msg = token_remover_um($hash) ? 'Token removido e revogado.' : 'Token não encontrado.';
    }

    if ($acao === 'token_delete_all') {
        $qtd = token_remover_todos();
        $msg = 'Todos os tokens conhecidos foram removidos e revogados: ' . $qtd;
    }

    if ($acao === 'token_revoke_manual') {
        $tok = trim((string)($_POST['manual_token'] ?? ''));
        $msg = token_revogar($tok, 'colado manualmente') ? 'Token colado foi revogado.' : 'Cole um token válido.';
    }
}

[$status, $total, $files] = ssd_status($DIRS);

$disk_free = @disk_free_space($BASE);
$disk_total = @disk_total_space($BASE);
$disk_used = ($disk_total && $disk_free) ? ($disk_total - $disk_free) : 0;

$tokens = token_load_json($TOKENS_FILE);
$filtro = trim((string)($_GET['categoria'] ?? ''));

$tokens_filtrados = [];
foreach ($tokens as $hash => $info) {
    $cat = (string)($info['categoria'] ?? '');
    if ($filtro !== '' && stripos($cat, $filtro) === false) continue;
    $tokens_filtrados[$hash] = $info;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Área dos Estudos</title>
<style>
body{margin:0;background:#07111f;color:#eaf3ff;font-family:Arial;padding:24px}
.box{max-width:1100px;margin:auto;background:#0b1a2e;border:1px solid #1e4778;border-radius:18px;padding:22px}
h1{margin-top:0}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
.card{background:#061225;border:1px solid #244e7c;border-radius:14px;padding:14px}
.card b{font-size:24px}
small{color:#9fc3ec}
button{border:0;border-radius:12px;color:white;padding:11px 14px;font-weight:900;cursor:pointer;margin:5px 3px}
.azul{background:#2563eb}.verde{background:#059669}.vermelho{background:#dc2626}.cinza{background:#334155}
.msg{background:#052e1b;border:1px solid #059669;color:#b7f7d4;padding:12px;border-radius:12px;margin:14px 0}
.aviso{background:#3b1111;border:1px solid #dc2626;color:#fecaca;padding:12px;border-radius:12px;margin:14px 0}
input,textarea{width:100%;background:#061225;color:#fff;border:1px solid #244e7c;border-radius:10px;padding:10px}
textarea{height:70px}
.token{background:#061225;border:1px solid #244e7c;border-radius:12px;padding:12px;margin:9px 0;word-break:break-all}
a{color:#93c5fd}
</style>
</head>
<body>
<div class="box">
<h1>🧹 Área dos Estudos</h1>
<small>Monitor de espaço, HLS e tokens gerados.</small>

<?php if ($msg): ?>
<div class="msg"><?=htmlspecialchars($msg)?></div>
<?php endif; ?>

<div class="grid" style="margin-top:16px">
    <div class="card"><small>HLS</small><br><b><?=ssd_fmt($status['hls']['bytes'])?></b><br><small><?=$status['hls']['files']?> arquivos</small></div>
    <div class="card"><small>Streams</small><br><b><?=ssd_fmt($status['streams']['bytes'])?></b><br><small><?=$status['streams']['files']?> arquivos</small></div>
    <div class="card"><small>Logs</small><br><b><?=ssd_fmt($status['logs']['bytes'])?></b><br><small><?=$status['logs']['files']?> arquivos</small></div>
    <div class="card"><small>Total HLS/streams/logs</small><br><b><?=ssd_fmt($total)?></b><br><small><?=$files?> arquivos</small></div>
</div>

<div class="grid" style="margin-top:12px">
    <div class="card"><small>SSD usado no servidor</small><br><b><?=ssd_fmt($disk_used)?></b></div>
    <div class="card"><small>SSD livre</small><br><b><?=ssd_fmt($disk_free)?></b></div>
</div>

<div class="aviso">
<b>Atenção:</b> limpar HLS total pode parar canal aberto no momento. Token e cache da lista continuam.
</div>

<form method="post" style="display:inline">
    <input type="hidden" name="acao" value="limpar_logs">
    <button class="verde" type="submit">Limpar só logs</button>
</form>

<form method="post" style="display:inline" onsubmit="return confirm('Limpar HLS/streams/logs? Não apaga token nem cache da lista, mas pode parar canal aberto agora.')">
    <input type="hidden" name="acao" value="limpar_total">
    <button class="vermelho" type="submit">Limpar HLS total sem apagar token</button>
</form>

<button class="azul" onclick="location.reload()">Atualizar MB</button>
<a href="/"><button class="cinza">Voltar ao painel</button></a>
<a href="/hls_cleaner.php?logout=1"><button class="cinza">Sair</button></a>

<hr style="border:0;border-top:1px solid #244e7c;margin:22px 0">

<h2>🔐 Tokens de listas geradas</h2>

<form method="get">
    <label>Buscar por categoria</label>
    <input name="categoria" value="<?=htmlspecialchars($filtro)?>" placeholder="ex: Telecine">
    <button class="azul" type="submit">Buscar</button>
    <a href="/hls_cleaner.php"><button class="cinza" type="button">Mostrar todos</button></a>
</form>

<form method="post" onsubmit="return confirm('Remover e revogar todos os tokens conhecidos?')">
    <input type="hidden" name="acao" value="token_delete_all">
    <button class="vermelho" type="submit">Limpar todos tokens conhecidos</button>
</form>

<form method="post">
    <input type="hidden" name="acao" value="token_revoke_manual">
    <label>Revogar token manual colado</label>
    <textarea name="manual_token" placeholder="Cole aqui só o token JWT"></textarea>
    <button class="vermelho" type="submit">Revogar token colado</button>
</form>

<p><small>Total listado: <?=count($tokens_filtrados)?> / Total conhecido: <?=count($tokens)?></small></p>

<?php if (!$tokens_filtrados): ?>
<div class="card"><small>Nenhum token registrado ainda. Os próximos links gerados pelo painel passam a aparecer aqui.</small></div>
<?php endif; ?>

<?php foreach ($tokens_filtrados as $hash => $info): ?>
<div class="token">
    <b>Categoria:</b> <?=htmlspecialchars($info['categoria'] ?? '-')?><br>
    <b>Criado:</b> <?=htmlspecialchars($info['created_at'] ?? '-')?><br>
    <b>Expira:</b> <?=!empty($info['exp']) ? date('d/m/Y H:i:s', (int)$info['exp']) : '-'?><br>
    <b>URL:</b> <span style="color:#facc15"><?=htmlspecialchars($info['url'] ?? '')?></span><br>
    <form method="post" onsubmit="return confirm('Remover esse token?')">
        <input type="hidden" name="acao" value="token_delete_one">
        <input type="hidden" name="hash" value="<?=htmlspecialchars($hash)?>">
        <button class="vermelho" type="submit">Excluir este token</button>
    </form>
</div>
<?php endforeach; ?>

</div>
</body>
</html>

<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('memory_limit', '512M');
set_time_limit(0);


// PAINEL_LOGIN_E_HLS_CLEANER_SEGURO
if (PHP_SAPI !== 'cli') {
    $__cleaner_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if ($__cleaner_path === '/hls_cleaner.php') {
        require __DIR__ . '/hls_cleaner.php';
        exit;
    }
}
require_once __DIR__ . '/painel_login_guard.php';



// HLS_CLEANER_ROUTE_SEGURO
if (PHP_SAPI !== 'cli') {
    $__hls_cleaner_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if ($__hls_cleaner_path === '/hls_cleaner.php') {
        require __DIR__ . '/hls_cleaner.php';
        exit;
    }
}


// RESTREAM_BUFFER_FORTE_GLOBAL
if (PHP_SAPI !== 'cli') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: *');
    header('Access-Control-Expose-Headers: Content-Length, Content-Range, Accept-Ranges');
    header('X-Accel-Buffering: no');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}


// RESTREAM_GLOBAL_CORS_FIX
if (PHP_SAPI !== 'cli') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: *');
    header('Access-Control-Expose-Headers: Content-Length, Content-Range, Accept-Ranges');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}


$config_file = __DIR__ . '/config.json';
$config = [];
if (file_exists($config_file)) {
    $tmp = json_decode((string)file_get_contents($config_file), true);
    if (is_array($tmp)) $config = $tmp;
}

$config_default = [
    'url' => 'http://bru.i0x.fun',
    'user' => 'leaopreto',
    'pass' => 'leaopreto',
    'token_secret' => 'CHAVE_SECRETA_2026',
    'token_ttl' => 86400 * 30, // CORRIGIDO: 30 dias (era 1 dia)
    'cache_ttl' => 7200,
    'limite_canais' => 5000,
    'hls_path' => __DIR__ . '/hls/',
    'base_url' => '',
    'hls_url' => '',
    'streams_path' => __DIR__ . '/streams/',
    'logs_path' => __DIR__ . '/logs/',
    'ffmpeg_path' => '/usr/bin/ffmpeg',
    'hls_wait_seconds' => 18,
    'hls_time' => 2,
    'hls_list_size' => 90,
    'hls_delete_threshold' => 90,
    'hls_stale_seconds' => 20,
    'ffmpeg_reconnect_delay_max' => 4,
    'ffmpeg_rw_timeout_seconds' => 35,
    'vod_realtime' => 1,
    'vod_hls_list_size' => 180,
    'ffmpeg_prefer_mode' => 'aac',
    'modo_velocidade_online' => 1,
    'stream_idle_stop_seconds' => 0
];
$config = array_merge($config_default, $config);

if (PHP_SAPI === 'cli' && isset($argv) && is_array($argv)) {
    foreach (array_slice($argv, 1) as $arg) {
        if (strpos($arg, '=') === false) continue;
        [$k, $v] = explode('=', $arg, 2);
        $_GET[$k] = $v;
    }
    $dominio_cli = $_GET['dominio'] ?? ($config['dominio'] ?? 'localhost');
    $_SERVER['HTTP_HOST'] = $dominio_cli;
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['HTTPS'] = 'off';
}

if (empty($config['hls_path'])) $config['hls_path'] = __DIR__ . '/hls/';
if (empty($config['streams_path'])) $config['streams_path'] = __DIR__ . '/streams/';
if (empty($config['logs_path'])) $config['logs_path'] = __DIR__ . '/logs/';
if (empty($config['ffmpeg_path'])) $config['ffmpeg_path'] = '/usr/bin/ffmpeg';
foreach (['hls_wait_seconds','hls_time','hls_list_size','hls_delete_threshold','hls_stale_seconds','ffmpeg_reconnect_delay_max','ffmpeg_rw_timeout_seconds','vod_realtime','vod_hls_list_size','modo_velocidade_online'] as $k) {
    $config[$k] = max(1, (int)($config[$k] ?? $config_default[$k] ?? 1));
}

function app_base_url() {
    global $config;
    if (!empty($config['base_url'])) return rtrim((string)$config['base_url'], '/');
    $https = false;
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') $https = true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') $https = true;
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
    $dir = trim(str_replace('\\', '/', $dir), '/');
    return $scheme . '://' . $host . ($dir !== '' && $dir !== '.' ? '/' . $dir : '');
}

if (empty($config['hls_url'])) {
    $config['hls_url'] = app_base_url() . '/hls/';
}

foreach (['hls_path', 'streams_path', 'logs_path'] as $k) {
    $config[$k] = rtrim((string)$config[$k], '/\\') . '/';
}
$config['hls_url'] = rtrim((string)$config['hls_url'], '/') . '/';

function criar_pasta($pasta) {
    if (!is_dir($pasta)) @mkdir($pasta, 0777, true);
}

criar_pasta($config['hls_path']);
criar_pasta($config['streams_path']);
criar_pasta($config['logs_path']);

function json_out($dados) {
    if (PHP_SAPI !== 'cli') {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: *');
    }
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    $json = json_encode($dados, $flags);
    if ($json === false) {
        $json = json_encode(utf8_limpar($dados), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
    echo $json !== false ? $json : '{"erro":"Falha ao gerar JSON"}';
    exit;
}

function utf8_limpar($valor) {
    if (is_array($valor)) {
        $out = [];
        foreach ($valor as $k => $v) $out[utf8_limpar($k)] = utf8_limpar($v);
        return $out;
    }
    if (is_string($valor)) {
        if (function_exists('mb_check_encoding') && !mb_check_encoding($valor, 'UTF-8')) {
            if (function_exists('mb_convert_encoding')) return mb_convert_encoding($valor, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        }
        if (function_exists('iconv')) {
            $tmp = @iconv('UTF-8', 'UTF-8//IGNORE', $valor);
            if ($tmp !== false) return $tmp;
        }
    }
    return $valor;
}

if (!empty($_GET['acao'])) {
    register_shutdown_function(function() {
        $e = error_get_last();
        if (!$e) return;
        $fatais = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
        if (!in_array($e['type'], $fatais, true)) return;
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['erro' => 'Erro fatal PHP: ' . $e['message']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    });
}

function texto_out($texto, $tipo = 'text/plain; charset=utf-8') {
    if (PHP_SAPI !== 'cli') header('Content-Type: ' . $tipo);
    echo $texto;
    exit;
}

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function m3u_attr($v) {
    $v = str_replace(["\r", "\n"], ' ', (string)$v);
    return str_replace('"', "'", $v);
}

function b64u($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function b64u_decode($data) {
    $data = strtr($data, '-_', '+/');
    $pad = strlen($data) % 4;
    if ($pad) $data .= str_repeat('=', 4 - $pad);
    return base64_decode($data);
}

function gerar_token($payload) {
    global $config;
    $payload['exp'] = time() + (int)$config['token_ttl'];
    $h = b64u(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $p = b64u(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $s = b64u(hash_hmac('sha256', $h . '.' . $p, (string)$config['token_secret'], true));
    return $h . '.' . $p . '.' . $s;
}

function validar_token($token) {
    global $config;
    $parts = explode('.', (string)$token);
    if (count($parts) !== 3) return null;
    [$h, $p, $s] = $parts;
    $expected = b64u(hash_hmac('sha256', $h . '.' . $p, (string)$config['token_secret'], true));
    if (!hash_equals($expected, $s)) return null;
    $payload = json_decode((string)b64u_decode($p), true);
    if (!is_array($payload)) return null;
    if (empty($payload['exp']) || (int)$payload['exp'] < time()) return null;
    return $payload;
}

function cache_file() {
    return __DIR__ . '/cache_streams_lazy.json';
}

function raw_m3u_file() {
    return __DIR__ . '/raw_m3u_origem.txt';
}

function estado_lazy_vazio() {
    return [
        'timestamp' => 0,
        'raw_timestamp' => 0,
        'categorias' => [],
        'loaded_categories' => [],
        'next_index' => 0
    ];
}

function normalizar_canais_lazy($canais) {
    if (!is_array($canais)) return [];
    $out = [];
    foreach ($canais as $i => $c) {
        if (!is_array($c)) continue;
        $url = trim((string)($c['url'] ?? ''));
        if (!preg_match('/^https?:\/\//i', $url)) continue;
        $nome = trim((string)($c['nome'] ?? ('Canal ' . ($i + 1))));
        $grupo = trim((string)($c['grupo'] ?? 'Geral')) ?: 'Geral';
        $logo = trim((string)($c['logo'] ?? ''));
        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($c['id'] ?? ''));
        if ($id === '') $id = substr(sha1($url . '|' . $nome . '|' . $grupo . '|' . $i), 0, 20);
        $out[] = ['id' => $id, 'nome' => $nome, 'url' => $url, 'logo' => $logo, 'grupo' => $grupo];
    }
    return $out;
}

function carregar_estado_lazy() {
    $estado = estado_lazy_vazio();
    $file = cache_file();
    if (file_exists($file)) {
        $tmp = json_decode((string)file_get_contents($file), true);
        if (is_array($tmp)) $estado = array_merge($estado, $tmp);
    }
    if (!is_array($estado['categorias'])) $estado['categorias'] = [];
    if (!is_array($estado['loaded_categories'])) $estado['loaded_categories'] = [];
    foreach ($estado['loaded_categories'] as $cat => $canais) {
        $estado['loaded_categories'][$cat] = normalizar_canais_lazy($canais);
    }
    $estado['next_index'] = max(0, (int)($estado['next_index'] ?? 0));
    return $estado;
}

function salvar_estado_lazy($estado) {
    $base = estado_lazy_vazio();
    $estado = array_merge($base, is_array($estado) ? $estado : []);
    $estado['timestamp'] = time();
    file_put_contents(cache_file(), json_encode($estado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function extrair_categorias_m3u($m3u) {
    $linhas = preg_split("/\r\n|\n|\r/", (string)$m3u);
    $set = [];
    $categorias = [];
    foreach ($linhas as $linha) {
        $linha = trim($linha);
        if (stripos($linha, '#EXTINF:') !== 0) continue;
        $grupo = 'Geral';
        if (preg_match('/group-title="([^"]*)"/i', $linha, $m)) $grupo = trim($m[1]) ?: 'Geral';
        if (empty($set[$grupo])) {
            $set[$grupo] = true;
            $categorias[] = $grupo;
        }
    }
    sort($categorias, SORT_NATURAL | SORT_FLAG_CASE);
    return $categorias;
}

function parse_m3u_categoria($m3u, $categoria) {
    $linhas = preg_split("/\r\n|\n|\r/", (string)$m3u);
    $canais = [];
    $info = null;
    foreach ($linhas as $linha) {
        $linha = trim($linha);
        if ($linha === '') continue;
        if (stripos($linha, '#EXTINF:') === 0) {
            $nome = 'Canal';
            $grupo = 'Geral';
            $logo = '';
            if (preg_match('/group-title="([^"]*)"/i', $linha, $m)) $grupo = trim($m[1]) ?: 'Geral';
            if (preg_match('/tvg-logo="([^"]*)"/i', $linha, $m)) $logo = trim($m[1]);
            if (preg_match('/,(.*)$/', $linha, $m)) $nome = trim($m[1]) ?: $nome;
            $info = ['nome' => $nome, 'grupo' => $grupo, 'logo' => $logo];
            continue;
        }
        if ($info && preg_match('/^https?:\/\//i', $linha)) {
            if (strcasecmp((string)$info['grupo'], (string)$categoria) === 0) {
                $i = count($canais);
                $canais[] = [
                    'id' => substr(sha1($linha . '|' . $info['nome'] . '|' . $info['grupo'] . '|' . $i), 0, 20),
                    'nome' => $info['nome'],
                    'url' => $linha,
                    'logo' => $info['logo'],
                    'grupo' => $info['grupo']
                ];
            }
            $info = null;
        }
    }
    return normalizar_canais_lazy($canais);
}

function categoria_real_lazy($categoria, $estado = null) {
    $categoria = trim((string)$categoria);
    if ($categoria === '') return '';
    if ($estado === null) $estado = carregar_estado_lazy();
    foreach ($estado['categorias'] as $cat) {
        if (strcasecmp((string)$cat, $categoria) === 0) return (string)$cat;
    }
    return $categoria;
}

function proxima_categoria_lazy($estado) {
    $cats = $estado['categorias'] ?? [];
    $total = count($cats);
    if ($total <= 0) return '';
    $inicio = max(0, (int)($estado['next_index'] ?? 0));
    for ($i = 0; $i < $total; $i++) {
        $idx = ($inicio + $i) % $total;
        $cat = (string)$cats[$idx];
        if (!array_key_exists($cat, $estado['loaded_categories'])) return $cat;
    }
    return '';
}

function origem_m3u_url() {
    global $config;
    $base = trim((string)($config['url'] ?? ''));
    if ($base === '') return '';
    if (stripos($base, 'get.php') !== false || strpos($base, '?') !== false) return $base;
    return rtrim($base, '/') . '/get.php?' . http_build_query([
        'username' => (string)$config['user'],
        'password' => (string)$config['pass'],
        'type' => 'm3u_plus',
        'output' => 'ts'
    ]);
}

function validar_arquivo_m3u($arquivo) {
    if (!file_exists($arquivo) || filesize($arquivo) < 20) {
        return ['ok' => false, 'erro' => 'Arquivo M3U vazio'];
    }
    $fp = @fopen($arquivo, 'rb');
    if (!$fp) return ['ok' => false, 'erro' => 'Não consegui ler a M3U baixada'];
    $lido = 0;
    $amostra = '';
    $tem_extinf = false;
    while (!feof($fp) && $lido < 1048576) {
        $chunk = (string)fread($fp, 65536);
        $lido += strlen($chunk);
        if ($amostra === '') $amostra = substr($chunk, 0, 220);
        if (strpos($chunk, '#EXTINF') !== false) {
            $tem_extinf = true;
            break;
        }
    }
    fclose($fp);
    if (!$tem_extinf) {
        $amostra = trim(strip_tags($amostra));
        return ['ok' => false, 'erro' => 'A origem respondeu, mas não retornou uma M3U válida. Resposta: ' . $amostra];
    }
    return ['ok' => true];
}

function baixar_m3u_para_arquivo($destino) {
    $url = origem_m3u_url();
    if ($url === '') return ['ok' => false, 'erro' => 'URL da origem vazia no config.json'];

    $tmp = $destino . '.tmp.' . uniqid('', true);
    @unlink($tmp);
    $erro = '';
    $http = 0;

    if (function_exists('curl_init')) {
        $fp = @fopen($tmp, 'wb');
        if (!$fp) return ['ok' => false, 'erro' => 'Sem permissão para criar cache bruto da M3U'];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 240,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'Mozilla/5.0 Área dos Estudos-Restream-Lazy/2.0',
            CURLOPT_HTTPHEADER => ['Accept: */*', 'Connection: keep-alive']
        ]);
        $ok = curl_exec($ch);
        $erro = curl_error($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        if ($ok !== false && file_exists($tmp) && filesize($tmp) > 20) {
            $val = validar_arquivo_m3u($tmp);
            if ($val['ok']) {
                @rename($tmp, $destino);
                return ['ok' => true, 'arquivo' => $destino, 'url' => $url, 'http' => $http];
            }
            @unlink($tmp);
            return ['ok' => false, 'erro' => $val['erro'] . ($http ? ' HTTP ' . $http : '')];
        }
        @unlink($tmp);
    }

    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 240,
                'ignore_errors' => true,
                'header' => "User-Agent: Mozilla/5.0 Área dos Estudos-Restream-Lazy/2.0\r\nAccept: */*\r\nConnection: keep-alive\r\n"
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ]);
        $in = @fopen($url, 'rb', false, $ctx);
        $out = @fopen($tmp, 'wb');
        if ($in && $out) {
            while (!feof($in)) {
                $buf = fread($in, 65536);
                if ($buf === false) break;
                fwrite($out, $buf);
            }
        }
        if ($in) fclose($in);
        if ($out) fclose($out);
        if (file_exists($tmp) && filesize($tmp) > 20) {
            $val = validar_arquivo_m3u($tmp);
            if ($val['ok']) {
                @rename($tmp, $destino);
                return ['ok' => true, 'arquivo' => $destino, 'url' => $url, 'http' => 0];
            }
            @unlink($tmp);
            return ['ok' => false, 'erro' => $val['erro']];
        }
        @unlink($tmp);
    }

    return ['ok' => false, 'erro' => 'Não consegui baixar a M3U da origem' . ($erro ? ': ' . $erro : '') . ($http ? ' HTTP ' . $http : '')];
}

function extrair_categorias_m3u_arquivo($arquivo) {
    $set = [];
    $categorias = [];
    $fp = @fopen($arquivo, 'rb');
    if (!$fp) return [];
    while (($linha = fgets($fp)) !== false) {
        $linha = trim($linha);
        if (stripos($linha, '#EXTINF:') !== 0) continue;
        $grupo = 'Geral';
        if (preg_match('/group-title="([^"]*)"/i', $linha, $m)) $grupo = trim($m[1]) ?: 'Geral';
        if (empty($set[$grupo])) {
            $set[$grupo] = true;
            $categorias[] = $grupo;
        }
    }
    fclose($fp);
    sort($categorias, SORT_NATURAL | SORT_FLAG_CASE);
    return $categorias;
}

function parse_m3u_categoria_arquivo($arquivo, $categoria) {
    global $config;
    $canais = [];
    $info = null;
    $limite = max(1, (int)($config['limite_canais'] ?? 5000));
    $fp = @fopen($arquivo, 'rb');
    if (!$fp) return [];
    while (($linha = fgets($fp)) !== false) {
        $linha = trim($linha);
        if ($linha === '') continue;
        if (stripos($linha, '#EXTINF:') === 0) {
            $nome = 'Canal';
            $grupo = 'Geral';
            $logo = '';
            if (preg_match('/group-title="([^"]*)"/i', $linha, $m)) $grupo = trim($m[1]) ?: 'Geral';
            if (preg_match('/tvg-logo="([^"]*)"/i', $linha, $m)) $logo = trim($m[1]);
            if (preg_match('/,(.*)$/', $linha, $m)) $nome = trim($m[1]) ?: $nome;
            $info = ['nome' => $nome, 'grupo' => $grupo, 'logo' => $logo];
            continue;
        }
        if ($info && preg_match('/^https?:\/\//i', $linha)) {
            if (strcasecmp((string)$info['grupo'], (string)$categoria) === 0) {
                $i = count($canais);
                $canais[] = [
                    'id' => substr(sha1($linha . '|' . $info['nome'] . '|' . $info['grupo'] . '|' . $i), 0, 20),
                    'nome' => $info['nome'],
                    'url' => $linha,
                    'logo' => $info['logo'],
                    'grupo' => $info['grupo']
                ];
                if (count($canais) >= $limite) break;
            }
            $info = null;
        }
    }
    fclose($fp);
    return normalizar_canais_lazy($canais);
}

function garantir_raw_lazy($forcar = false) {
    $estado = carregar_estado_lazy();
    $raw_file = raw_m3u_file();
    $raw_ok = file_exists($raw_file) && filesize($raw_file) > 20;
    if ($forcar || !$raw_ok) {
        $res = baixar_m3u_para_arquivo($raw_file);
        if (!$res['ok']) {
            if ($raw_ok) return ['ok' => true, 'estado' => $estado, 'cache_antigo' => true];
            return ['ok' => false, 'erro' => $res['erro'] ?? 'Fonte sem resposta'];
        }
        $estado = estado_lazy_vazio();
        $estado['raw_timestamp'] = time();
    }
    if (empty($estado['categorias'])) {
        $estado['categorias'] = extrair_categorias_m3u_arquivo($raw_file);
        $estado['raw_timestamp'] = filemtime($raw_file) ?: time();
        salvar_estado_lazy($estado);
    }
    if (empty($estado['categorias'])) return ['ok' => false, 'erro' => 'Nenhuma categoria encontrada na M3U da origem'];
    return ['ok' => true, 'estado' => $estado];
}

function carregar_categoria_lazy($categoria = '', $forcar_raw = false) {
    $base = garantir_raw_lazy($forcar_raw);
    if (!$base['ok']) return $base;
    $estado = $base['estado'];
    $raw_file = raw_m3u_file();
    $categoria = trim((string)$categoria);
    if ($categoria === '') $categoria = proxima_categoria_lazy($estado);
    if ($categoria === '') {
        return [
            'ok' => true,
            'fim' => true,
            'mensagem' => 'Todas as categorias já foram carregadas',
            'estado' => $estado,
            'progresso' => count($estado['loaded_categories']) . '/' . count($estado['categorias'])
        ];
    }
    $categoria = categoria_real_lazy($categoria, $estado);
    $canais = parse_m3u_categoria_arquivo($raw_file, $categoria);
    $estado['loaded_categories'][$categoria] = $canais;
    $idx = array_search($categoria, $estado['categorias'], true);
    if ($idx !== false) $estado['next_index'] = ((int)$idx + 1) % max(1, count($estado['categorias']));
    salvar_estado_lazy($estado);
    return [
        'ok' => true,
        'categoria' => $categoria,
        'canais' => count($canais),
        'estado' => $estado,
        'progresso' => count($estado['loaded_categories']) . '/' . count($estado['categorias'])
    ];
}

function carregar_categoria_se_precisar_lazy($categoria) {
    $estado = carregar_estado_lazy();
    $categoria = categoria_real_lazy($categoria, $estado);
    if ($categoria !== '' && array_key_exists($categoria, $estado['loaded_categories'])) return ['ok' => true, 'categoria' => $categoria, 'estado' => $estado, 'cache' => true];
    return carregar_categoria_lazy($categoria, false);
}

function carregar_cache($validar_ttl = true) {
    global $config;
    $estado = carregar_estado_lazy();
    if ($validar_ttl && !empty($estado['timestamp']) && (time() - (int)$estado['timestamp']) > (int)$config['cache_ttl']) return null;
    $canais = [];
    foreach ($estado['loaded_categories'] as $cat => $lista) {
        foreach (normalizar_canais_lazy($lista) as $c) $canais[] = $c;
    }
    return [
        'categorias' => $estado['categorias'],
        'loaded_categories' => $estado['loaded_categories'],
        'loaded_count' => count($estado['loaded_categories']),
        'canais' => $canais,
        'total' => count($canais),
        'next_index' => $estado['next_index'],
        'raw' => file_exists(raw_m3u_file())
    ];
}

function salvar_cache($dados) {
    $estado = estado_lazy_vazio();
    if (is_array($dados) && !empty($dados['categorias'])) $estado['categorias'] = $dados['categorias'];
    if (is_array($dados) && !empty($dados['canais']) && is_array($dados['canais'])) {
        foreach ($dados['canais'] as $c) {
            $cat = (string)($c['grupo'] ?? 'Geral');
            if (!isset($estado['loaded_categories'][$cat])) $estado['loaded_categories'][$cat] = [];
            $estado['loaded_categories'][$cat][] = $c;
        }
    }
    salvar_estado_lazy($estado);
}


// PATCH_VOD_NAO_LIVE
function texto_minusculo_simples($txt) {
    $txt = strtolower((string)$txt);
    $map = [
        'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
        'ç'=>'c'
    ];
    return strtr($txt, $map);
}

function canal_eh_vod($c) {
    $url = (string)($c['url'] ?? '');
    $grupo = texto_minusculo_simples((string)($c['grupo'] ?? ''));
    $nome = texto_minusculo_simples((string)($c['nome'] ?? ''));

    if (preg_match('#/(movie|movies|series|serie|vod)/#i', $url)) return true;
    if (preg_match('#\.(mp4|mkv|avi|mov|m4v|webm)(\?|$)#i', $url)) return true;

    $grupo_trim = trim($grupo);
    $grupo_live = preg_match('/^(canais|24\s*horas|ao\s*vivo|live)\s*(\||:|-)/i', $grupo_trim);
    if (!$grupo_live && preg_match('/^(filmes|movies|vod|series|serie|séries)\s*(\||:|-)/i', $grupo_trim)) return true;
    if (!$grupo_live && preg_match('/(temporada|episodio|episódio)/i', $grupo_trim . ' ' . $nome)) return true;

    return false;
}

function vod_extensao($url) {
    $path = parse_url((string)$url, PHP_URL_PATH);
    $ext = strtolower(pathinfo((string)$path, PATHINFO_EXTENSION));
    if (!in_array($ext, ['mp4','mkv','avi','mov','m4v','webm','ts'], true)) $ext = 'mp4';
    return $ext;
}

function achar_canal($id) {
    $cache = carregar_cache(false);
    if (!$cache || empty($cache['canais'])) return null;
    foreach ($cache['canais'] as $c) {
        if (($c['id'] ?? '') === $id) return $c;
    }
    return null;
}

function cmd_ok($fn) {
    return function_exists($fn) && !in_array($fn, array_map('trim', explode(',', (string)ini_get('disable_functions'))), true);
}

function ffmpeg_bin() {
    global $config;
    $lista = [(string)$config['ffmpeg_path'], '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/usr/bin/ffmpeg-static', '/snap/bin/ffmpeg', '/bin/ffmpeg'];
    foreach ($lista as $bin) {
        if ($bin && is_file($bin) && is_executable($bin)) return $bin;
    }
    if (cmd_ok('exec')) {
        $out = [];
        @exec('command -v ffmpeg 2>/dev/null', $out);
        if (!empty($out[0]) && is_file(trim($out[0]))) return trim($out[0]);
        $out = [];
        @exec('which ffmpeg 2>/dev/null', $out);
        if (!empty($out[0]) && is_file(trim($out[0]))) return trim($out[0]);
    }
    if (cmd_ok('shell_exec')) {
        $achado = trim((string)@shell_exec('command -v ffmpeg 2>/dev/null'));
        if ($achado !== '' && is_file($achado)) return $achado;
    }
    return (string)$config['ffmpeg_path'];
}

function shell_saida($cmd) {
    if (cmd_ok('shell_exec')) {
        $out = @shell_exec($cmd);
        if (is_string($out) && $out !== '') return $out;
    }
    if (cmd_ok('exec')) {
        $linhas = [];
        @exec($cmd, $linhas);
        return implode("\n", $linhas);
    }
    return '';
}

function processo_ativo($pid) {
    $pid = (int)$pid;
    if ($pid <= 0) return false;
    if (function_exists('posix_kill')) return @posix_kill($pid, 0);
    if (cmd_ok('exec')) {
        @exec('kill -0 ' . $pid . ' 2>/dev/null', $out, $code);
        return $code === 0;
    }
    return false;
}

function parar_processo($pid) {
    $pid = (int)$pid;
    if ($pid <= 0 || !cmd_ok('exec')) return;
    @exec('kill -TERM ' . $pid . ' 2>/dev/null');
    usleep(300000);
    if (processo_ativo($pid)) @exec('kill -9 ' . $pid . ' 2>/dev/null');
}

function ultimo_log($file) {
    if (!file_exists($file)) return '';
    $size = (int)filesize($file);
    if ($size <= 0) return '';
    $fp = fopen($file, 'rb');
    if (!$fp) return '';
    $limit = 5000;
    if ($size > $limit) fseek($fp, -$limit, SEEK_END);
    $txt = fread($fp, $limit);
    fclose($fp);
    $txt = trim(preg_replace('/\s+/', ' ', (string)$txt));
    if (function_exists('mb_substr')) return mb_substr($txt, 0, 1500, 'UTF-8');
    return substr($txt, 0, 1500);
}

function esperar_hls($file, $segundos) {
    $fim = time() + max(8, (int)$segundos);
    $base = preg_replace('/\.m3u8$/', '', $file);

    while (time() < $fim) {
        clearstatcache(true, $file);
        $segs = glob($base . '_*.ts');

        if (file_exists($file) && filesize($file) > 30 && is_array($segs) && count($segs) >= 2) {
            return true;
        }

        usleep(250000);
    }

    return false;
}

function hls_esta_pronto($id) {
    global $config;
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$id);
    $file = $config['hls_path'] . $id . '.m3u8';
    $segs = glob($config['hls_path'] . $id . '_*.ts');
    return file_exists($file) && filesize($file) > 30 && is_array($segs) && count($segs) > 0;
}

function hls_ultimo_segmento($id) {
    global $config;
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$id);
    $segs = glob($config['hls_path'] . $id . '_*.ts');
    if (!is_array($segs) || count($segs) === 0) return null;
    usort($segs, function($a, $b) { return filemtime($b) <=> filemtime($a); });
    $f = $segs[0];
    return [
        'file' => $f,
        'nome' => basename($f),
        'mtime' => (int)filemtime($f),
        'idade' => time() - (int)filemtime($f),
        'size' => (int)filesize($f)
    ];
}

function hls_fresco($id, $max_age = null) {
    global $config;
    if (!hls_esta_pronto($id)) return false;
    $seg = hls_ultimo_segmento($id);
    if (!$seg) return false;
    $limite = $max_age !== null ? (int)$max_age : (int)$config['hls_stale_seconds'];
    return $seg['idade'] <= max(6, $limite) && $seg['size'] > 500;
}

function hls_tem_endlist($id) {
    global $config;
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$id);
    $file = $config['hls_path'] . $id . '.m3u8';
    if (!file_exists($file)) return false;
    $txt = (string)@file_get_contents($file);
    return stripos($txt, '#EXT-X-ENDLIST') !== false;
}

function hls_segmentos_count($id) {
    global $config;
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$id);
    $segs = glob($config['hls_path'] . $id . '_*.ts');
    return is_array($segs) ? count($segs) : 0;
}

function stream_info($id) {
    global $config;
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$id);
    $pid_file = $config['streams_path'] . $id . '.pid';
    $meta_file = $config['streams_path'] . $id . '.meta.json';
    $pid = file_exists($pid_file) ? (int)file_get_contents($pid_file) : 0;
    $meta = [];
    if (file_exists($meta_file)) {
        $tmp = json_decode((string)file_get_contents($meta_file), true);
        if (is_array($tmp)) $meta = $tmp;
    }
    $seg = hls_ultimo_segmento($id);
    return [
        'id' => $id,
        'pid' => $pid,
        'ativo' => $pid > 0 && processo_ativo($pid),
        'hls_pronto' => hls_esta_pronto($id),
        'hls_fresco' => hls_fresco($id),
        'ultimo_segmento' => $seg,
        'meta' => $meta
    ];
}

function registrar_stream_log($id, $texto) {
    global $config;
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$id);
    @file_put_contents($config['logs_path'] . 'watchdog.log', date('Y-m-d H:i:s') . ' | ' . $id . ' | ' . $texto . "\n", FILE_APPEND);
}

function garantir_segmento_aguarde() {
    global $config;
    $file = $config['hls_path'] . 'aguarde.ts';
    if (file_exists($file) && filesize($file) > 5000) return true;
    $ffmpeg = ffmpeg_bin();
    if (!is_file($ffmpeg) || !is_executable($ffmpeg) || !cmd_ok('exec')) return false;
    $log = $config['logs_path'] . 'aguarde.log';
    $cmd = escapeshellarg($ffmpeg) . ' -hide_banner -loglevel error -y ' .
        '-f lavfi -i ' . escapeshellarg('color=c=black:s=640x360:r=25') . ' ' .
        '-f lavfi -i ' . escapeshellarg('anullsrc=channel_layout=stereo:sample_rate=44100') . ' ' .
        '-t 1 -shortest -c:v libx264 -preset ultrafast -tune zerolatency -pix_fmt yuv420p ' .
        '-c:a aac -b:a 64k -f mpegts ' . escapeshellarg($file) . ' > ' . escapeshellarg($log) . ' 2>&1';
    @exec($cmd, $out, $code);
    return file_exists($file) && filesize($file) > 5000;
}

function hls_playlist_aguarde($id) {
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$id);
    $seq = max(0, time() % 100000);
    $base = app_base_url();
    if (garantir_segmento_aguarde()) {
        return "#EXTM3U\n" .
            "#EXT-X-VERSION:3\n" .
            "#EXT-X-TARGETDURATION:1\n" .
            "#EXT-X-MEDIA-SEQUENCE:" . $seq . "\n" .
            "#EXT-X-ALLOW-CACHE:NO\n" .
            "#EXTINF:1.000,\n" .
            $base . "/?acao=aguarde_ts&v=" . $seq . "\n";
    }
    return "#EXTM3U\n" .
        "#EXT-X-VERSION:3\n" .
        "#EXT-X-TARGETDURATION:2\n" .
        "#EXT-X-MEDIA-SEQUENCE:" . $seq . "\n" .
        "#EXT-X-ALLOW-CACHE:NO\n";
}

function servir_aguarde_ts() {
    global $config;
    if (!garantir_segmento_aguarde()) {
        http_response_code(404);
        texto_out('Aguarde indisponível');
    }
    $file = $config['hls_path'] . 'aguarde.ts';
    header('Content-Type: video/mp2t');
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}

function limpar_stream_arquivos($id) {
    global $config;
    $files = glob($config['hls_path'] . $id . '*');
    if (is_array($files)) foreach ($files as $f) @unlink($f);
}

function hls_playlist_publica($id) {
    global $config;

    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$id);
    $file = $config['hls_path'] . $id . '.m3u8';

    if (!file_exists($file) || filesize($file) < 10) return '';

    $base = app_base_url();
    $linhas = preg_split("/
|
|
/", (string)file_get_contents($file));
    $out = [];

    foreach ($linhas as $linha) {
        $l = trim($linha);
        if ($l === '') continue;

        if (stripos($l, '#EXT-X-PROGRAM-DATE-TIME:') === 0) continue;

        if ($l[0] === '#') {
            if (stripos($l, '#EXT-X-VERSION:') === 0) {
                $out[] = '#EXT-X-VERSION:3';
            } else {
                $out[] = $l;
            }
            continue;
        }

        $path = parse_url($l, PHP_URL_PATH);
        $seg = basename($path ?: $l);

        if (preg_match('/\.ts$/i', $seg)) {
            $out[] = $base . '/seg/' . rawurlencode($id) . '/' . rawurlencode($seg);
        } else {
            $out[] = $l;
        }
    }

    return implode("
", $out) . "
";
}

// CORREÇÃO: função melhorada para servir segmentos
function servir_segmento_hls($id, $seg) {
    global $config;
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$id);
    $seg = basename((string)$seg);
    
    if ($id === '' || $seg === '' || !preg_match('/^' . preg_quote($id, '/') . '[_\-].*\.ts$/', $seg)) {
        http_response_code(400);
        texto_out('Segmento inválido');
    }
    
    $file = $config['hls_path'] . $seg;
    if (!file_exists($file)) {
        $fim = microtime(true) + 2.5;
        while (microtime(true) < $fim && !file_exists($file)) {
            clearstatcache(true, $file);
            usleep(200000);
        }
    }

    if (!file_exists($file)) {
        http_response_code(404);
        texto_out('Segmento não encontrado');
    }
    
    header('Content-Type: video/mp2t');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: *');
    header('Cache-Control: public, max-age=3600, immutable');
    header('Content-Length: ' . filesize($file));
    header('Accept-Ranges: bytes');
    
    if (ob_get_level()) ob_end_clean();
    flush();
    
    readfile($file);
    exit;
}

// CORREÇÃO: comando FFmpeg melhorado
function origem_perfis_ffmpeg($url = '') {
    global $config;

    $referer = trim((string)($config['origin_referer'] ?? 'auto'));

    if ($referer === '' || strtolower($referer) === 'auto') {
        $u = parse_url((string)$url);
        if (!empty($u['scheme']) && !empty($u['host'])) {
            $referer = $u['scheme'] . '://' . $u['host'] . '/';
        } else {
            $referer = '';
        }
    }

    $base_headers = "Connection: keep-alive
Accept: */*
Icy-MetaData: 0
";

    $perfis = [
        [
            'nome' => 'vlc',
            'ua' => 'VLC/3.0.20 LibVLC/3.0.20',
            'headers' => $base_headers
        ],
        [
            'nome' => 'smarters',
            'ua' => 'Área dos EstudosSmartersPro',
            'headers' => "Connection: keep-alive
Accept: */*
Icy-MetaData: 0
"
        ],
        [
            'nome' => 'mozilla',
            'ua' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36',
            'headers' => "Connection: keep-alive
Accept: */*
Accept-Language: pt-BR,pt;q=0.9,en;q=0.8
"
        ],
        [
            'nome' => 'android',
            'ua' => 'Dalvik/2.1.0 (Linux; U; Android 10; TV Box)',
            'headers' => "Connection: keep-alive
Accept: */*
"
        ],
        [
            'nome' => 'ffmpeg',
            'ua' => 'Lavf/58.76.100',
            'headers' => $base_headers
        ]
    ];

    $custom_ua = trim((string)($config['origin_user_agent'] ?? ''));
    if ($custom_ua !== '') {
        array_unshift($perfis, [
            'nome' => 'custom',
            'ua' => $custom_ua,
            'headers' => $base_headers
        ]);
    }

    foreach ($perfis as $k => $p) {
        if ($referer !== '' && stripos($p['headers'], 'Referer:') === false) {
            $perfis[$k]['headers'] .= 'Referer: ' . $referer . "
";
            $perfis[$k]['headers'] .= 'Origin: ' . rtrim($referer, '/') . "
";
        }
    }

    $prefer = strtolower(trim((string)($config['origin_profile'] ?? 'auto')));
    if ($prefer !== '' && $prefer !== 'auto') {
        usort($perfis, function($a, $b) use ($prefer) {
            if (strtolower($a['nome']) === $prefer) return -1;
            if (strtolower($b['nome']) === $prefer) return 1;
            return 0;
        });
    }

    return $perfis;
}

function montar_cmd_ffmpeg_hls($ffmpeg, $url, $hls_file, $seg_pattern, $log_file, $modo = 'copy', $perfil = null, $tipo_stream = 'auto') {
    global $config;

    $tipo_stream = strtolower(trim((string)$tipo_stream));
    $eh_vod = ($tipo_stream === 'vod') || ($tipo_stream === 'auto' && canal_eh_vod(['url' => $url]));
    $map = '-map 0:v:0? -map 0:a:0? -dn -sn ';

    if ($modo === 'copy') {
        $codec = '-c:v copy -c:a copy ';
    } elseif ($modo === 'aac') {
        $codec = '-c:v copy -c:a aac -b:a 128k -ac 2 -ar 48000 ';
    } else {
        $codec = '-c:v libx264 -preset veryfast -tune zerolatency -pix_fmt yuv420p -b:v 2500k -maxrate 3200k -bufsize 6400k -c:a aac -b:a 128k -ac 2 -ar 48000 ';
    }

    if (!is_array($perfil)) {
        $tmp = origem_perfis_ffmpeg($url);
        $perfil = $tmp[0] ?? ['nome' => 'default', 'ua' => 'VLC/3.0', 'headers' => "Accept: */*
"];
    }

    $ua = (string)($perfil['ua'] ?? 'VLC/3.0');
    $headers = (string)($perfil['headers'] ?? "Accept: */*
");
    $headers_cli = str_replace(["
", "
"], ['\r', '\n'], $headers);

    $hls_time = max(1, (int)($config['hls_time'] ?? 2));
    $list_size = max(45, (int)($config['hls_list_size'] ?? 90));
    if ($eh_vod) $list_size = max($list_size, (int)($config['vod_hls_list_size'] ?? 180));
    $delete_threshold = max($list_size, (int)($config['hls_delete_threshold'] ?? 90));
    $delay = max(2, (int)($config['ffmpeg_reconnect_delay_max'] ?? 5));
    $rw_timeout = max(15000000, (int)(max(15, (int)($config['ffmpeg_rw_timeout_seconds'] ?? 45)) * 1000000));
    $realtime = ($eh_vod && !empty($config['vod_realtime'])) ? '-re ' : '';

    return 'nohup ' . escapeshellarg($ffmpeg) . ' ' .
        '-hide_banner -loglevel warning -nostdin -y ' .
        '-thread_queue_size 16384 ' .
        '-protocol_whitelist file,http,https,tcp,tls,crypto ' .
        '-rw_timeout ' . $rw_timeout . ' ' .
        '-reconnect 1 -reconnect_streamed 1 -reconnect_at_eof 1 -reconnect_on_network_error 1 ' .
        '-reconnect_on_http_error 4xx,5xx -reconnect_delay_max ' . $delay . ' ' .
        '-user_agent ' . escapeshellarg($ua) . ' ' .
        '-headers ' . escapeshellarg($headers_cli) . ' ' .
        $realtime .
        '-analyzeduration 10000000 -probesize 10000000 -fflags +genpts+discardcorrupt -err_detect ignore_err ' .
        '-i ' . escapeshellarg($url) . ' ' .
        $map . '-max_muxing_queue_size 16384 ' . $codec .
        '-flush_packets 1 -avoid_negative_ts make_zero -start_at_zero -muxpreload 0 -muxdelay 0 ' .
        '-f hls -hls_segment_type mpegts ' .
        '-hls_time ' . $hls_time . ' -hls_init_time 1 -hls_list_size ' . $list_size . ' -hls_delete_threshold ' . $delete_threshold . ' ' .
        '-hls_allow_cache 1 ' .
        '-hls_flags append_list+omit_endlist+independent_segments+temp_file ' .
        '-hls_segment_filename ' . escapeshellarg($seg_pattern) . ' ' .
        escapeshellarg($hls_file) . ' > ' . escapeshellarg($log_file) . ' 2>&1 & echo $!';
}

function iniciar_ffmpeg_hls($url, $nome = '', $id = '', $tipo_stream = 'auto') {
    global $config;

    $url = trim((string)$url);

    if (!preg_match('/^https?:\/\//i', $url)) {
        return ['ok' => false, 'erro' => 'URL inválida do canal'];
    }

    if (!cmd_ok('exec')) {
        return ['ok' => false, 'erro' => 'A função exec está desativada no PHP'];
    }

    $ffmpeg = ffmpeg_bin();

    if (!is_file($ffmpeg) || !is_executable($ffmpeg)) {
        return ['ok' => false, 'erro' => 'FFmpeg não encontrado em ' . $ffmpeg];
    }

    if (!is_writable($config['hls_path'])) {
        return ['ok' => false, 'erro' => 'Sem permissão de escrita na pasta hls'];
    }

    if (!is_writable($config['streams_path'])) {
        return ['ok' => false, 'erro' => 'Sem permissão de escrita na pasta streams'];
    }

    if (!is_writable($config['logs_path'])) {
        return ['ok' => false, 'erro' => 'Sem permissão de escrita na pasta logs'];
    }

    $tipo_stream = strtolower(trim((string)$tipo_stream));
    if ($tipo_stream !== 'live' && $tipo_stream !== 'vod') {
        $tipo_stream = canal_eh_vod(['url' => $url, 'nome' => $nome, 'grupo' => '']) ? 'vod' : 'live';
    }

    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', $id ?: substr(sha1($url), 0, 20));

    $hls_file = $config['hls_path'] . $id . '.m3u8';
    $pid_file = $config['streams_path'] . $id . '.pid';
    $log_file = $config['logs_path'] . $id . '.log';
    $hls_url = $config['hls_url'] . $id . '.m3u8';

    if (file_exists($pid_file)) {
        $pid = (int)file_get_contents($pid_file);

        if ($pid > 0 && processo_ativo($pid)) {
            if (hls_esta_pronto($id)) {
                if (hls_fresco($id) || esperar_hls($hls_file, 3) || ($tipo_stream === 'vod' && hls_tem_endlist($id))) {
                    return [
                        'ok' => true,
                        'id' => $id,
                        'url' => $hls_url,
                        'file' => $hls_file,
                        'pid' => $pid,
                        'tipo_stream' => $tipo_stream,
                        'mensagem' => 'Já ativo'
                    ];
                }

                registrar_stream_log($id, 'Processo ativo, mas HLS ficou velho. Reiniciando para evitar travar no player.');
                parar_processo($pid);
                @unlink($pid_file);
                limpar_stream_arquivos($id);
            } else {
                if (esperar_hls($hls_file, 6)) {
                    return [
                        'ok' => true,
                        'id' => $id,
                        'url' => $hls_url,
                        'file' => $hls_file,
                        'pid' => $pid,
                        'tipo_stream' => $tipo_stream,
                        'mensagem' => 'Aquecido'
                    ];
                }

                registrar_stream_log($id, 'Processo ativo sem gerar HLS. Reiniciando.');
                parar_processo($pid);
                @unlink($pid_file);
                limpar_stream_arquivos($id);
            }
        } else {
            @unlink($pid_file);
        }
    }

    if (hls_esta_pronto($id)) {
        if ($tipo_stream === 'vod' && hls_tem_endlist($id)) {
            return [
                'ok' => true,
                'id' => $id,
                'url' => $hls_url,
                'file' => $hls_file,
                'pid' => 0,
                'tipo_stream' => $tipo_stream,
                'mensagem' => 'VOD em cache HLS completo'
            ];
        }

        registrar_stream_log($id, 'HLS antigo encontrado sem processo válido. Limpando e subindo de novo.');
        limpar_stream_arquivos($id);
    }

    @unlink($log_file);

    $tentativas_codec = ((string)($config['ffmpeg_prefer_mode'] ?? 'aac') === 'copy')
        ? ['copy', 'aac', 'transcode']
        : ['aac', 'copy', 'transcode'];

    $perfis = origem_perfis_ffmpeg($url);

    $ultimo = '';

    foreach ($perfis as $perfil) {
        foreach ($tentativas_codec as $modo) {
            limpar_stream_arquivos($id);
            @unlink($log_file);

            @file_put_contents(
                $config['logs_path'] . 'tentativas_origem.log',
                date('Y-m-d H:i:s') . ' | ' . $id . ' | tipo=' . $tipo_stream . ' | perfil=' . ($perfil['nome'] ?? 'x') . ' | modo=' . $modo . ' | ' . $url . "
",
                FILE_APPEND
            );

            $cmd = montar_cmd_ffmpeg_hls(
                $ffmpeg,
                $url,
                $hls_file,
                $config['hls_path'] . $id . '_%06d.ts',
                $log_file,
                $modo,
                $perfil,
                $tipo_stream
            );

            @exec($cmd, $out, $code);

            $pid = isset($out[0]) ? (int)trim($out[0]) : 0;

            if ($pid <= 0) {
                $ultimo = ultimo_log($log_file) ?: 'FFmpeg não retornou PID';
                continue;
            }

            file_put_contents($pid_file, (string)$pid);

            if (esperar_hls($hls_file, (int)($config['hls_wait_seconds'] ?? 25))) {
                file_put_contents(
                    $config['streams_path'] . $id . '.meta.json',
                    json_encode([
                        'nome' => $nome,
                        'url' => $url,
                        'modo' => $modo,
                        'tipo_stream' => $tipo_stream,
                        'perfil_origem' => $perfil['nome'] ?? 'auto',
                        'inicio' => time(),
                        'last_start' => time(),
                        'robusto' => true
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                );

                return [
                    'ok' => true,
                    'id' => $id,
                    'url' => $hls_url,
                    'file' => $hls_file,
                    'pid' => $pid,
                    'modo' => $modo,
                    'tipo_stream' => $tipo_stream,
                    'perfil_origem' => $perfil['nome'] ?? 'auto',
                    'mensagem' => 'Iniciado'
                ];
            }

            $ultimo = ultimo_log($log_file);

            if (processo_ativo($pid)) {
                parar_processo($pid);
            }

            @unlink($pid_file);
        }
    }

    return [
        'ok' => false,
        'erro' => 'Arquivo HLS não foi criado',
        'detalhe' => $ultimo ?: 'A origem pode ter bloqueado a VPS, o IP ou exigir outro formato.'
    ];
}


function canais_por_categoria($categoria) {
    $cache = carregar_cache(false);
    if (!$cache || empty($cache['canais'])) return [];
    $out = [];
    foreach ($cache['canais'] as $c) {
        if (strcasecmp((string)$c['grupo'], (string)$categoria) === 0) $out[] = $c;
    }
    return $out;
}

// CORREÇÃO: tratar OPTIONS para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: *');
    http_response_code(200);
    exit;
}


// ROTAS COMPATIVEIS COM PLAYERS ANTIGOS
$__uri_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

if (preg_match('#^/seg/([a-zA-Z0-9_-]+)/([^/]+\.ts)$#', $__uri_path, $__m)) {
    servir_segmento_hls($__m[1], $__m[2]);
}


if (preg_match('#^/vod/([a-zA-Z0-9_-]+)\.([a-zA-Z0-9]{2,5})$#', $__uri_path, $__m)) {
    $_GET['acao'] = 'vod';
    $_GET['id'] = $__m[1];
}

if (preg_match('#^/play/([a-zA-Z0-9_-]+)\.m3u8$#', $__uri_path, $__m)) {
    $_GET['acao'] = 'play';
    $_GET['id'] = $__m[1];
}

$acao = $_GET['acao'] ?? '';


// AREA_TOKENS_M3U_HELPERS
function area_tokens_file() {
    return __DIR__ . '/area_tokens_m3u.json';
}

function area_tokens_rev_file() {
    return __DIR__ . '/area_tokens_revogados.json';
}

function area_tokens_load($file) {
    if (!file_exists($file)) return [];
    $j = json_decode((string)file_get_contents($file), true);
    return is_array($j) ? $j : [];
}

function area_tokens_save($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function area_tokens_hash($token) {
    return sha1((string)$token);
}

function area_tokens_decode($token) {
    $p = explode('.', (string)$token);
    if (count($p) !== 3) return [];
    $data = strtr($p[1], '-_', '+/');
    $pad = strlen($data) % 4;
    if ($pad) $data .= str_repeat('=', 4 - $pad);
    $json = base64_decode($data);
    $arr = json_decode((string)$json, true);
    return is_array($arr) ? $arr : [];
}

function area_token_register($token, $categoria = '') {
    $token = trim((string)$token);
    if ($token === '') return false;

    $payload = area_tokens_decode($token);
    if (($payload['tipo'] ?? '') !== 'm3u_restream') return false;

    $categoria = $categoria ?: (string)($payload['categoria'] ?? '');
    $hash = area_tokens_hash($token);
    $base = function_exists('app_base_url') ? app_base_url() : '';

    $tokens = area_tokens_load(area_tokens_file());
    $tokens[$hash] = [
        'token' => $token,
        'categoria' => $categoria,
        'created_at' => date('Y-m-d H:i:s'),
        'exp' => (int)($payload['exp'] ?? 0),
        'url' => rtrim($base, '/') . '/?acao=m3u_restream&token=' . rawurlencode($token)
    ];

    area_tokens_save(area_tokens_file(), $tokens);
    return true;
}

function area_token_revogado($token) {
    $token = trim((string)$token);
    if ($token === '') return false;

    $rev = area_tokens_load(area_tokens_rev_file());
    return isset($rev[area_tokens_hash($token)]);
}



// PATCH_CONFIG_FONTE_IP8088
function ip8088_limpar_runtime_total($limpar_cache=true) {
    global $config;

    foreach (['hls_path','streams_path','logs_path'] as $k) {
        if (empty($config[$k])) continue;
        $files = glob(rtrim($config[$k], '/\\') . '/*');
        if (is_array($files)) {
            foreach ($files as $f) {
                if (is_file($f)) @unlink($f);
            }
        }
    }

    foreach (['pids','data'] as $dir) {
        $path = __DIR__ . '/' . $dir;
        if (is_dir($path)) {
            $files = glob($path . '/*');
            if (is_array($files)) {
                foreach ($files as $f) {
                    if (is_file($f)) @unlink($f);
                }
            }
        }
    }

    if ($limpar_cache) {
        foreach ([
            __DIR__ . '/cache_streams_lazy.json',
            __DIR__ . '/raw_m3u_origem.txt',
            __DIR__ . '/cache_streams.json',
            __DIR__ . '/links_m3u_salvos.json'
        ] as $f) {
            if (file_exists($f)) @unlink($f);
        }
    }

    return true;
}

function ip8088_salvar_config_json($novo) {
    global $config;

    $cfg_file = __DIR__ . '/config.json';
    $atual = [];

    if (file_exists($cfg_file)) {
        $tmp = json_decode((string)file_get_contents($cfg_file), true);
        if (is_array($tmp)) $atual = $tmp;
    }

    $cfg = array_merge($atual, $novo);
    file_put_contents($cfg_file, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $cfg;
}

if ($acao === 'config_atual_painel') {
    json_out([
        'ok' => true,
        'url' => $config['url'] ?? '',
        'user' => $config['user'] ?? '',
        'pass' => $config['pass'] ?? '',
        'base_url' => $config['base_url'] ?? 'http://38.100.203.66:8088',
        'token_ttl' => (int)($config['token_ttl'] ?? 2592000),
        'token_dias' => round(((int)($config['token_ttl'] ?? 2592000)) / 86400)
    ]);
}

if ($acao === 'salvar_origem_reset') {
    $url = trim((string)($_POST['url'] ?? $_GET['url'] ?? ''));
    $user = trim((string)($_POST['user'] ?? $_GET['user'] ?? ''));
    $pass = trim((string)($_POST['pass'] ?? $_GET['pass'] ?? ''));
    $base_url = trim((string)($_POST['base_url'] ?? $_GET['base_url'] ?? 'http://38.100.203.66:8088'));
    $token_2m = (string)($_POST['token_2m'] ?? $_GET['token_2m'] ?? '0');

    if ($url === '' || $user === '' || $pass === '') {
        json_out(['ok' => false, 'erro' => 'Preencha DNS, usuário e senha']);
    }

    $base_url = rtrim($base_url ?: 'http://38.100.203.66:8088', '/');
    $dias = $token_2m === '1' ? 60 : 30;

    $novo = [
        'url' => rtrim($url, '/'),
        'user' => $user,
        'pass' => $pass,
        'base_url' => $base_url,
        'hls_url' => $base_url . '/hls/',
        'dominio' => preg_replace('#^https?://#', '', $base_url),
        'token_ttl' => 86400 * $dias,
        'ffmpeg_path' => $config['ffmpeg_path'] ?? '/usr/bin/ffmpeg',
        'ffmpeg' => $config['ffmpeg'] ?? '/usr/bin/ffmpeg',
        'hls_wait_seconds' => 18,
        'hls_time' => 2,
        'hls_list_size' => 90,
        'hls_delete_threshold' => 90,
        'hls_stale_seconds' => 20,
        'ffmpeg_reconnect_delay_max' => 4,
        'ffmpeg_rw_timeout_seconds' => 35,
        'vod_realtime' => 1,
        'vod_hls_list_size' => 180,
        'ffmpeg_prefer_mode' => 'aac',
        'modo_velocidade_online' => 1,
        'stream_idle_stop_seconds' => 0
    ];

    ip8088_salvar_config_json($novo);
    ip8088_limpar_runtime_total(true);

    json_out([
        'ok' => true,
        'mensagem' => 'Nova fonte salva. Cache/HLS/lista antiga foram limpos.',
        'token_dias' => $dias
    ]);
}

if ($acao === 'limpar_hls_runtime') {
    ip8088_limpar_runtime_total(false);
    json_out(['ok' => true, 'mensagem' => 'HLS, streams e logs limpos. Cache da lista mantido.']);
}

if ($acao === 'token_ttl_update') {
    $dias = (int)($_POST['dias'] ?? $_GET['dias'] ?? 30);
    if (!in_array($dias, [30, 60], true)) $dias = 30;

    ip8088_salvar_config_json([
        'token_ttl' => 86400 * $dias,
        'base_url' => $config['base_url'] ?? 'http://38.100.203.66:8088',
        'hls_url' => rtrim(($config['base_url'] ?? 'http://38.100.203.66:8088'), '/') . '/hls/',
        'dominio' => preg_replace('#^https?://#', '', ($config['base_url'] ?? 'http://38.100.203.66:8088'))
    ]);

    json_out(['ok' => true, 'mensagem' => 'Token ajustado para ' . $dias . ' dias.', 'token_dias' => $dias]);
}



if ($acao === 'categorias_cards') {
    $estado = carregar_estado_lazy();
    $cards = [];

    foreach (($estado['loaded_categories'] ?? []) as $cat => $lista) {
        $lista = normalizar_canais_lazy($lista);
        $logo = '';

        foreach ($lista as $c) {
            if (!empty($c['logo'])) {
                $logo = (string)$c['logo'];
                break;
            }
        }

        $cards[] = [
            'categoria' => (string)$cat,
            'canais' => count($lista),
            'logo' => $logo
        ];
    }

    json_out([
        'ok' => true,
        'cards' => $cards,
        'total' => count($cards),
        'categorias_total' => count($estado['categorias'] ?? []),
        'progresso' => count($estado['loaded_categories'] ?? []) . '/' . count($estado['categorias'] ?? [])
    ]);
}



if ($acao === 'teste') {
    $ffmpeg = ffmpeg_bin();
    $versao = shell_saida(escapeshellarg($ffmpeg) . ' -version 2>&1');
    json_out([
        'ok' => true,
        'php' => PHP_VERSION,
        'exec' => cmd_ok('exec'),
        'ffmpeg' => strpos($versao, 'ffmpeg version') !== false ? 'instalado' : 'não encontrado',
        'ffmpeg_path' => $ffmpeg,
        'hls_writable' => is_writable($config['hls_path']),
        'streams_writable' => is_writable($config['streams_path']),
        'logs_writable' => is_writable($config['logs_path'])
    ]);
}

if ($acao === 'carregar_lista') {
    $res = carregar_categoria_lazy('', false);
    if (!$res['ok']) json_out(['erro' => $res['erro'] ?? 'Falha ao carregar categoria']);
    if (!empty($res['fim'])) json_out([
        'ok' => true,
        'fim' => true,
        'mensagem' => $res['mensagem'],
        'progresso' => $res['progresso'],
        'categorias_total' => count($res['estado']['categorias'] ?? []),
        'categorias_carregadas' => count($res['estado']['loaded_categories'] ?? [])
    ]);
    json_out([
        'ok' => true,
        'lazy' => true,
        'categoria' => $res['categoria'],
        'canais' => $res['canais'],
        'progresso' => $res['progresso'],
        'categorias_total' => count($res['estado']['categorias'] ?? []),
        'categorias_carregadas' => count($res['estado']['loaded_categories'] ?? [])
    ]);
}

if ($acao === 'carregar_categoria') {
    $categoria = trim($_GET['categoria'] ?? '');
    if ($categoria === '') json_out(['erro' => 'Selecione uma categoria']);
    $res = carregar_categoria_lazy($categoria, false);
    if (!$res['ok']) json_out(['erro' => $res['erro'] ?? 'Falha ao carregar categoria']);
    json_out([
        'ok' => true,
        'lazy' => true,
        'categoria' => $res['categoria'] ?? $categoria,
        'canais' => $res['canais'] ?? 0,
        'progresso' => $res['progresso'] ?? '',
        'categorias_total' => count($res['estado']['categorias'] ?? []),
        'categorias_carregadas' => count($res['estado']['loaded_categories'] ?? [])
    ]);
}

if ($acao === 'categorias') {
    $estado = carregar_estado_lazy();
    if (empty($estado['categorias']) && file_exists(raw_m3u_file()) && filesize(raw_m3u_file()) > 20) {
        $estado['categorias'] = extrair_categorias_m3u_arquivo(raw_m3u_file());
        $estado['raw_timestamp'] = filemtime(raw_m3u_file()) ?: time();
        salvar_estado_lazy($estado);
    }
    if (empty($estado['categorias'])) json_out(['erro' => 'Clique em Atualizar próxima categoria para iniciar o modo lazy.']);
    $loaded = [];
    foreach ($estado['loaded_categories'] as $cat => $lista) $loaded[$cat] = count($lista);
    json_out([
        'categorias' => $estado['categorias'],
        'loaded' => $loaded,
        'loaded_count' => count($loaded),
        'total_categorias' => count($estado['categorias']),
        'total' => array_sum($loaded),
        'progresso' => count($loaded) . '/' . count($estado['categorias'])
    ]);
}

if ($acao === 'gerar_link') {
    $categoria = trim($_GET['categoria'] ?? '');
    if ($categoria === '') json_out(['erro' => 'Categoria obrigatória']);
    $load = carregar_categoria_se_precisar_lazy($categoria);
    if (!$load['ok']) json_out(['erro' => $load['erro'] ?? 'Falha ao carregar categoria']);
    $categoria = $load['categoria'] ?? categoria_real_lazy($categoria);
    $canais = canais_por_categoria($categoria);
    if (!$canais) json_out(['erro' => 'Nenhum canal encontrado nessa categoria']);
    $token = gerar_token(['tipo' => 'm3u_restream', 'categoria' => $categoria]);
    // AREA_TOKEN_REGISTER_GERAR_LINK
    if (function_exists('area_token_register')) area_token_register($token, $categoria);
    $url = app_base_url() . '/?acao=m3u_restream&token=' . urlencode($token);
    json_out(['ok' => true, 'url' => $url, 'categoria' => $categoria, 'canais' => count($canais), 'lazy' => true]);
}

if ($acao === 'testar_primeiro_canal') {
    $categoria = trim($_GET['categoria'] ?? '');
    if ($categoria === '') json_out(['erro' => 'Categoria obrigatória']);
    $load = carregar_categoria_se_precisar_lazy($categoria);
    if (!$load['ok']) json_out(['erro' => $load['erro'] ?? 'Falha ao carregar categoria']);
    $categoria = $load['categoria'] ?? categoria_real_lazy($categoria);
    $canais = canais_por_categoria($categoria);
    if (!$canais) json_out(['erro' => 'Nenhum canal encontrado nessa categoria']);
    $c = $canais[0];
    $res = iniciar_ffmpeg_hls($c['url'], $c['nome'], $c['id'], canal_eh_vod($c) ? 'vod' : 'live');
    $playlist = $res['ok'] ? hls_playlist_publica($res['id']) : '';
    json_out([
        'ok' => (bool)$res['ok'],
        'canal' => $c['nome'],
        'id' => $c['id'],
        'url_hls' => app_base_url() . '/index.php?acao=play&id=' . rawurlencode($c['id']) . '&token=' . urlencode(gerar_token(['tipo'=>'play','id'=>$c['id']])) . '&arquivo=' . rawurlencode($c['id'] . '.m3u8'),
        'mensagem' => $res['mensagem'] ?? '',
        'aguardando' => !empty($res['aguardando']),
        'playlist_pronta' => $playlist !== '',
        'erro' => $res['ok'] ? '' : ($res['erro'] ?? 'falha'),
        'detalhe' => $res['detalhe'] ?? '',
        'log' => ultimo_log($config['logs_path'] . $c['id'] . '.log')
    ]);
}

if ($acao === 'm3u_restream') {
    // AREA_TOKEN_CHECK_M3U_RESTREAM
    $token_m3u_req = $_GET['token'] ?? '';
    if (function_exists('area_token_revogado') && area_token_revogado($token_m3u_req)) texto_out('Token removido');
    $payload = validar_token($token_m3u_req);
    if (!$payload || ($payload['tipo'] ?? '') !== 'm3u_restream') texto_out('Token inválido');
    // AREA_TOKEN_REGISTER_M3U_RESTREAM
    if (function_exists('area_token_register')) area_token_register($token_m3u_req, $payload['categoria'] ?? '');
    $categoria = $payload['categoria'] ?? '';
    $load = carregar_categoria_se_precisar_lazy($categoria);
    if (!$load['ok']) texto_out('Falha ao carregar categoria: ' . ($load['erro'] ?? 'erro'));
    $categoria = $load['categoria'] ?? categoria_real_lazy($categoria);
    $canais = canais_por_categoria($categoria);
    if (!$canais) texto_out('Nenhum canal encontrado. Recarregue essa categoria.');

    $m3u = "#EXTM3U\n";
    foreach ($canais as $c) {
        $eh_vod = canal_eh_vod($c);
        $tipo_token = $eh_vod ? 'vod' : 'play';
        $token = gerar_token(['tipo' => $tipo_token, 'id' => $c['id']]);
        $rota = $eh_vod ? '/vod/' : '/play/';
        $play = app_base_url() . $rota . rawurlencode($c['id']) . '.m3u8?token=' . urlencode($token);
        $m3u .= '#EXTINF:-1 tvg-name="' . m3u_attr($c['nome']) . '" tvg-logo="' . m3u_attr($c['logo']) . '" group-title="' . m3u_attr($c['grupo']) . '",' . m3u_attr($c['nome']) . "\n";
        $m3u .= $play . "\n";
    }

    header('Content-Type: audio/x-mpegurl; charset=utf-8');
    header('Content-Disposition: inline; filename="restream_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $categoria) . '.m3u"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo $m3u;
    exit;
}

if ($acao === 'aguarde_ts') {
    servir_aguarde_ts();
}

// CORREÇÃO: ação para servir segmentos
if ($acao === 'seg') {
    servir_segmento_hls($_GET['id'] ?? '', $_GET['seg'] ?? '');
}

// CORREÇÃO: ação play com auto-regeneração de token

if ($acao === 'vod') {
    $token = $_GET['token'] ?? '';
    $payload = validar_token($token);
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_GET['id'] ?? ''));

    if (!$payload || ($payload['tipo'] ?? '') !== 'vod') {
        if ($id) {
            $novo_token = gerar_token(['tipo' => 'vod', 'id' => $id]);
            header('Location: ' . app_base_url() . '/vod/' . rawurlencode($id) . '.m3u8?token=' . urlencode($novo_token));
            exit;
        }
        texto_out('Token inválido');
    }

    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($payload['id'] ?? $id));
    $canal = achar_canal($id);

    if (!$canal) {
        texto_out('VOD não encontrado no cache. Recarregue a categoria.');
    }

    @file_put_contents(
        $config['logs_path'] . 'hits_vod.log',
        date('Y-m-d H:i:s') . " | VOD_FFMPEG | " . ($canal['nome'] ?? '') . " | " . $id . " | " . ($_SERVER['HTTP_USER_AGENT'] ?? '') . "\n",
        FILE_APPEND
    );

    if (function_exists('stream_touch')) {
        stream_touch($id);
    }

    $res = iniciar_ffmpeg_hls($canal['url'], $canal['nome'], $canal['id'], canal_eh_vod($canal) ? 'vod' : 'live');

    header('Content-Type: application/vnd.apple.mpegurl; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: *');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('X-Accel-Buffering: no');

    if (!$res['ok']) {
        @file_put_contents(
            $config['logs_path'] . 'ultimo_erro.txt',
            date('Y-m-d H:i:s') . " | VOD_FFMPEG | " . ($canal['nome'] ?? '') . " | " . ($res['erro'] ?? 'falha') . " | " . ($res['detalhe'] ?? '') . "\n",
            FILE_APPEND
        );
        echo hls_playlist_aguarde($canal['id']);
        exit;
    }

    if (!empty($res['aguardando'])) {
        esperar_hls($config['hls_path'] . $res['id'] . '.m3u8', 10);
    }

    $playlist = hls_playlist_publica($res['id']);

    if ($playlist === '') {
        echo hls_playlist_aguarde($res['id']);
        exit;
    }

    echo $playlist;
    exit;
}


if ($acao === 'play') {
    $token = $_GET['token'] ?? '';
    $payload = validar_token($token);
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_GET['id'] ?? ''));
    
    // Se token inválido, gera novo automaticamente
    if (!$payload || ($payload['tipo'] ?? '') !== 'play') {
        if ($id) {
            $novo_token = gerar_token(['tipo' => 'play', 'id' => $id]);
            header('Location: ?acao=play&id=' . urlencode($id) . '&token=' . urlencode($novo_token));
            exit;
        }
        texto_out('Token inválido');
    }
    
    $id = $payload['id'] ?? $id;
    $canal = achar_canal($id);
    if (!$canal) texto_out('Canal não encontrado no cache. Recarregue a lista.');
    file_put_contents($config['logs_path'] . 'hits_play.log', date('Y-m-d H:i:s') . " | PLAY | " . $canal['nome'] . " | " . $canal['id'] . " | " . ($_SERVER['HTTP_USER_AGENT'] ?? '') . "\n", FILE_APPEND);
    $res = iniciar_ffmpeg_hls($canal['url'], $canal['nome'], $canal['id'], canal_eh_vod($canal) ? 'vod' : 'live');
    header('Content-Type: application/vnd.apple.mpegurl; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: *');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('X-Accel-Buffering: no');
    if (!$res['ok']) {
        file_put_contents($config['logs_path'] . 'ultimo_erro.txt', date('Y-m-d H:i:s') . " | " . $canal['nome'] . " | " . ($res['erro'] ?? 'falha') . " | " . ($res['detalhe'] ?? '') . "\n", FILE_APPEND);
        echo hls_playlist_aguarde($canal['id']);
        exit;
    }
    if (!empty($res['aguardando'])) {
        esperar_hls($config['hls_path'] . $res['id'] . '.m3u8', 10);
    }
    $playlist = hls_playlist_publica($res['id']);
    if ($playlist === '') {
        file_put_contents($config['logs_path'] . 'ultimo_erro.txt', date('Y-m-d H:i:s') . " | " . $canal['nome'] . " | aguardando playlist | " . ($res['detalhe'] ?? '') . "\n", FILE_APPEND);
        echo hls_playlist_aguarde($res['id']);
        exit;
    }
    echo $playlist;
    exit;
}

if ($acao === 'restream_iniciar') {
    $url = trim($_GET['url'] ?? '');
    $nome = trim($_GET['nome'] ?? 'manual');
    if ($url === '') json_out(['erro' => 'URL obrigatória']);
    $id = substr(sha1($url . '|' . $nome), 0, 20);
    $res = iniciar_ffmpeg_hls($url, $nome, $id, 'live');
    json_out($res['ok'] ? array_merge(['ok' => true, 'stream_id' => $res['id']], $res) : ['erro' => $res['erro'], 'detalhe' => $res['detalhe'] ?? '']);
}

if ($acao === 'restream_parar') {
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['stream_id'] ?? '');
    if ($id === '') json_out(['erro' => 'Stream ID obrigatório']);
    $pid_file = $config['streams_path'] . $id . '.pid';
    if (file_exists($pid_file)) {
        parar_processo((int)file_get_contents($pid_file));
        @unlink($pid_file);
    }
    limpar_stream_arquivos($id);
    @unlink($config['logs_path'] . $id . '.log');
    json_out(['ok' => true, 'mensagem' => 'Parado']);
}

if ($acao === 'restream_listar') {
    $streams = [];
    $files = glob($config['hls_path'] . '*.m3u8');
    if (!is_array($files)) $files = [];
    foreach ($files as $file) {
        $id = pathinfo($file, PATHINFO_FILENAME);
        $pid_file = $config['streams_path'] . $id . '.pid';
        $pid = file_exists($pid_file) ? (int)file_get_contents($pid_file) : 0;
        $streams[] = [
            'id' => $id,
            'url' => $config['hls_url'] . $id . '.m3u8',
            'ativo' => processo_ativo($pid),
            'pid' => $pid,
            'criado' => date('d/m/Y H:i:s', filemtime($file))
        ];
    }
    json_out(['streams' => $streams, 'total' => count($streams)]);
}

if ($acao === 'ultimo_log') {
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['id'] ?? '');
    $file = $id ? $config['logs_path'] . $id . '.log' : $config['logs_path'] . 'ultimo_erro.txt';
    json_out(['ok' => true, 'log' => ultimo_log($file)]);
}

if ($acao === 'watchdog') {
    $reiniciados = [];
    $ok = [];
    $metas = glob($config['streams_path'] . '*.meta.json');
    if (is_array($metas)) {
        foreach ($metas as $meta_file) {
            $id = basename($meta_file, '.meta.json');
            $meta = json_decode((string)@file_get_contents($meta_file), true);
            if (!is_array($meta) || empty($meta['url'])) continue;
            $info = stream_info($id);
            if (!$info['ativo'] || !$info['hls_fresco']) {
                registrar_stream_log($id, 'Watchdog reiniciando. ativo=' . ($info['ativo'] ? '1' : '0') . ' fresco=' . ($info['hls_fresco'] ? '1' : '0'));
                if ($info['pid'] > 0) parar_processo($info['pid']);
                @unlink($config['streams_path'] . $id . '.pid');
                limpar_stream_arquivos($id);
                $res = iniciar_ffmpeg_hls((string)$meta['url'], (string)($meta['nome'] ?? $id), $id, (string)($meta['tipo_stream'] ?? 'auto'));
                $reiniciados[] = ['id' => $id, 'ok' => !empty($res['ok']), 'mensagem' => $res['mensagem'] ?? ($res['erro'] ?? '')];
            } else {
                $ok[] = $id;
            }
        }
    }
    json_out(['ok' => true, 'ativos_ok' => count($ok), 'reiniciados' => $reiniciados, 'total_metas' => is_array($metas) ? count($metas) : 0]);
}

if ($acao === 'status') {
    $cache = carregar_cache(false);
    $estado = carregar_estado_lazy();
    $files = glob($config['hls_path'] . '*.m3u8');
    $ffmpeg = ffmpeg_bin();
    $versao = shell_saida(escapeshellarg($ffmpeg) . ' -version 2>&1');
    $total_categorias = count($estado['categorias'] ?? []);
    $carregadas = count($estado['loaded_categories'] ?? []);
    json_out([
        'cache' => file_exists(raw_m3u_file()) && $total_categorias > 0,
        'canais' => $cache ? count($cache['canais']) : 0,
        'categorias' => $total_categorias,
        'categorias_carregadas' => $carregadas,
        'progresso' => $carregadas . '/' . $total_categorias,
        'streams_ativos' => is_array($files) ? count($files) : 0,
        'ffmpeg' => strpos($versao, 'ffmpeg version') !== false ? 'instalado' : 'não encontrado',
        'exec' => cmd_ok('exec'),
        'hls_ok' => is_writable($config['hls_path'])
    ]);
}

if ($acao === 'limpar_cache') {
    @unlink(cache_file());
    @unlink(raw_m3u_file());
    @unlink(__DIR__ . '/cache_streams.json');
    json_out(['ok' => true]);
}

if ($acao === 'limpar_restreams') {
    $files = glob($config['streams_path'] . '*.pid');
    if (is_array($files)) {
        foreach ($files as $pid_file) {
            parar_processo((int)file_get_contents($pid_file));
            @unlink($pid_file);
        }
    }
    $hls = glob($config['hls_path'] . '*');
    if (is_array($hls)) foreach ($hls as $f) @unlink($f);
    $logs = glob($config['logs_path'] . '*');
    if (is_array($logs)) foreach ($logs as $f) @unlink($f);
    json_out(['ok' => true]);
}
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Área dos Estudos</title>
<style>
*{box-sizing:border-box}
body{margin:0;background:#06101f;color:#eaf3ff;font-family:Arial,Helvetica,sans-serif}
.wrap{max-width:1280px;margin:0 auto;padding:18px}
.top{
    position:relative;
    top:auto;
    z-index:10;
    background:rgba(6,16,31,.96);
    backdrop-filter:none;
    border-bottom:1px solid #17365c;
    padding:12px 0 16px;
}
.hero{
    background:linear-gradient(135deg,#0c1b31,#0a172a);
    border:1px solid #1e4778;
    border-radius:18px;
    padding:18px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
    box-shadow:0 20px 50px rgba(0,0,0,.28)
}
h1{margin:0;font-size:27px}
.sub{font-size:13px;color:#8fb4dc;margin-top:5px}
.row{display:flex;gap:9px;flex-wrap:wrap;align-items:center}
.btn{
    border:0;border-radius:12px;padding:11px 15px;
    color:#fff;font-weight:800;cursor:pointer;
    background:#2563eb
}
.btn:hover{filter:brightness(1.12)}
.btn.green{background:#059669}
.btn.red{background:#dc2626}
.btn.gray{background:#334155}
.btn.cyan{background:#0891b2}
.btn.small{padding:8px 11px;border-radius:10px;font-size:12px}
.stats{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin:14px 0}
.stat{background:#0a1b31;border:1px solid #1e4778;border-radius:15px;padding:12px}
.stat span{display:block;color:#8fb4dc;font-size:12px}
.stat b{display:block;margin-top:4px;font-size:22px}
.panel{
    background:#0a1b31;
    border:1px solid #1e4778;
    border-radius:18px;
    padding:16px;
    margin-bottom:14px
}
.panel h2{font-size:18px;margin:0 0 12px}
.progress{height:7px;background:#09213d;border:1px solid #1d4773;border-radius:999px;overflow:hidden;margin-top:11px}
.progress div{height:100%;background:#2563eb;width:0%}
.msg{font-size:13px;color:#9fc3ec;margin-top:8px;min-height:18px}
.ok{color:#6ee7b7}.err{color:#fecaca}
.autoBox{
    margin-top:12px;
    background:#071529;
    border:1px solid #1f4774;
    border-radius:14px;
    padding:12px
}
.autoBox label{font-size:12px;color:#9fc3ec}
.autoBox input{
    width:78px;background:#061225;color:#fff;border:1px solid #244e7c;
    border-radius:10px;padding:8px 9px;outline:none;font-weight:800
}
.autoState{font-size:12px;color:#facc15;font-weight:900}
.cards{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(150px,1fr));
    gap:14px;
    margin-top:14px
}
.cat-card{
    position:relative;
    min-height:152px;
    background:linear-gradient(180deg,#0c213b,#09182d);
    border:1px solid #1f5084;
    border-radius:17px;
    padding:12px;
    cursor:pointer;
    transition:.16s transform,.16s border,.16s box-shadow;
    overflow:hidden
}
.cat-card:hover{
    transform:translateY(-3px);
    border-color:#38bdf8;
    box-shadow:0 12px 30px rgba(14,165,233,.18)
}
.logoBox{
    height:64px;
    border:1px solid #1f4774;
    border-radius:12px;
    background:#0a1425;
    display:flex;
    align-items:center;
    justify-content:center;
    overflow:hidden;
    margin-bottom:10px
}
.logoBox img{max-width:100%;max-height:100%;object-fit:contain}
.placeholder{font-size:28px}
.badge-salvo{
    position:absolute;right:9px;top:8px;
    background:#10b981;color:white;
    font-weight:900;font-size:11px;
    padding:4px 8px;border-radius:999px
}
.cat-title{font-weight:900;font-size:14px;line-height:1.15;margin-bottom:8px}
.cat-info{font-size:12px;color:#9fbce1}
.empty{
    color:#9fbce1;
    background:#0a1b31;
    border:1px dashed #2a5b91;
    border-radius:15px;
    padding:20px;
    text-align:center
}
.modalBg{
    position:fixed;inset:0;background:rgba(0,0,0,.66);
    display:none;align-items:center;justify-content:center;
    z-index:99;padding:18px
}
.modal{
    width:min(680px,100%);
    background:#081a31;
    border:1px solid #38bdf8;
    border-radius:20px;
    padding:20px;
    box-shadow:0 25px 70px rgba(0,0,0,.55)
}
.urlBox{
    background:#061225;border:1px solid #244e7c;
    border-radius:14px;padding:13px;
    color:#facc15;
    word-break:break-all;
    font-size:13px;
    line-height:1.45;
    margin:12px 0
}
.side{
    display:grid;
    grid-template-columns:2fr 1fr;
    gap:14px
}
.listItem{
    background:#071529;border:1px solid #1f4774;
    border-radius:13px;padding:11px;margin-bottom:9px
}
@media(max-width:850px){
    .stats{grid-template-columns:repeat(2,1fr)}
    .side{grid-template-columns:1fr}
    .hero{align-items:flex-start;flex-direction:column}
}
</style>
</head>
<body>
<div class="top">
  <div class="wrap" style="padding-top:0;padding-bottom:0">
    <div class="hero">
      <div>
        <h1>📘 Área dos Estudos</h1>
        <div class="sub">Categorias em cards com ícones • clique no card para gerar/copiar o link de estudo</div>
      </div>
      <div class="row">
        <button class="btn green" onclick="atualizarProxima()">Atualizar próxima categoria</button>
        <button class="btn cyan" onclick="toggleAutoCategorias()" id="autoBtnTop">Auto carregar</button>
        <button class="btn gray" onclick="carregarTudoCategorias()">Carregar tudo</button>
        <button class="btn gray" onclick="carregarCards()">Recarregar cards</button>
        <button class="btn red" onclick="limparCache()">Limpar Cache</button>
      </div>
    </div>

    <div class="stats">
      <div class="stat"><span>Cache origem</span><b id="cacheStatus">---</b></div>
      <div class="stat"><span>Categorias</span><b id="catStatus">0/0</b></div>
      <div class="stat"><span>Canais carregados</span><b id="canaisStatus">0</b></div>
      <div class="stat"><span>HLS criados</span><b id="hlsStatus">0</b></div>
      <div class="stat"><span>FFmpeg</span><b id="ffmpegStatus">---</b></div>
    </div>
  </div>
</div>

<div class="wrap">
  <div class="side">
    <div>
      <div class="panel">
        <h2>1. Carregamento das categorias</h2>
        <div class="sub">Use o modo leve para carregar uma por vez, o automático para ir puxando em lotes sem pesar, ou “Carregar tudo” quando quiser deixar tudo pronto sem clicar repetido.</div>
        <div class="row" style="margin-top:12px">
          <button class="btn green" onclick="atualizarProxima()">Carregar próxima</button>
          <button class="btn cyan" onclick="toggleAutoCategorias()" id="autoBtn">Auto carregar</button>
          <button class="btn gray" onclick="carregarTudoCategorias()">Carregar tudo</button>
          <button class="btn red" onclick="limparCache()">Limpar Cache</button>
        </div>
        <div class="autoBox">
          <div class="row">
            <label>Intervalo do automático</label>
            <input id="autoDelay" type="number" min="300" step="100" value="900">
            <span class="sub">ms entre uma categoria e outra</span>
            <span id="autoState" class="autoState">Auto desligado</span>
          </div>
        </div>
        <div class="progress"><div id="bar"></div></div>
        <div id="loadStatus" class="msg"></div>
      </div>

      <div class="panel">
        <h2>Categorias carregadas</h2>
        <div class="sub">Clique em qualquer bloco para gerar o link M3U local daquela categoria.</div>
        <div id="cards" class="cards">
          <div class="empty">Carregando cards...</div>
        </div>
      </div>
    </div>

    <div>
      <div class="panel">
        <h2>Ativos <span id="restreamCount" class="sub">(0)</span></h2>
        <div id="restreams"><div class="sub">Carregando...</div></div>
      </div>

      <div class="panel">
        <h2>Como funciona</h2>
        <div class="sub">
          Primeiro carregue as categorias. Depois clique no card, copie o link e coloque no Smarters/VLC.
          O sistema só inicia quando o player abre o canal.
        </div>
      </div>
    </div>
  </div>
</div>

<div id="modalBg" class="modalBg" onclick="fecharModalFora(event)">
  <div class="modal">
    <h2 id="modalTitle">Link da categoria</h2>
    <div class="sub" id="modalInfo"></div>
    <div id="modalUrl" class="urlBox"></div>
    <div class="row">
      <button class="btn cyan" onclick="copiarModal()">Copiar link</button>
      <button class="btn green" onclick="abrirModal()">Abrir</button>
      <button class="btn red" onclick="fecharModal()">Fechar</button>
    </div>
  </div>
</div>

<script>
let modalUrl = '';
let autoCategoriasLigado = false;
let autoCategoriasTimer = null;
let autoCategoriasRodando = false;

function qs(id){return document.getElementById(id)}
function esc(v){return String(v ?? '').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]))}

async function api(acao, params={}){
  const q = new URLSearchParams({acao, ...params}).toString();
  const r = await fetch('?' + q, {cache:'no-store'});
  const t = await r.text();
  try{return JSON.parse(t)}catch(e){return {erro:t.slice(0,180)}}
}

async function atualizarStatus(){
  const d = await api('status');
  if(d.erro) return;
  qs('cacheStatus').textContent = d.cache ? 'OK' : 'Vazio';
  qs('catStatus').textContent = (d.categorias_carregadas ?? d.loaded_count ?? 0) + '/' + (d.categorias ?? 0);
  qs('canaisStatus').textContent = d.canais ?? 0;
  qs('hlsStatus').textContent = d.streams_ativos ?? 0;
  qs('ffmpegStatus').textContent = d.ffmpeg ?? 'OK';

  const total = Number(d.categorias || 0);
  const car = Number(d.categorias_carregadas || 0);
  qs('bar').style.width = total ? Math.min(100, (car/total)*100) + '%' : '0%';
}

async function atualizarProxima(silencioso=false){
  if(!silencioso){
    qs('loadStatus').className='msg';
    qs('loadStatus').textContent='Carregando próxima categoria...';
  }

  const d = await api('carregar_lista');
  if(d.erro){
    qs('loadStatus').className='msg err';
    qs('loadStatus').textContent='Erro: ' + d.erro;
    return d;
  }

  if(d.fim){
    qs('loadStatus').className='msg ok';
    qs('loadStatus').textContent='Todas as categorias já foram carregadas.';
  }else{
    qs('loadStatus').className='msg ok';
    qs('loadStatus').textContent='Categoria carregada: ' + (d.categoria || '-') + ' • ' + (d.canais || 0) + ' canais • progresso ' + (d.progresso || '');
  }

  await atualizarStatus();
  await carregarCards();
  return d;
}

function atualizarBotoesAuto(){
  const txt = autoCategoriasLigado ? 'Parar auto' : 'Auto carregar';
  const state = autoCategoriasLigado ? 'Auto ligado: carregando em lotes leves' : 'Auto desligado';
  if(qs('autoBtn')) qs('autoBtn').textContent = txt;
  if(qs('autoBtnTop')) qs('autoBtnTop').textContent = txt;
  if(qs('autoState')) qs('autoState').textContent = state;
}

function delayAutoCategorias(){
  const v = Number(qs('autoDelay') ? qs('autoDelay').value : 900);
  return Math.max(300, isNaN(v) ? 900 : v);
}

function toggleAutoCategorias(){
  autoCategoriasLigado = !autoCategoriasLigado;
  atualizarBotoesAuto();
  if(autoCategoriasLigado){
    qs('loadStatus').className='msg';
    qs('loadStatus').textContent='Auto carregamento iniciado. Pode deixar a página aberta que ele vai puxando uma categoria por vez.';
    executarAutoCategorias();
  }else{
    if(autoCategoriasTimer) clearTimeout(autoCategoriasTimer);
    autoCategoriasTimer = null;
    qs('loadStatus').className='msg ok';
    qs('loadStatus').textContent='Auto carregamento pausado.';
  }
}

async function executarAutoCategorias(){
  if(!autoCategoriasLigado || autoCategoriasRodando) return;
  autoCategoriasRodando = true;
  const d = await atualizarProxima(true);
  autoCategoriasRodando = false;

  if(d && d.fim){
    autoCategoriasLigado = false;
    atualizarBotoesAuto();
    qs('loadStatus').className='msg ok';
    qs('loadStatus').textContent='Auto finalizado: todas as categorias foram carregadas.';
    return;
  }
  if(d && d.erro){
    autoCategoriasLigado = false;
    atualizarBotoesAuto();
    return;
  }
  if(autoCategoriasLigado){
    autoCategoriasTimer = setTimeout(executarAutoCategorias, delayAutoCategorias());
  }
}

async function carregarTudoCategorias(){
  if(!confirm('Carregar todas as categorias agora? Vai ser feito em sequência, uma por vez, para não travar o servidor.')) return;
  autoCategoriasLigado = false;
  if(autoCategoriasTimer) clearTimeout(autoCategoriasTimer);
  atualizarBotoesAuto();

  qs('loadStatus').className='msg';
  qs('loadStatus').textContent='Carregando tudo em sequência...';
  let carregadasAgora = 0;
  const maxSeguranca = 5000;

  for(let i=0;i<maxSeguranca;i++){
    const d = await atualizarProxima(true);
    if(d && d.erro) return;
    if(d && d.fim){
      qs('loadStatus').className='msg ok';
      qs('loadStatus').textContent='Carregar tudo finalizado. Categorias novas carregadas agora: ' + carregadasAgora + '.';
      return;
    }
    carregadasAgora++;
    if(qs('autoState')) qs('autoState').textContent = 'Carregando tudo: +' + carregadasAgora + ' categorias';
    await new Promise(r=>setTimeout(r, 250));
  }

  qs('loadStatus').className='msg err';
  qs('loadStatus').textContent='Parou por segurança. Clique em Carregar tudo novamente se ainda faltar categoria.';
}

async function carregarCards(){
  const box = qs('cards');
  const d = await api('categorias_cards');

  if(d.erro || !d.cards){
    box.innerHTML = '<div class="empty">Clique em Atualizar próxima categoria para carregar os cards.</div>';
    return;
  }

  if(!d.cards.length){
    box.innerHTML = '<div class="empty">Nenhuma categoria carregada ainda.</div>';
    return;
  }

  box.innerHTML = d.cards.map(c=>{
    const logo = c.logo ? '<img src="'+esc(c.logo)+'" onerror="this.style.display=\'none\';this.parentNode.innerHTML=\'<div class=&quot;placeholder&quot;>📺</div>\'">' : '<div class="placeholder">📺</div>';
    return `
      <div class="cat-card" data-cat="${esc(c.categoria)}">
        <div class="badge-salvo">SALVO</div>
        <div class="logoBox">${logo}</div>
        <div class="cat-title">${esc(c.categoria)}</div>
        <div class="cat-info">${esc(c.canais)} canais • clique para link</div>
      </div>
    `;
  }).join('');

  document.querySelectorAll('.cat-card').forEach(el=>{
    el.onclick = ()=>gerarLinkCategoria(el.dataset.cat);
  });
}

async function gerarLinkCategoria(cat){
  qs('modalBg').style.display='flex';
  qs('modalTitle').textContent = cat;
  qs('modalInfo').textContent = 'Gerando link...';
  qs('modalUrl').textContent = '';
  modalUrl = '';

  const d = await api('gerar_link', {categoria:cat});
  if(d.erro){
    qs('modalInfo').textContent = 'Erro ao gerar link';
    qs('modalUrl').textContent = d.erro;
    return;
  }

  modalUrl = d.url || '';
  qs('modalInfo').textContent = 'Categoria: ' + (d.categoria || cat) + ' • Canais: ' + (d.canais || 0);
  qs('modalUrl').textContent = modalUrl;
}

function copiarModal(){
  if(!modalUrl) return;
  navigator.clipboard.writeText(modalUrl).then(()=>{
    qs('modalInfo').textContent='Link copiado.';
    setTimeout(fecharModal, 700);
  });
}

function abrirModal(){
  if(modalUrl) window.open(modalUrl, '_blank');
}

function fecharModal(){qs('modalBg').style.display='none'}
function fecharModalFora(e){if(e.target.id==='modalBg') fecharModal()}

async function carregarRestreams(){
  const box = qs('restreams');
  const d = await api('restream_listar');
  if(d.erro){box.innerHTML='<div class="sub err">'+esc(d.erro)+'</div>';return}
  qs('restreamCount').textContent='('+(d.total||0)+')';

  if(!d.streams || !d.streams.length){
    box.innerHTML='<div class="sub">Nenhum HLS criado ainda</div>';
    return;
  }

  box.innerHTML = d.streams.slice(0,8).map(s=>`
    <div class="listItem">
      <div style="font-size:12px;color:#facc15;word-break:break-all">${esc(s.url)}</div>
      <div class="sub">ID: ${esc(s.id)} • ${s.ativo?'Ativo':'Parado'}</div>
      <button class="btn small red" onclick="pararStream('${esc(s.id)}')">Parar</button>
    </div>
  `).join('');
}

async function pararStream(id){
  await api('restream_parar',{stream_id:id});
  carregarRestreams();
  atualizarStatus();
}

async function limparCache(){
  if(!confirm('Limpar cache e categorias carregadas?')) return;
  autoCategoriasLigado = false;
  if(autoCategoriasTimer) clearTimeout(autoCategoriasTimer);
  atualizarBotoesAuto();
  await api('limpar_cache');
  await atualizarStatus();
  await carregarCards();
}

setInterval(()=>{atualizarStatus();carregarRestreams()},10000);
atualizarBotoesAuto();
atualizarStatus();
carregarCards();
carregarRestreams();
</script>

<!-- PATCH_UI_CONFIG_FONTE_IP8088 -->
<style>
#cfgFonteBtn{
    position:fixed;right:16px;bottom:16px;z-index:9999;
    border:0;border-radius:999px;background:#2563eb;color:white;
    padding:13px 16px;font-weight:900;cursor:pointer;
    box-shadow:0 12px 35px rgba(0,0,0,.45)
}
#cfgFonteBox{
    display:none;position:fixed;right:16px;bottom:72px;z-index:9999;
    width:min(440px,calc(100vw - 28px));
    background:#081a31;border:1px solid #38bdf8;border-radius:18px;
    padding:16px;color:#eaf3ff;box-shadow:0 25px 70px rgba(0,0,0,.6);
    font-family:Arial,Helvetica,sans-serif
}
#cfgFonteBox h3{margin:0 0 10px;font-size:18px}
#cfgFonteBox label{display:block;font-size:12px;color:#9fc3ec;margin:9px 0 5px}
#cfgFonteBox input{
    width:100%;background:#061225;color:#fff;border:1px solid #244e7c;
    border-radius:10px;padding:10px;outline:none
}
#cfgFonteBox .linha{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
#cfgFonteBox button{
    border:0;border-radius:10px;padding:10px 12px;color:#fff;
    font-weight:800;cursor:pointer
}
#cfgFonteBox .green{background:#059669}
#cfgFonteBox .red{background:#dc2626}
#cfgFonteBox .gray{background:#334155}
#cfgFonteBox .blue{background:#2563eb}
#cfgFonteMsg{font-size:12px;color:#6ee7b7;margin-top:10px;min-height:16px}
</style>

<button id="cfgFonteBtn" onclick="cfgFonteToggle()">⚙ Fonte</button>

<div id="cfgFonteBox">
  <h3>⚙ Configurar fonte</h3>

  <label>DNS / URL</label>
  <input id="cfgUrl" placeholder="http://servidor.com">

  <label>Usuário</label>
  <input id="cfgUser" placeholder="usuario">

  <label>Senha</label>
  <input id="cfgPass" placeholder="senha">

  <label>Base URL/IP do painel</label>
  <input id="cfgBase" value="http://38.100.203.66:8088">

  <label style="display:flex;align-items:center;gap:8px;margin-top:10px">
    <input id="cfgToken2m" type="checkbox" style="width:auto">
    Token por 2 meses
  </label>

  <div class="linha">
    <button class="green" onclick="cfgSalvarFonte()">Salvar nova fonte e limpar tudo</button>
    <button class="red" onclick="cfgLimparHls()">Limpar HLS</button>
  </div>

  <div class="linha">
    <button class="blue" onclick="cfgTokenDias(60)">Token 2 meses ON</button>
    <button class="gray" onclick="cfgTokenDias(30)">Token 30 dias OFF</button>
    <button class="gray" onclick="cfgFonteToggle()">Fechar</button>
  </div>

  <div id="cfgFonteMsg"></div>
</div>

<script>
function cfgFonteToggle(){
  const b=document.getElementById('cfgFonteBox');
  b.style.display = b.style.display === 'block' ? 'none' : 'block';
  if(b.style.display === 'block') cfgCarregarAtual();
}
async function cfgApi(acao, data){
  const fd=new FormData();
  Object.keys(data||{}).forEach(k=>fd.append(k,data[k]));
  const r=await fetch('?acao='+acao,{method:'POST',body:fd,cache:'no-store'});
  const t=await r.text();
  try{return JSON.parse(t)}catch(e){return {ok:false,erro:t.slice(0,200)}}
}
async function cfgCarregarAtual(){
  const r=await fetch('?acao=config_atual_painel',{cache:'no-store'});
  const d=await r.json();
  if(!d.ok)return;
  document.getElementById('cfgUrl').value=d.url||'';
  document.getElementById('cfgUser').value=d.user||'';
  document.getElementById('cfgPass').value=d.pass||'';
  document.getElementById('cfgBase').value=d.base_url||'http://38.100.203.66:8088';
  document.getElementById('cfgToken2m').checked=Number(d.token_dias||30)>=60;
}
async function cfgSalvarFonte(){
  if(!confirm('Salvar nova fonte e apagar HLS/cache/lista antiga?'))return;
  const msg=document.getElementById('cfgFonteMsg');
  msg.style.color='#facc15';
  msg.textContent='Salvando e limpando...';
  const d=await cfgApi('salvar_origem_reset',{
    url:document.getElementById('cfgUrl').value,
    user:document.getElementById('cfgUser').value,
    pass:document.getElementById('cfgPass').value,
    base_url:document.getElementById('cfgBase').value,
    token_2m:document.getElementById('cfgToken2m').checked?'1':'0'
  });
  msg.style.color=d.ok?'#6ee7b7':'#fecaca';
  msg.textContent=d.ok?d.mensagem:(d.erro||'Erro');
  setTimeout(()=>location.reload(),1200);
}
async function cfgLimparHls(){
  if(!confirm('Limpar apenas HLS/streams/logs?'))return;
  const msg=document.getElementById('cfgFonteMsg');
  msg.style.color='#facc15';
  msg.textContent='Limpando HLS...';
  const d=await cfgApi('limpar_hls_runtime',{});
  msg.style.color=d.ok?'#6ee7b7':'#fecaca';
  msg.textContent=d.ok?d.mensagem:(d.erro||'Erro');
  setTimeout(()=>location.reload(),900);
}
async function cfgTokenDias(dias){
  const msg=document.getElementById('cfgFonteMsg');
  msg.style.color='#facc15';
  msg.textContent='Ajustando token...';
  const d=await cfgApi('token_ttl_update',{dias});
  msg.style.color=d.ok?'#6ee7b7':'#fecaca';
  msg.textContent=d.ok?d.mensagem:(d.erro||'Erro');
  document.getElementById('cfgToken2m').checked=Number(dias)>=60;
}
</script>


<!-- BOTAO_LIMPAR_SSD_SEGURO -->
<a href="/hls_cleaner.php" target="_blank" style="position:fixed;left:16px;bottom:16px;z-index:999999;background:#dc2626;color:#fff;padding:12px 15px;border-radius:999px;text-decoration:none;font-family:Arial;font-weight:900;box-shadow:0 12px 35px rgba(0,0,0,.45)">🧹 SSD/HLS</a>


<!-- BOTAO_HLS_CLEANER_SEGURO -->
<a href="/hls_cleaner.php" target="_blank" style="position:fixed;left:16px;bottom:16px;z-index:999999;background:#dc2626;color:#fff;padding:12px 15px;border-radius:999px;text-decoration:none;font-family:Arial;font-weight:900;box-shadow:0 12px 35px rgba(0,0,0,.45)">🧹 SSD/HLS</a>
<a href="/?logout_painel=1" style="position:fixed;right:16px;top:16px;z-index:999999;background:#334155;color:#fff;padding:9px 12px;border-radius:10px;text-decoration:none;font-family:Arial;font-weight:900;font-size:12px">Sair</a>

</body>
</html>

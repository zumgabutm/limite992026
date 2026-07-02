<?php
header('Content-Type: application/json; charset=utf-8');

$file = __DIR__ . '/keys.json';
$key = trim((string)($_POST['key'] ?? $_GET['key'] ?? ''));
$fingerprint = trim((string)($_POST['fingerprint'] ?? $_GET['fingerprint'] ?? ''));
$project = trim((string)($_POST['project'] ?? $_GET['project'] ?? ''));
$hostname = trim((string)($_POST['hostname'] ?? $_GET['hostname'] ?? ''));
$ip = trim((string)($_POST['ip'] ?? $_GET['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? '')));

function out($ok, $msg, $extra = []) {
    echo json_encode(array_merge(['ok' => $ok, 'message' => $msg], $extra), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($key === '' || $fingerprint === '') out(false, 'Key ou fingerprint vazio');
if (!preg_match('/^[a-zA-Z0-9\-_.]{3,120}$/', $key)) out(false, 'Formato de key inválido');
if (!preg_match('/^[a-f0-9]{64}$/', $fingerprint)) out(false, 'Fingerprint inválido');
if (!file_exists($file)) out(false, 'Arquivo keys.json não encontrado');

$fp = fopen($file, 'c+');
if (!$fp) out(false, 'Não consegui abrir keys.json');
flock($fp, LOCK_EX);
$raw = stream_get_contents($fp);
$keys = json_decode($raw ?: '{}', true);
if (!is_array($keys)) $keys = [];

if (!isset($keys[$key])) {
    flock($fp, LOCK_UN);
    fclose($fp);
    out(false, 'Key não cadastrada');
}

if (empty($keys[$key]['active'])) {
    flock($fp, LOCK_UN);
    fclose($fp);
    out(false, 'Key desativada');
}

$current = trim((string)($keys[$key]['fingerprint'] ?? ''));
$now = date('c');

if ($current === '') {
    $keys[$key]['fingerprint'] = $fingerprint;
    $keys[$key]['project'] = $project;
    $keys[$key]['hostname'] = $hostname;
    $keys[$key]['ip'] = $ip;
    $keys[$key]['activated_at'] = $now;
    $keys[$key]['last_check'] = $now;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($keys, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    out(true, 'Key ativada nesta VPS', ['status' => 'activated']);
}

if (hash_equals($current, $fingerprint)) {
    $keys[$key]['last_check'] = $now;
    $keys[$key]['last_ip'] = $ip;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($keys, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    out(true, 'Key já autorizada para esta VPS', ['status' => 'same_vps']);
}

flock($fp, LOCK_UN);
fclose($fp);
out(false, 'Key já usada em outra VPS', ['status' => 'blocked']);

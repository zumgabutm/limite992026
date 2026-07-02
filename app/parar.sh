#!/bin/bash
BASE="$(cd "$(dirname "$0")" && pwd)"
fuser -k 8088/tcp 2>/dev/null || true
pkill -f "php -S.*8088" 2>/dev/null || true
pkill -f "$BASE/index.php acao=watchdog" 2>/dev/null || true
pkill -f "$BASE/hls.sh" 2>/dev/null || true
pkill -f "ffmpeg.*$BASE" 2>/dev/null || true
echo "parado"

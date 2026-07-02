#!/bin/bash

BASE="$(cd "$(dirname "$0")" && pwd)"
cd "$BASE" || exit 0

LIMIT=$((13 * 1024 * 1024 * 1024))
EMERGENCY=$((22 * 1024 * 1024 * 1024))
LOG="$BASE/logs/hls_auto_clean.log"

mkdir -p hls streams logs pids data
chmod -R 775 hls streams logs pids data 2>/dev/null || true

# Limpeza leve: só remove segmentos bem antigos que não ficam mais na playlist.
# Não para canal ao vivo e não apaga .meta/.pid em limpeza normal.
find "$BASE/hls" -type f -name "*.ts" -mmin +90 -delete 2>/dev/null || true
find "$BASE/hls" -type f -name "*.m3u8" -mmin +360 -delete 2>/dev/null || true
find "$BASE/logs" -type f ! -name "hls_auto_clean.log" ! -name "watchdog.log" -mtime +5 -delete 2>/dev/null || true

USADO=$(du -sb hls streams logs 2>/dev/null | awk '{s+=$1} END{print s+0}')
USADO_MB=$((USADO / 1024 / 1024))
echo "$(date '+%Y-%m-%d %H:%M:%S') | usado=${USADO_MB}MB | limite=13312MB | modo=velocidade_online" >> "$LOG"

# Só em emergência real limpa runtime completo para salvar disco/servidor.
if [ "$USADO" -ge "$EMERGENCY" ]; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') | EMERGENCIA DISCO, limpando runtime HLS" >> "$LOG"
    pkill -f "ffmpeg.*$BASE" 2>/dev/null || true
    find "$BASE/hls" -type f -delete 2>/dev/null || true
    find "$BASE/streams" -type f -delete 2>/dev/null || true
    find "$BASE/logs" -type f ! -name "hls_auto_clean.log" -delete 2>/dev/null || true
fi

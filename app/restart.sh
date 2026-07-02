#!/usr/bin/env bash
set -e
BASE="$(cd "$(dirname "$0")" && pwd)"
cd "$BASE"
php -l index.php
systemctl restart limiter99-web.service limiter99-watchdog.service limiter99-cleaner.service 2>/dev/null || true
echo "Limiter99 reiniciado."

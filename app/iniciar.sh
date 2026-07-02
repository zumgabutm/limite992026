#!/usr/bin/env bash
set -e
systemctl start limiter99-web.service limiter99-watchdog.service limiter99-cleaner.service 2>/dev/null || true
echo "Limiter99 iniciado."

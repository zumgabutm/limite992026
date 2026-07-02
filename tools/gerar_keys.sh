#!/usr/bin/env bash
QTD="${1:-10}"
for i in $(seq 1 "$QTD"); do
  echo "L99-$(openssl rand -hex 3 | tr 'a-f' 'A-F')-$(openssl rand -hex 3 | tr 'a-f' 'A-F')-$(openssl rand -hex 3 | tr 'a-f' 'A-F')"
done

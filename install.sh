#!/usr/bin/env bash
set -Eeuo pipefail

# ==========================================================
# Limiter99 Setup - projeto GitHub: limite992026
# Instala em: /root/limiter99
# Para trava real de key 1 VPS mesmo após formatar, configure LICENSE_SERVER_URL.
# ==========================================================

GITHUB_OWNER="${GITHUB_OWNER:-zumgabutm}"
GITHUB_REPO="${GITHUB_REPO:-limite992026}"
GITHUB_BRANCH="${GITHUB_BRANCH:-main}"
GITHUB_ZIP_URL="${GITHUB_ZIP_URL:-https://github.com/${GITHUB_OWNER}/${GITHUB_REPO}/archive/refs/heads/${GITHUB_BRANCH}.zip}"
INSTALL_DIR="${INSTALL_DIR:-/root/limiter99}"
PUBLIC_PORT_DEFAULT="${PUBLIC_PORT:-8099}"
INTERNAL_PORT="${INTERNAL_PORT:-12799}"
PROJECT_NAME="limiter99"
PROJECT_REPO_NAME="limite992026"

# Deixe vazio para validar somente pelo arquivo licenses/keys.txt.
# Para bloquear a mesma key em VPS diferente, suba a pasta license-server em um domínio seu
# e coloque aqui a URL completa do activate.php.
LICENSE_SERVER_URL="${LICENSE_SERVER_URL:-}"

C0='\033[0m'; C1='\033[1;36m'; C2='\033[1;32m'; C3='\033[1;33m'; C4='\033[1;31m'; C5='\033[1;35m'
trap 'echo -e "${C4}✘ Instalação interrompida na linha $LINENO. Veja a mensagem acima.${C0}" >&2' ERR

banner() {
  clear || true
  echo -e "${C1}"
  cat <<'BANNER'
╔══════════════════════════════════════════════════════════════╗
║                    🚀 LIMITER99 SETUP                       ║
║              Restream HLS com FFmpeg + Watchdog             ║
║                                                              ║
║                 USE SOMENTE COM AUTORIZAÇÃO                 ║
╚══════════════════════════════════════════════════════════════╝
BANNER
  echo -e "${C0}"
}

msg(){ echo -e "${C2}✔${C0} $*" >&2; }
warn(){ echo -e "${C3}⚠${C0} $*" >&2; }
err(){ echo -e "${C4}✘${C0} $*" >&2; }

need_root() {
  if [ "${EUID}" -ne 0 ]; then
    err "Execute como root: sudo bash install.sh"
    exit 1
  fi
}

cmd_exists(){ command -v "$1" >/dev/null 2>&1; }

install_base_deps() {
  msg "Instalando dependências base: PHP CLI, FFmpeg, FFprobe, curl, unzip..."
  if cmd_exists apt-get; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -y
    apt-get install -y ca-certificates curl unzip procps psmisc lsof openssl ffmpeg php-cli php-curl php-json
  elif cmd_exists dnf; then
    dnf install -y ca-certificates curl unzip procps-ng psmisc lsof openssl ffmpeg php-cli php-curl php-json || true
  elif cmd_exists yum; then
    yum install -y ca-certificates curl unzip procps-ng psmisc lsof openssl ffmpeg php-cli php-curl php-json || true
  else
    err "Gerenciador de pacotes não suportado. Use Ubuntu/Debian para instalação automática."
    exit 1
  fi

  if ! cmd_exists php; then err "PHP CLI não instalado."; exit 1; fi
  if ! cmd_exists ffmpeg; then err "FFmpeg não instalado."; exit 1; fi
  if ! cmd_exists ffprobe; then warn "FFprobe não encontrado; normalmente vem junto com FFmpeg."; fi
}

get_public_ip() {
  local ip=""
  ip=$(curl -4 -fsS --max-time 8 https://api.ipify.org 2>/dev/null || true)
  if [ -z "$ip" ]; then
    ip=$(hostname -I 2>/dev/null | awk '{print $1}' || true)
  fi
  echo "${ip:-127.0.0.1}"
}

fingerprint_vps() {
  # Prioriza dados de hardware/rede que continuam iguais mesmo após formatar a VPS.
  # machine-id/hostname só entram como fallback, porque mudam em reinstalação.
  local data
  data=$( {
    ip link 2>/dev/null | awk '/link\/ether/{print $2}' || true
    lsblk -ndo SERIAL 2>/dev/null || true
    cat /sys/class/dmi/id/product_uuid 2>/dev/null || true
    cat /sys/class/dmi/id/board_serial 2>/dev/null || true
  } | tr '[:upper:]' '[:lower:]' | tr -d ' \t\r' | sed '/^$/d' | sort -u )
  if [ -z "$data" ]; then
    data=$( { cat /etc/machine-id 2>/dev/null || true; hostname 2>/dev/null || true; } | tr '[:upper:]' '[:lower:]' | tr -d ' \t\r' )
  fi
  printf '%s' "$data" | sha256sum | awk '{print $1}'
}

prepare_repo_source() {
  local script_dir repo_dir tmp zip
  script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
  if [ -d "$script_dir/app" ] && [ -f "$script_dir/app/index.php" ]; then
    echo "$script_dir"
    return 0
  fi

  tmp="$(mktemp -d)"
  zip="$tmp/repo.zip"
  msg "Baixando arquivos do GitHub: ${GITHUB_OWNER}/${GITHUB_REPO} (${GITHUB_BRANCH})..."
  if ! curl -LfsS "$GITHUB_ZIP_URL" -o "$zip"; then
    warn "Não consegui baixar pela branch ${GITHUB_BRANCH}. Tentando branch master..."
    GITHUB_ZIP_URL="https://github.com/${GITHUB_OWNER}/${GITHUB_REPO}/archive/refs/heads/master.zip"
    curl -LfsS "$GITHUB_ZIP_URL" -o "$zip"
  fi
  unzip -q "$zip" -d "$tmp"
  repo_dir=$(find "$tmp" -maxdepth 2 -type d -name app -printf '%h\n' | head -n1)
  if [ -z "$repo_dir" ] || [ ! -f "$repo_dir/app/index.php" ]; then
    err "Não encontrei a pasta app no repositório baixado."
    exit 1
  fi
  echo "$repo_dir"
}

validate_license_local() {
  local key="$1" keys_file="$2"
  if [ ! -f "$keys_file" ]; then
    err "Arquivo de keys não encontrado: $keys_file"
    exit 1
  fi
  if ! grep -vE '^\s*(#|$)' "$keys_file" | tr -d '\r' | grep -qx "$key"; then
    err "Key inválida. Edite licenses/keys.txt para adicionar/remover keys."
    exit 1
  fi
}

validate_license_remote() {
  local key="$1" fp="$2" host="$3" response=""
  [ -z "$LICENSE_SERVER_URL" ] && return 0
  msg "Validando key no servidor de licença..."
  response=$(curl -fsS --max-time 20 -X POST "$LICENSE_SERVER_URL" \
    --data-urlencode "key=$key" \
    --data-urlencode "fingerprint=$fp" \
    --data-urlencode "project=$PROJECT_REPO_NAME" \
    --data-urlencode "hostname=$(hostname)" \
    --data-urlencode "ip=$host" 2>/dev/null || true)

  if echo "$response" | grep -q '"ok"[[:space:]]*:[[:space:]]*true'; then
    return 0
  fi

  err "Servidor de licença recusou a key. Resposta: ${response:-sem resposta}"
  exit 1
}

save_license_files() {
  local key="$1" fp="$2" public_ip="$3" public_port="$4"
  mkdir -p /etc/limiter99
  cat > /etc/limiter99/license.json <<JSON
{
  "project": "$PROJECT_REPO_NAME",
  "install_name": "$PROJECT_NAME",
  "key": "$key",
  "fingerprint": "$fp",
  "public_ip": "$public_ip",
  "public_port": "$public_port",
  "installed_at": "$(date -Is)"
}
JSON
  cp /etc/limiter99/license.json "$INSTALL_DIR/.license.json" 2>/dev/null || true
  chmod 600 /etc/limiter99/license.json 2>/dev/null || true
}

copy_app_files() {
  local repo_dir="$1"
  msg "Copiando sistema para $INSTALL_DIR..."
  mkdir -p "$INSTALL_DIR"
  cp -a "$repo_dir/app/." "$INSTALL_DIR/"
  mkdir -p "$INSTALL_DIR/hls" "$INSTALL_DIR/streams" "$INSTALL_DIR/logs" "$INSTALL_DIR/pids" "$INSTALL_DIR/data"
  chmod +x "$INSTALL_DIR"/*.sh 2>/dev/null || true
  chmod -R 775 "$INSTALL_DIR/hls" "$INSTALL_DIR/streams" "$INSTALL_DIR/logs" "$INSTALL_DIR/pids" "$INSTALL_DIR/data" 2>/dev/null || true
}

update_config() {
  local public_ip="$1" public_port="$2" secret
  secret=$(openssl rand -hex 32 2>/dev/null || date +%s%N | sha256sum | awk '{print $1}')
  msg "Gerando config.json com IP/porta de acesso..."
  php -r '
    $file=$argv[1]; $base=$argv[2]; $secret=$argv[3];
    $c=json_decode(file_get_contents($file), true); if(!is_array($c)) $c=[];
    $c["base_url"]=$base;
    $c["hls_url"]=rtrim($base,"/")."/hls/";
    $c["dominio"]=preg_replace("#^https?://#", "", $base);
    $c["ffmpeg_path"]="/usr/bin/ffmpeg";
    $c["ffmpeg"]="/usr/bin/ffmpeg";
    $c["ffprobe_path"]="/usr/bin/ffprobe";
    if(empty($c["token_secret"]) || $c["token_secret"]==="LIMITE992026_TROQUE_ESSA_CHAVE") $c["token_secret"]=$secret;
    file_put_contents($file, json_encode($c, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
  ' "$INSTALL_DIR/config.json" "http://$public_ip:$public_port" "$secret"
}

write_env_file() {
  local public_ip="$1" public_port="$2"
  cat > /etc/limiter99/env <<EOFENV
INSTALL_DIR=$INSTALL_DIR
PUBLIC_IP=$public_ip
PUBLIC_PORT=$public_port
INTERNAL_PORT=$INTERNAL_PORT
DOMINIO=$public_ip:$public_port
EOFENV
}

install_systemd() {
  msg "Criando serviços systemd do Limiter99..."
  cat > /etc/systemd/system/limiter99-web.service <<EOFUNIT
[Unit]
Description=Limiter99 PHP internal server
After=network.target

[Service]
Type=simple
WorkingDirectory=$INSTALL_DIR
Environment=PHP_CLI_SERVER_WORKERS=48
ExecStart=/usr/bin/php -S 127.0.0.1:$INTERNAL_PORT -t $INSTALL_DIR $INSTALL_DIR/index.php
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOFUNIT

  cat > /etc/systemd/system/limiter99-watchdog.service <<EOFUNIT
[Unit]
Description=Limiter99 HLS Watchdog
After=network.target limiter99-web.service

[Service]
Type=simple
WorkingDirectory=$INSTALL_DIR
EnvironmentFile=/etc/limiter99/env
ExecStart=/bin/bash -lc 'while true; do /usr/bin/php "$INSTALL_DIR/index.php" acao=watchdog dominio="\$DOMINIO" >/dev/null 2>&1; sleep 8; done'
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOFUNIT

  cat > /etc/systemd/system/limiter99-cleaner.service <<EOFUNIT
[Unit]
Description=Limiter99 HLS Cleaner
After=network.target limiter99-web.service

[Service]
Type=simple
WorkingDirectory=$INSTALL_DIR
ExecStart=/bin/bash -lc 'while true; do /bin/bash "$INSTALL_DIR/hls.sh" >/dev/null 2>&1; sleep 300; done'
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOFUNIT

  systemctl daemon-reload
  systemctl enable limiter99-web.service limiter99-watchdog.service limiter99-cleaner.service >/dev/null
  systemctl restart limiter99-web.service limiter99-watchdog.service limiter99-cleaner.service
}

install_apache_proxy() {
  local public_port="$1"
  msg "Configurando Apache na porta $public_port sem mexer em domínios existentes..."
  if cmd_exists apt-get; then
    apt-get install -y apache2
    a2enmod proxy proxy_http headers rewrite >/dev/null || true
    grep -q "Listen $public_port" /etc/apache2/ports.conf 2>/dev/null || echo "Listen $public_port" >> /etc/apache2/ports.conf
    cat > /etc/apache2/sites-available/limiter99.conf <<EOFAPACHE
<VirtualHost *:$public_port>
    ServerName limiter99.local
    ProxyPreserveHost On
    RequestHeader set X-Forwarded-Proto "http"
    ProxyPass / http://127.0.0.1:$INTERNAL_PORT/ retry=0 timeout=300
    ProxyPassReverse / http://127.0.0.1:$INTERNAL_PORT/
    ErrorLog \${APACHE_LOG_DIR}/limiter99_error.log
    CustomLog \${APACHE_LOG_DIR}/limiter99_access.log combined
</VirtualHost>
EOFAPACHE
    a2ensite limiter99.conf >/dev/null || true
    systemctl restart apache2
  else
    err "Configuração Apache automática disponível principalmente para Ubuntu/Debian."
    exit 1
  fi
}

install_nginx_proxy() {
  local public_port="$1"
  msg "Configurando Nginx na porta $public_port..."
  if cmd_exists apt-get; then
    apt-get install -y nginx
    rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true
    cat > /etc/nginx/sites-available/limiter99 <<EOFNGINX
server {
    listen $public_port;
    server_name _;

    client_max_body_size 200M;

    location / {
        proxy_pass http://127.0.0.1:$INTERNAL_PORT;
        proxy_http_version 1.1;
        proxy_set_header Host \$host:$public_port;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_buffering off;
        proxy_read_timeout 300s;
        proxy_send_timeout 300s;
    }
}
EOFNGINX
    ln -sf /etc/nginx/sites-available/limiter99 /etc/nginx/sites-enabled/limiter99
    nginx -t
    systemctl restart nginx
  else
    err "Configuração Nginx automática disponível principalmente para Ubuntu/Debian."
    exit 1
  fi
}

open_firewall_best_effort() {
  local port="$1"
  ufw allow "$port"/tcp >/dev/null 2>&1 || true
  firewall-cmd --permanent --add-port="$port/tcp" >/dev/null 2>&1 || true
  firewall-cmd --reload >/dev/null 2>&1 || true
}

show_final() {
  local ip="$1" port="$2" mode="$3"
  echo
  echo -e "${C1}╔══════════════════════════════════════════════════════════════╗${C0}"
  echo -e "${C1}║                 INSTALAÇÃO FINALIZADA ✅                    ║${C0}"
  echo -e "${C1}╚══════════════════════════════════════════════════════════════╝${C0}"
  echo
  echo -e "Acesso:  ${C2}http://$ip:$port${C0}"
  echo -e "Usuário: ${C2}admin${C0}"
  echo -e "Senha:   ${C2}admin${C0}"
  echo -e "Modo:    ${C2}$mode${C0}"
  echo
  echo "Pasta instalada: $INSTALL_DIR"
  echo "Reiniciar: bash $INSTALL_DIR/restart.sh"
  echo "Parar FFmpeg/streams: bash $INSTALL_DIR/parar.sh"
  echo
}

main() {
  need_root
  banner
  echo "1) Instalar Limiter99"
  echo "2) Sair"
  echo
  read -rp "Digite a opção: " op
  [ "$op" = "1" ] || exit 0

  echo
  read -rp "Digite sua KEY de instalação: " LICENSE_KEY
  LICENSE_KEY="$(echo "$LICENSE_KEY" | tr -d '[:space:]')"
  [ -n "$LICENSE_KEY" ] || { err "Key vazia."; exit 1; }

  echo
  echo "Escolha o modo do servidor web:"
  echo "1) Apache / KeyHelp / hospedagem com Apache"
  echo "2) Nginx"
  read -rp "Digite 1 ou 2: " WEB_OPT
  case "$WEB_OPT" in
    1) WEB_MODE="apache" ;;
    2) WEB_MODE="nginx" ;;
    *) err "Opção inválida."; exit 1 ;;
  esac

  read -rp "Porta de acesso público [${PUBLIC_PORT_DEFAULT}]: " PUBLIC_PORT
  PUBLIC_PORT="${PUBLIC_PORT:-$PUBLIC_PORT_DEFAULT}"
  if ! [[ "$PUBLIC_PORT" =~ ^[0-9]+$ ]] || [ "$PUBLIC_PORT" -lt 1 ] || [ "$PUBLIC_PORT" -gt 65535 ]; then
    err "Porta inválida: $PUBLIC_PORT"
    exit 1
  fi
  echo
  msg "Modo escolhido: $WEB_MODE | Porta: $PUBLIC_PORT"

  local repo_dir keys_file fp public_ip
  repo_dir=$(prepare_repo_source)
  keys_file="$repo_dir/licenses/keys.txt"
  fp=$(fingerprint_vps)

  validate_license_local "$LICENSE_KEY" "$keys_file"

  install_base_deps
  public_ip=$(get_public_ip)
  validate_license_remote "$LICENSE_KEY" "$fp" "$public_ip"

  copy_app_files "$repo_dir"
  update_config "$public_ip" "$PUBLIC_PORT"
  write_env_file "$public_ip" "$PUBLIC_PORT"
  save_license_files "$LICENSE_KEY" "$fp" "$public_ip" "$PUBLIC_PORT"
  install_systemd

  if [ "$WEB_MODE" = "apache" ]; then
    install_apache_proxy "$PUBLIC_PORT"
  else
    install_nginx_proxy "$PUBLIC_PORT"
  fi

  open_firewall_best_effort "$PUBLIC_PORT"
  sleep 2
  show_final "$public_ip" "$PUBLIC_PORT" "$WEB_MODE"
}

main "$@"

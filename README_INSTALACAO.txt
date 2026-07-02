LIMITER99 / limite992026

Pasta do GitHub: limite992026
Usuário GitHub configurado no instalador: zumgabutm
Pasta instalada na VPS: /root/limiter99
Login padrão do painel: admin
Senha padrão do painel: admin

COMO SUBIR NO GITHUB

Suba todo este conteúdo solto dentro do repositório:

install.sh
README_INSTALACAO.txt
licenses/
license-server/
tools/
app/

Não suba somente o ZIP.

COMANDO PARA INSTALAR NA VPS

Ubuntu/Debian como root:

bash <(curl -fsSL https://raw.githubusercontent.com/zumgabutm/limite992026/main/install.sh)

Se sua branch for master:

bash <(curl -fsSL https://raw.githubusercontent.com/zumgabutm/limite992026/master/install.sh)

Também pode clonar:

git clone https://github.com/zumgabutm/limite992026.git
cd limite992026
bash install.sh

KEYS

As keys locais ficam em:
licenses/keys.txt

Keys incluídas:
196208
19844
19966
148903
763298
120762

Para adicionar mais keys locais, adicione uma por linha.

IMPORTANTE SOBRE KEY UMA VPS

Validação local impede instalação sem key, mas não consegue impedir 100% que a mesma key seja usada em outra VPS após formatação, porque o arquivo local some.

Para travar cada key em uma VPS mesmo depois de formatar, use a pasta:
license-server/

Suba em um domínio seu e configure no install.sh:
LICENSE_SERVER_URL="https://seudominio.com/license-server/activate.php"

MODOS DE INSTALAÇÃO

Durante a instalação, o setup pergunta:
1. Apache / KeyHelp
2. Nginx

Por padrão ele usa a porta 8099 para não mexer nos sites principais.

SERVIÇOS CRIADOS

limiter99-web.service
limiter99-watchdog.service
limiter99-cleaner.service

Comandos úteis:

systemctl status limiter99-web.service
systemctl status limiter99-watchdog.service
systemctl status limiter99-cleaner.service
bash /root/limiter99/restart.sh
bash /root/limiter99/parar.sh

OBSERVAÇÃO

Use somente com fontes e restream autorizados.

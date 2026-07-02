LIMITER99 / limite992026

Pasta do GitHub: limite992026
Pasta instalada na VPS: /root/limiter99
Login padrão do painel: admin
Senha padrão do painel: admin

COMANDO APÓS COLOCAR NO GITHUB

1. Suba todo este conteúdo solto no repositório limite992026.
2. Edite no install.sh a linha GITHUB_ZIP_URL, trocando SEU_USUARIO pelo seu usuário do GitHub.
3. Instale em uma VPS Ubuntu/Debian com:

bash <(curl -fsSL https://raw.githubusercontent.com/SEU_USUARIO/limite992026/main/install.sh)

Ou clone e rode:

git clone https://github.com/SEU_USUARIO/limite992026.git
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

SERVIDOR DE LICENÇA OPCIONAL DO LIMITER99

Use esta pasta apenas se você quiser bloquear cada key para uma única VPS mesmo depois de formatar.

Como usar:
1. Suba a pasta license-server em um domínio seu, exemplo:
   https://seudominio.com/license-server/activate.php

2. Dê permissão de escrita ao arquivo keys.json.

3. Edite o install.sh e coloque:
   LICENSE_SERVER_URL="https://seudominio.com/license-server/activate.php"

4. Para adicionar mais keys, edite keys.json neste formato:
   "NOVAKEY": {"active": true, "fingerprint": "", "note": "cliente novo"}

Quando a key for usada pela primeira vez, o servidor salva o fingerprint da VPS.
Se tentar usar a mesma key em outra VPS, o servidor bloqueia.
Se formatar a mesma VPS e o fingerprint continuar igual, ele libera novamente.

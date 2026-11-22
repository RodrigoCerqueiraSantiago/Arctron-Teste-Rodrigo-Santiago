# Arctron Analytics

Dashboard de compras com Laravel, React e PostgreSQL, com integração WooCommerce por webhook. Todo o ambiente de desenvolvimento é reproduzível com Docker Compose. O visual é demonstrativo e não representa uma identidade oficial.

## Pré-requisitos

- Docker com Docker Compose v2 e plugin `docker compose`.
- GNU Make, shell POSIX, `curl`, `jq` e acesso à internet para baixar imagens e o plugin.
- Portas 8080, 5173 e, para a loja opcional, 8081 e 8025 disponíveis.

## Início rápido

Na raiz do projeto:

```sh
cp .env.example .env
make up
make migrate
docker compose exec api php artisan dashboard:create-user
make seed-woocommerce
```

`make up` sobe apenas API, PostgreSQL, Nginx e frontend. `make seed-woocommerce` inicia também o profile da loja e configura WordPress, cinco produtos e webhooks. Alternativamente, use `make up-all` antes da migration para iniciar todos os serviços. A primeira execução precisa baixar imagens, dependências e o WooCommerce; o tempo depende da conexão. Em reinicializações os dados são preservados.

No comando de criação de usuário, escolha `admin` para o primeiro acesso e informe uma senha com no mínimo 12 caracteres e no máximo 72 bytes; ela é solicitada sem exibição e armazenada como hash. Execute novamente e escolha `reader` para criar um leitor. Não há usuário ou senha padrão do dashboard. Acesse o endereço abaixo com as credenciais criadas.

| Serviço | Endereço |
|---|---|
| Dashboard | http://localhost:5173 |
| API e healthcheck | http://localhost:8080/api/purchases e http://localhost:8080/up |
| Contrato OpenAPI | http://localhost:8080/openapi.yaml |
| Loja WooCommerce | http://localhost:8081 |
| Administração WordPress | http://localhost:8081/wp-admin |
| E-mails de teste (Mailpit) | http://localhost:8025 |

O acesso de **desenvolvimento** padrão à loja é usuário `admin` e senha `change-me-dev-only`. As variáveis da raiz são aplicadas aos contêineres via Compose; altere `.env` **antes da primeira inicialização**. `WOOCOMMERCE_WEBHOOK_SECRET` é compartilhado entre API e loja. A chave da aplicação e senhas fornecidas são públicas e destinadas apenas a desenvolvimento. Não publique `.env` nem use esses valores em produção. O primeiro cadastro de administrador fica no banco WordPress: alterar as variáveis depois não altera automaticamente esse usuário.

## Acesso e proteção de dados

- **Administrador:** consulta indicadores, compras, CPF, email e observações; pode cadastrar compras pela API.
- **Leitor:** consulta indicadores e compras, mas não recebe CPF, email ou observações. A busca por cliente considera apenas o nome; cadastro de compras retorna `403`.
- A API e o dashboard exigem sessão autenticada; o webhook permanece independente e exige assinatura HMAC. O login limita tentativas e renova a sessão. Operações de escrita usam token CSRF. As sessões ficam no banco com conteúdo criptografado, cookie HttpOnly e respostas `no-store`. O Nginx da API não grava logs de acesso, para não registrar filtros com CPF ou email.

Para experimentar HTTPS **local** no dashboard, forneça certificado e chave válidos para `localhost`, acessíveis ao Docker. Mantenha a chave privada fora do repositório. Configure `TLS_CERT_PATH`, `TLS_KEY_PATH` e `SESSION_SECURE_COOKIE=true` no `.env`, reinicie a API e inicie o proxy:

```sh
docker compose up -d api
docker compose --profile tls up -d tls
```

Abra `https://localhost:8443`. Certificados não confiáveis geram aviso no navegador; não ignore avisos em produção. Com cookie seguro habilitado, o dashboard HTTP deixa de manter o login; para voltar ao modo local HTTP, defina `SESSION_SECURE_COOKIE=false` e recrie a API. Esse proxy protege apenas o dashboard e a API; a loja de teste continua em HTTP local. As portas do Compose ficam vinculadas a `127.0.0.1`.

O Compose é demonstrativo, não uma implantação de produção: antes de publicar, use certificados confiáveis, HTTPS também para a loja, um proxy e frontend adequados à produção, credenciais e `APP_KEY` próprios, proteção de rede, políticas de retenção e auditoria de logs. Desativar o log de acesso não substitui uma revisão completa dos demais logs nem criptografa CPF e email no banco.

## Verificar a integração

Simulação assinada, sem depender da loja:

```sh
make simulate-order ARGS="--reference=987654321"
```

O argumento `--reference` é o ID numérico do pedido WooCommerce; omitido, é gerado automaticamente. `--url` permite outro destino acessível pelo contêiner `api`. Repetir o mesmo ID deve retornar `created: false`. Entre no dashboard e busque `woocommerce:987654321`.

Para criar um pedido real **na loja de teste**:

```sh
make test-order
```

O script cria um pedido `processing` com dados fictícios e executa as ações agendadas. Ele mostra o ID; busque `woocommerce:<ID>` no dashboard ou na API. A loja envia os webhooks à URL interna `http://nginx/api/integrations/woocommerce/webhook`. Não é necessário expor a API publicamente. O plugin de desenvolvimento em `docker/woocommerce/allow-internal-webhook.php` permite que WordPress acesse somente esse host interno, contornando a proteção padrão contra destinos privados.

Para testar pela interface, acesse http://localhost:8081/shop/, adicione um produto, entre em **Carrinho** e depois em **Finalizar pedido**. Preencha os dados fictícios e o CPF com 11 dígitos. Selecione **Pagamento de teste na entrega**: não há cobrança real. Após finalizar, consulte `woocommerce:<número do pedido>` no dashboard e o e-mail de confirmação no Mailpit. O checkout de blocos coleta o CPF e o salva como `_billing_cpf`; a validação confere apenas o formato de 11 dígitos, não os dígitos verificadores. Caso o webhook ainda esteja na fila, execute `docker compose exec -T wpcli wp action-scheduler run --batch-size=100 --batches=5`.

A loja demonstrativa tem tema próprio inspirado nas cores do Brasil, sem vínculo com a CBF. A interface usa português e reais, e o cabeçalho mostra a quantidade de itens e o total do carrinho. O CSS compilado do tema já está incluído; após alterar `frontend/src/store.css`, gere-o novamente com `cd frontend` e `npm run build:store`.

O Mailpit captura os e-mails enviados pela loja e **não os entrega a destinatários reais**. A interface é vinculada somente a `127.0.0.1`. Para envio em produção, configure um provedor SMTP adequado, autenticação e proteção de dados; o plugin e o pagamento de demonstração são apenas para desenvolvimento.

O setup cria `order.created` e também `order.updated`: pedidos inicialmente pendentes são ignorados e podem ser importados quando passam para `processing` ou `completed`. Se não aparecer, consulte o histórico de entregas em **WooCommerce > Configurações > Avançado > Webhooks**, as ações agendadas e `make logs`. Um ping inicial do WooCommerce, sem assinatura, recebe HTTP 200 mas não altera dados; pedidos reais precisam de HMAC válido.

## API

- `GET /api/csrf`, `POST /api/login`, `GET /api/me` e `POST /api/logout`: sessão do dashboard; login e logout exigem `X-CSRF-TOKEN`.
- `POST /api/purchases`: exclusivo de administrador e exige `X-CSRF-TOKEN`. A primeira criação retorna `201`; reenvios retornam `200` e `created: false`.
- `GET /api/purchases`: lista paginada, com filtros opcionais `reference`, `customer` (nome, email ou CPF), `date_from`, `date_to` (`YYYY-MM-DD`, UTC), `amount_min`, `amount_max` (reais) e `page`.
- `GET /api/dashboard`: quantidade, valores e descontos totais/médios; agregações por cliente e produto.
- `POST /api/integrations/woocommerce/webhook`: assinatura HMAC-SHA256 em Base64 do corpo bruto, no cabeçalho `X-WC-Webhook-Signature`, e tópico em `X-WC-Webhook-Topic`. Eventos de outros estados são ignorados. O endpoint anterior `/api/webhooks/woocommerce` permanece compatível.

Exemplo de sessão e compra direta por administrador, em shell com `jq`:

```sh
read -r -p 'Email: ' LOGIN_EMAIL
read -r -s -p 'Senha: ' LOGIN_PASSWORD; printf '\n'
export LOGIN_EMAIL LOGIN_PASSWORD
COOKIE_JAR="$(mktemp)"
csrf="$(curl -fsS -c "$COOKIE_JAR" http://localhost:8080/api/csrf | jq -er .token)"
csrf="$(jq -n '{email: env.LOGIN_EMAIL, password: env.LOGIN_PASSWORD}' | \
  curl -fsS -b "$COOKIE_JAR" -c "$COOKIE_JAR" -H 'Content-Type: application/json' \
    -H 'Accept: application/json' -H "X-CSRF-TOKEN: $csrf" \
    --data @- http://localhost:8080/api/login | jq -er .token)"
unset LOGIN_PASSWORD
curl -X POST http://localhost:8080/api/purchases \
  -b "$COOKIE_JAR" -H "X-CSRF-TOKEN: $csrf" \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{
    "reference": "PED-1001",
    "amount": "180.00",
    "discount": "20.00",
    "purchased_at": "2026-01-15T12:00:00Z",
    "customer": {"name": "Ana Silva", "email": "ana@example.test", "cpf": "12345678901"},
    "products": [{"name": "Camisa", "quantity": 2, "unit_price": "100.00"}],
    "notes": "Enviado por SEDEX"
  }'
curl -b "$COOKIE_JAR" 'http://localhost:8080/api/purchases?reference=PED-1001'
rm -f -- "$COOKIE_JAR"
```

Os valores de entrada são decimais em reais com até duas casas. Campos de saída `_cents` são centavos; as médias podem conter frações de centavo. O resumo de produtos soma quantidade × valor unitário e divide pelo número de compras; por produto, a média é por compra que contém o produto. Cliente é agrupado por CPF e produto pelo nome exato.

No PostgreSQL, as buscas por referência, nome e email não diferenciam maiúsculas de minúsculas. A busca por CPF aceita números com ou sem pontuação.

## Testes e comandos

Com os serviços iniciados:

```sh
make test       # Pest: banco SQLite em memória, sem alterar o banco da aplicação
make lint       # Pint
make stan       # Larastan
make logs
make down       # preserva volumes
```

Para recriar as tabelas da aplicação, `make fresh CONFIRM=fresh` **apaga todos os dados do banco da aplicação**. Não execute em ambiente com dados que deseje preservar. Os volumes da loja não são apagados por esse comando.

A CI executa Pint, Larastan e Pest em PostgreSQL, build e lint do frontend e build do tema da loja, smoke Docker, criação de pedido WooCommerce e checkout automatizado no navegador, conferindo API, dashboard e e-mail no Mailpit. O PostgreSQL da CI é exclusivo do job de testes. Para executar o checkout automatizado localmente, com os serviços e a loja configurados:

```sh
cd frontend
npm ci
npx playwright install chromium
export E2E_LOGIN_EMAIL='email-do-admin'
export E2E_READER_EMAIL='email-do-leitor'
read -r -s -p 'Senha do administrador: ' E2E_LOGIN_PASSWORD; printf '\n'
read -r -s -p 'Senha do leitor: ' E2E_READER_PASSWORD; printf '\n'
export E2E_LOGIN_PASSWORD E2E_READER_PASSWORD
npm run test:checkout
unset E2E_LOGIN_PASSWORD E2E_READER_PASSWORD
```

O teste exige contas previamente criadas, cria um pedido fictício persistente na loja e na aplicação e usa Docker Compose para processar a fila. A execução real da CI depende de publicar o projeto em um repositório GitHub e verificar o resultado em Actions.

## Decisões e trade-offs

- **Idempotência:** `purchases.reference` é única no banco e a gravação dos itens ocorre em transação. Reenvios preservam a compra original. Sob concorrência no PostgreSQL, o índice único impede duas compras com a mesma referência.
- **Centavos:** valores persistidos em inteiros, evitando erros de representação no armazenamento. Valores de compra podem diferir do total dos produtos por frete, taxas ou descontos.
- **Anti-corruption layer:** `OrderMapper` converte o pedido externo em `ProcessPurchaseCommand`. O CPF é obtido de `billing.cpf` ou metadados `_billing_cpf`, `billing_cpf` ou `cpf`; sem CPF no formato esperado o evento retorna `422` e não inventa identidade. O checkout de blocos da loja de teste inclui esse campo; outras lojas podem precisar de extensão própria. O preço unitário aceita até duas casas decimais.
- **Profile:** a loja é opcional para quem deseja testar a API com o simulador. Seus volumes persistem entre inicializações.
- **Escopo de segurança:** perfis, sessão, CSRF e assinatura do webhook reduzem o acesso, mas o ambiente continua exclusivamente de desenvolvimento e ainda precisa das medidas de produção descritas acima.

## Troubleshooting

- **Portas ocupadas:** libere 8080, 5173, 8081 e 8025, ou altere os mapeamentos em `docker-compose.yml`.
- **Serviços ainda inicializando:** consulte `docker compose ps` e `make logs`; a API espera o healthcheck do PostgreSQL e o setup espera o banco da loja.
- **Webhook com 401:** confirme o mesmo `WOOCOMMERCE_WEBHOOK_SECRET` no `.env`, recrie os contêineres com `make up-all` e execute `make seed-woocommerce` para atualizar os webhooks.
- **Webhook com 422:** verifique CPF, email, itens e demais campos obrigatórios do pedido. O corpo e assinatura precisam corresponder exatamente.
- **Loja sem evento:** confirme que o pedido está `processing` ou `completed`, verifique o histórico de entregas e execute `make test-order`. A loja e o WooCommerce devem estar inicializados por `make seed-woocommerce`.
- **Mudança de credenciais do banco:** credenciais usadas na criação dos volumes não são alteradas automaticamente por uma edição posterior do `.env`.
- **Configuração inicial lenta:** na primeira execução são necessários downloads de imagens, dependências e plugin; o prazo depende da rede e do cache.

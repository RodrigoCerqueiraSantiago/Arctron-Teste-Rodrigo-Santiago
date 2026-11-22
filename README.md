# Arctron Analytics

Projeto para o desafio fullstack da Arctron. O dashboard lista compras, permite buscar por referência, cliente, data e valor e mostra os totais e médias das transações.

A API foi feita em Laravel, o frontend em React e o banco é PostgreSQL. Para testar a integração, há uma loja WooCommerce no Docker: quando um pedido é processado, ela envia um webhook para a API e a compra aparece no dashboard.

```text
Loja WooCommerce → webhook → API Laravel → PostgreSQL → dashboard React
```

## Dados do dashboard

O dashboard reúne os dados das compras e apresenta indicadores para apoiar decisões. As informações ficam organizadas em:

- `purchases`: referência, data, valor, desconto e observações da compra.
- `products`: produtos vendidos.
- `customers`: clientes que fizeram as compras.
- `purchase_items`: produtos, quantidades e preços de cada compra.

Cada referência de compra é única. Se a mesma referência for enviada novamente, a API mantém a compra original e não cria outra.

## Pré-requisitos

- Docker e Docker Compose v2.
- GNU Make, Bash (ou Git Bash no Windows), `curl` e `jq`.
- Acesso à internet e as portas 8080, 5173, 8081 e 8025 livres.

## Início rápido

Na pasta do projeto, execute:

```sh
cp .env.example .env
make up
make migrate
docker compose exec api php artisan dashboard:create-user
make seed-woocommerce
```

O primeiro comando copia as configurações de exemplo e só é necessário na primeira instalação. Se já existir um `.env`, não o sobrescreva: ele contém a configuração do ambiente. O `make up` inicia a API, o banco e o frontend; `make seed-woocommerce` inicia e configura a loja, os produtos e os webhooks. Também é possível usar `make up-all` para iniciar os serviços principais e a loja antes da migration.

A primeira execução pode demorar por causa dos downloads. Os dados permanecem nos volumes quando os serviços são reiniciados.

Ao criar o usuário, escolha `admin` e informe nome, e-mail e senha. A senha precisa ter pelo menos 12 caracteres e no máximo 72 bytes. Para criar um usuário de leitura, execute o mesmo comando e escolha `reader`. O dashboard não tem usuário padrão.

| Serviço | Endereço |
|---|---|
| Login e dashboard | http://localhost:5173 |
| API de compras | http://localhost:8080/api/purchases |
| Indicadores da API | http://localhost:8080/api/dashboard |
| Healthcheck | http://localhost:8080/up |
| Contrato OpenAPI | http://localhost:8080/openapi.yaml |
| Loja WooCommerce | http://localhost:8081 |
| Administração WordPress | http://localhost:8081/wp-admin |
| E-mails de teste (Mailpit) | http://localhost:8025 |

Para entrar no WordPress local, use `admin` e `change-me-dev-only`. Essas credenciais e as configurações de exemplo são apenas para desenvolvimento. Altere o `.env` antes da primeira inicialização e não o envie ao repositório. O usuário do WordPress é salvo no banco: mudar a variável depois não altera a conta já criada.

As portas são de serviços diferentes: `8081` é a loja, `5173` é o login e o dashboard e `8080` é a API Laravel. As rotas `/api/purchases` e `/api/dashboard` não existem na porta da loja.

Para consultar essas rotas no navegador, primeiro entre no dashboard com o usuário criado acima e depois abra os links da API na tabela. Use `localhost` nos dois endereços para compartilhar a sessão. Sem login, a API retorna `401`. As rotas retornam JSON; a interface visual fica no dashboard.

## Roteiro para a apresentação

Antes de apresentar:

1. Inicie o Docker e, na pasta do projeto, confira os serviços com `docker compose ps`. Se o ambiente já foi configurado e estiver parado, execute `make up-all`. No Windows, os comandos `make` podem ser executados pelo Git Bash.
2. Acesse http://localhost:5173 e entre no dashboard. Se ainda não houver uma conta, execute `docker compose exec api php artisan dashboard:create-user`, escolha `admin` e crie sua senha. Essa conta é diferente do usuário do WordPress.
3. Abra também a loja em http://localhost:8081/shop/ e o Mailpit em http://localhost:8025.

Durante a demonstração:

1. Na loja, adicione um produto ao carrinho, confira o total e finalize a compra com dados fictícios e **Pagamento de teste na entrega**.
2. Anote o número do pedido. Com o pedido processado, o WooCommerce envia um webhook assinado para a API Laravel, que grava a compra no PostgreSQL.
3. No dashboard, busque por `woocommerce:<número do pedido>`. Mostre a compra, os filtros e os indicadores de totais e médias. O dashboard consulta a API Laravel, não a API do WooCommerce.
4. No mesmo navegador autenticado, abra http://localhost:8080/api/purchases e http://localhost:8080/api/dashboard para mostrar as respostas JSON. Use `localhost` tanto no dashboard quanto na API.
5. Se desejar, mostre a confirmação no Mailpit. As mensagens ficam no ambiente de teste; não são entregues a endereços reais.

Um reenvio com a mesma referência não cria uma segunda compra. Para demonstrar sem passar pelo checkout, use `make simulate-order ARGS="--reference=987654321"` e busque `woocommerce:987654321`. Se um pedido da loja não aparecer, confira seu status e execute as ações agendadas indicadas em [Testar a integração](#testar-a-integração).

Se uma rota da API retornar `401`, faça login no dashboard. Um `404` em `localhost:8081/api/...` indica que a rota foi aberta na porta da loja; use a porta `8080`. O endpoint de login é `POST /api/login`, não uma página para abrir diretamente no navegador.

## Acesso e proteção de dados

- O **administrador** pode consultar os dados das compras e cadastrar compras pela API.
- O **leitor** pode consultar o dashboard e as compras, mas não recebe CPF, e-mail ou observações. A busca por cliente considera apenas o nome.
- O webhook usa uma assinatura própria e não depende do login no dashboard. O acesso pela interface usa sessão e proteção CSRF; as tentativas de login são limitadas. O Nginx da API não registra logs de acesso com os filtros pesquisados.
- CPF e e-mail dos clientes, além do CPF, e-mail e observações gravados em cada compra, são criptografados pela aplicação. Um índice HMAC do CPF permite identificar o mesmo cliente sem guardar esse campo em texto puro. Nomes continuam em texto puro.
- A busca parcial de e-mail e CPF é feita pela aplicação após descriptografar os candidatos, preservando os filtros. Ela pode ser lenta em bases grandes.

O projeto é um ambiente de demonstração. O banco do WooCommerce permanece separado e pode conter dados pessoais sem essa criptografia. Backups anteriores, logs de transações e páginas antigas do PostgreSQL também podem conter os dados originais. Desativar o log de acesso não substitui uma revisão dos outros logs. Para produção, ainda seriam necessários certificados confiáveis, HTTPS também para a loja, credenciais próprias, proteção de rede, políticas de retenção e uma estratégia de gestão de chaves.

### Atualização de um ambiente existente

Antes de atualizar, faça um backup testado do PostgreSQL e guarde a `APP_KEY` atual em local seguro. A migration criptografa os dados já existentes; execute-a com a aplicação sem receber novas compras. **Não altere a chave de um ambiente com dados criptografados:** ela é necessária para lê-los e para identificar clientes pelo CPF. A troca da chave exige um processo próprio de migração dos dados e do índice.

Depois de atualizar o código e a imagem da API, execute `make migrate`. Não use `make fresh` para essa atualização.

### HTTPS local (opcional)

Para testar o dashboard com HTTPS, forneça um certificado e uma chave válidos para `localhost`, acessíveis ao Docker. Não envie a chave privada ao repositório. Configure `TLS_CERT_PATH`, `TLS_KEY_PATH` e `SESSION_SECURE_COOKIE=true` no `.env` e execute:

```sh
docker compose up -d api
docker compose --profile tls up -d tls
```

Depois, abra `https://localhost:8443`. O proxy cobre o dashboard e a API; a loja continua em HTTP local. Para voltar ao dashboard HTTP, defina `SESSION_SECURE_COOKIE=false` e recrie a API. Com cookie seguro habilitado, o login não funciona por HTTP.

## Testar a integração

Para simular um pedido sem passar pela loja:

```sh
make simulate-order ARGS="--reference=987654321"
```

Entre no dashboard e busque `woocommerce:987654321`. A referência é o ID numérico do pedido; se for omitida, o comando gera uma. No modo síncrono, repetir o mesmo ID retorna `created: false`. Na fila, o reenvio pode retornar `202` novamente, mas não cria outra compra. O parâmetro `--url` permite escolher outro destino acessível pelo contêiner da API.

Para gerar um pedido na loja de teste pelo terminal:

```sh
make test-order
```

O comando cria um pedido fictício com status `processing` e informa o ID. Busque `woocommerce:<ID>` no dashboard ou na API.

Também é possível testar pelo navegador:

1. Abra http://localhost:8081/shop/ e adicione um produto ao carrinho.
2. Finalize o pedido com dados fictícios e um CPF de 11 dígitos.
3. Escolha **Pagamento de teste na entrega**. Nenhum pagamento será cobrado.
4. Busque `woocommerce:<número do pedido>` no dashboard e confira o e-mail no Mailpit.

O campo de CPF confere apenas se há 11 dígitos; ele não valida os dígitos verificadores. O Mailpit captura as mensagens localmente e não envia e-mails reais.

O WooCommerce envia os eventos `order.created` e `order.updated` para `http://nginx/api/integrations/woocommerce/webhook`. Pedidos pendentes são ignorados e podem ser importados quando passam para `processing` ou `completed`. Se o pedido não aparecer, execute as ações agendadas:

```sh
docker compose exec -T wpcli wp action-scheduler run --batch-size=100 --batches=5
```

O plugin em `docker/woocommerce/allow-internal-webhook.php` permite o envio ao host interno `nginx`, sem expor a API publicamente. O ping inicial do WooCommerce não tem assinatura e não grava compras; os eventos de pedido precisam de assinatura válida.

A loja tem um tema demonstrativo inspirado nas cores do Brasil, sem vínculo com a CBF. Após alterar `frontend/src/store.css`, execute `npm run build:store` dentro de `frontend` para atualizar o CSS do tema.

### Fila RabbitMQ (opcional)

Por padrão, o webhook continua processando a compra imediatamente. Para usar a fila, primeiro inicie o broker e o worker, depois configure `WOOCOMMERCE_QUEUE_CONNECTION=rabbitmq` no `.env` e recrie a API:

```sh
docker compose --profile rabbitmq up -d --build rabbitmq queue-worker
docker compose up -d --build api
```

O webhook valida a assinatura e os dados antes de publicar uma mensagem criptografada. A resposta `202` confirma o recebimento pela fila, não a conclusão da compra; o dashboard pode demorar alguns segundos para atualizá-la. Se o broker não aceitar a mensagem, a API retorna erro para que o WooCommerce possa tentar novamente. O worker tenta processar falhas novamente e mantém a regra de referência única.

Para acompanhar a operação local:

```sh
docker compose --profile rabbitmq logs -f queue-worker rabbitmq
docker compose exec -T api php artisan queue:failed
```

Os eventos de enfileiramento, processamento, assinatura inválida e falha final são registrados sem CPF ou e-mail no contexto do evento. Outros erros e seus logs também precisam ser revisados antes de usar dados reais. O RabbitMQ usa volume persistente e credenciais de exemplo; altere-as antes de uso fora do ambiente local. `make down` para os serviços sem apagar os volumes.

## API

- `GET /api/csrf`, `POST /api/login`, `GET /api/me` e `POST /api/logout`: sessão do dashboard; login e logout exigem `X-CSRF-TOKEN`.
- `POST /api/purchases`: exclusivo de administrador e exige `X-CSRF-TOKEN`. A primeira criação retorna `201`; reenvios retornam `200` e `created: false`.
- `GET /api/purchases`: lista paginada, com filtros opcionais `reference`, `customer` (nome, email ou CPF), `date_from`, `date_to` (`YYYY-MM-DD`, UTC), `amount_min`, `amount_max` (reais) e `page`.
- `GET /api/dashboard`: quantidade, valores e descontos totais/médios; agregações por cliente e produto.
- `POST /api/integrations/woocommerce/webhook`: assinatura HMAC-SHA256 em Base64 do corpo bruto, no cabeçalho `X-WC-Webhook-Signature`, e tópico em `X-WC-Webhook-Topic`. Eventos de outros estados são ignorados. No modo síncrono retorna `201` na criação e `200` para duplicatas; no modo RabbitMQ retorna `202` após enfileirar. O endpoint anterior `/api/webhooks/woocommerce` permanece compatível.

Para enviar uma compra diretamente, entre com uma conta de administrador. Exemplo em shell com `jq`:

```bash
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

Os valores de entrada são em reais, com até duas casas decimais. Os campos terminados em `_cents` são retornados em centavos. Para o total de produtos, a API soma quantidade × valor unitário; a média geral divide esse total pelo número de compras. A média por produto considera apenas as compras que o contêm. Clientes são agrupados por CPF e produtos pelo nome.

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

A configuração do GitHub Actions inclui os testes, verificações de código, builds, checkout e processamento com RabbitMQ. Confira o resultado da execução mais recente na aba **Actions** do repositório.

Para rodar o teste de checkout localmente, com a loja configurada e contas de administrador e leitor criadas:

```bash
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

O teste cria um pedido fictício que permanece nos bancos da loja e da aplicação. Ele também usa o Docker Compose para processar a fila de webhooks.

## Algumas decisões

- A referência da compra tem índice único. Os itens são gravados em transação, e um reenvio preserva a compra original.
- Os valores são armazenados em centavos para evitar erros de representação no banco. O valor da compra pode ser diferente da soma dos produtos por causa de descontos, frete ou taxas.
- `OrderMapper` transforma os dados do WooCommerce antes de enviá-los ao serviço de compras. Ele aceita CPF em `billing.cpf` ou nos metadados `_billing_cpf`, `billing_cpf` e `cpf`. Se o CPF estiver ausente ou fora do formato esperado, o webhook retorna `422`. Outras lojas podem precisar de um campo próprio para coletá-lo.
- A loja é opcional para testar a API: o comando de simulação também envia um webhook assinado. O projeto usa REST, com processamento síncrono por padrão e RabbitMQ opcional.

## Troubleshooting

- **Portas ocupadas:** libere 8080, 5173, 8081 e 8025, ou altere os mapeamentos em `docker-compose.yml`.
- **Serviços ainda inicializando:** consulte `docker compose ps` e `make logs`; a API espera o healthcheck do PostgreSQL e o setup espera o banco da loja.
- **Webhook com 401:** confirme o mesmo `WOOCOMMERCE_WEBHOOK_SECRET` no `.env`, recrie os contêineres com `make up-all` e execute `make seed-woocommerce` para atualizar os webhooks.
- **Webhook com 422:** verifique CPF, email, itens e demais campos obrigatórios do pedido. O corpo e assinatura precisam corresponder exatamente.
- **Loja sem evento:** confirme que o pedido está `processing` ou `completed`, verifique o histórico de entregas e execute `make test-order`. A loja e o WooCommerce devem estar inicializados por `make seed-woocommerce`.
- **Mudança de credenciais do banco:** credenciais usadas na criação dos volumes não são alteradas automaticamente por uma edição posterior do `.env`.
- **Configuração inicial lenta:** na primeira execução são necessários downloads de imagens, dependências e plugin; o prazo depende da rede e do cache.

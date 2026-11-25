# Desafio Fullstack Arctron

Dashboard de compras com API REST, interface React e integração com uma loja WooCommerce local.

```text
WooCommerce → webhook assinado → API Laravel → PostgreSQL → dashboard React
```

## Funcionalidades

- Recebe compras com referência única; reenvios não duplicam os registros.
- Pesquisa por referência, cliente, datas e valores, com paginação e ordenação.
- Apresenta totais e médias de compras, descontos e produtos, além de agregações por cliente e produto.
- Integra pedidos WooCommerce por webhook HMAC-SHA256. O processamento é síncrono por padrão; RabbitMQ é opcional.
- Separa os perfis de administrador e leitor. CPF, e-mail e observações são criptografados; nomes permanecem em texto claro. Leitores não recebem CPF, e-mail ou observações.

Valores monetários são armazenados em centavos. O total de produtos é calculado como quantidade × preço unitário; descontos e outros ajustes podem fazer esse total diferir do valor da compra.

## Tecnologias

- PHP 8.3 e Laravel 13
- React, Vite e Tailwind CSS
- PostgreSQL
- WooCommerce e WordPress
- Docker Compose v2
- RabbitMQ opcional

## Requisitos

Docker com Docker Compose v2, GNU Make e Bash. No Windows, use Git Bash com GNU Make. Node.js 22 e npm são necessários para os checks locais do frontend. A primeira execução precisa de internet para baixar imagens e dependências. As portas `8080`, `5173`, `8081` e `8025` devem estar disponíveis.

## Iniciar o ambiente

Na raiz do projeto:

```sh
cp .env.example .env
make up-all
make migrate
docker compose exec api php artisan dashboard:create-user
make seed-woocommerce
```

O comando de criação solicita nome, e-mail, senha e perfil. Crie um usuário `admin` para administrar o painel; um usuário `reader` pode ser criado da mesma forma para avaliar as permissões de leitura. As senhas devem ter ao menos 12 caracteres e no máximo 72 bytes.

O `.env.example` contém valores exclusivamente locais. Configure segredos próprios antes de qualquer uso fora da máquina de desenvolvimento; não envie o arquivo `.env` ao Git.

| Serviço | Endereço |
|---|---|
| Login e dashboard | http://127.0.0.1:5173 |
| API de compras | http://127.0.0.1:8080/api/purchases |
| Indicadores | http://127.0.0.1:8080/api/dashboard |
| Healthcheck | http://127.0.0.1:8080/up |
| Contrato OpenAPI | http://127.0.0.1:8080/openapi.yaml |
| Loja WooCommerce | http://localhost:8081 |
| Administração WordPress | http://localhost:8081/wp-admin |
| E-mails locais (Mailpit) | http://127.0.0.1:8025 |

O usuário do dashboard é independente do usuário do WordPress. Para a instalação local, as credenciais do WordPress são configuradas pelo `.env`; os valores de exemplo não devem ser usados em produção.

Esses endereços funcionam na máquina em que os serviços foram iniciados. Para acessar diretamente as compras e os indicadores da API pelo navegador, entre primeiro no dashboard usando `127.0.0.1`; sem autenticação, a API retorna `401`. O uso do mesmo endereço garante que a sessão seja compartilhada entre o dashboard e a API.

## Avaliar a integração

Para criar um pedido de teste na loja:

```sh
make test-order
```

O comando informa o número do pedido. Aguarde o webhook e procure no dashboard pela referência `woocommerce:<número>` ou pelo rótulo exibido `Pedido WooCommerce nº <número>`. Também é possível fazer o checkout pela loja usando dados fictícios e o método **Pagamento de teste na entrega**; nenhum pagamento real é processado.

Para enviar uma compra de teste sem usar a loja:

```sh
make simulate-order ARGS="--reference=987654321"
```

Pesquise por `woocommerce:987654321`. Repetir o comando com a mesma referência demonstra a idempotência: a compra original é mantida. Pedidos e compras de demonstração ficam nos bancos locais e persistem após reiniciar os contêineres.

## Testes e qualidade

Com os serviços iniciados, execute na raiz:

```sh
make test
make lint
make stan
```

Para lint e builds do frontend:

```sh
cd frontend
npm ci
npm run lint
npm run build
npm run build:store
```

O teste de checkout com Playwright requer a loja configurada, contas de administrador e leitor, e as variáveis `E2E_LOGIN_EMAIL`, `E2E_LOGIN_PASSWORD`, `E2E_READER_EMAIL` e `E2E_READER_PASSWORD` definidas no ambiente. Ele cria um pedido local que permanece nos bancos. A integração contínua executa os testes, análises, builds, checkout e processamento opcional com RabbitMQ.

## API

- `GET /api/csrf`, `POST /api/login`, `GET /api/me` e `POST /api/logout`: sessão do dashboard.
- `POST /api/purchases`: cadastro autenticado de compra; exclusivo para administradores.
- `GET /api/purchases`: listagem com `reference`, `customer`, `date_from`, `date_to`, `amount_min`, `amount_max` e `page`. A ordenação aceita `sort_by` e `sort_direction`.
- `GET /api/dashboard`: indicadores agregados para todo o período.
- `POST /api/integrations/woocommerce/webhook`: recebe eventos assinados do WooCommerce. O endpoint compatível `/api/webhooks/woocommerce` também está disponível.

Para buscar um pedido WooCommerce, a referência canônica da API é `woocommerce:<número do pedido>`. A interface também aceita o rótulo em português exibido na tabela.

O webhook valida a assinatura HMAC-SHA256 do corpo da requisição. Pedidos pendentes são ignorados; pedidos processados ou concluídos são importados. O RabbitMQ pode ser habilitado configurando `WOOCOMMERCE_QUEUE_CONNECTION=rabbitmq` no `.env` e iniciando o perfil:

```sh
docker compose --profile rabbitmq up -d --build rabbitmq queue-worker
docker compose up -d --build api
```

## Ambiente local e dados

Este projeto configura uma demonstração, não uma instalação de produção. Antes de expor serviços publicamente, substitua as credenciais de exemplo, configure HTTPS e revise acesso de rede, retenção de dados, backups e gestão da chave de criptografia.

`make down` interrompe os serviços e preserva os volumes. `make fresh CONFIRM=fresh` recria as tabelas e apaga os dados da aplicação; não o execute se quiser preservar compras de demonstração.

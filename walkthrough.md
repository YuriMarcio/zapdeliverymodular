# Walkthrough - Migrations e Models Multitenant SaaS (WhatsApp Delivery)

Concluímos com sucesso a reestruturação e a modelagem do banco de dados do sistema SaaS de Delivery Multitenant integrado via WhatsApp. A arquitetura foi construída de forma limpa, robusta, altamente isolada e aderente às melhores práticas de desenvolvimento Laravel.

---

## 🏆 Resumo das Conquistas e Resultados

1. **27 Migrations Criadas e Executadas:** Criamos todo o schema do banco de dados estruturado em 27 migrações sequenciais ordenadas perfeitamente. Todas foram executadas com sucesso através de `php artisan migrate:fresh` sem nenhum erro de restrição de chave estrangeira.
2. **27 Models Eloquent Robustos:** Criamos os modelos com `$fillable` completo, casts automáticos de tipos nativos (decimais para finanças, arrays/json, booleanos e datetimes) e relacionamentos reversos completos.
3. **Padrão Absoluto de tenant_id:** Em total sintonia com a **Regra 1**, todas as tabelas (exceto `tenants`) possuem `tenant_id` indexado e com integridade referencial `cascadeOnDelete()`.
4. **Tratamento de URLs Externas (Regra 2):** Todos os campos de imagem/arquivos foram modelados como `string` normais e com `nullable()`, ideais para salvar URLs absolutas externas (S3, CDN, Cloudflare, etc.).
5. **Middleware de Assinatura Inteligente:** Criamos o middleware `CheckTenantSubscription` (alias `tenant.subscription`), pronto para validar webhooks do WhatsApp/Evolution.

---

## 📂 Visão Geral dos Arquivos Implementados

### 1. Configurações, Lojas e Assinatura SaaS
* **`Tenant` (`app/Models/Tenant.php` & `000001_create_tenants_table.php`):**
  A loja/restaurante central no SaaS. Possui as configurações visuais (`primary_color`, `logo_url`) e a instância ativa do WhatsApp.
* **`TenantSubscription` (`app/Models/TenantSubscription.php` & `000002_create_tenant_subscriptions_table.php`):**
  Assinatura do SaaS (Basic, Pro, Max) com status e datas de expiração/trial.
* **`TenantInvoice` (`app/Models/TenantInvoice.php` & `000003_create_tenant_invoices_table.php`):**
  Faturamento da mensalidade da loja.
* **`User` (`app/Models/User.php` & `000004_create_users_table.php`):**
  Usuários de administração do painel do lojista (dono, gerente, atendente) com integração JWT mantida.
* **`TenantGateway` (`app/Models/TenantGateway.php` & `000005_create_tenant_gateways_table.php`):**
  Credenciais de pagamento do Mercado Pago de cada lojista (Token, Public Key, Refresh Token, etc.).

### 2. Cardápio e Produtos
* **`Category` (`app/Models/Category.php` & `000007_create_categories_table.php`):**
  Categorias do cardápio (ex: Pizzas, Bebidas) ordenadas e ativáveis.
* **`Product` (`app/Models/Product.php` & `000008_create_products_table.php`):**
  Produtos do cardápio com suporte a preços promocionais e destaques.
* **`ProductOptionGroup` (`app/Models/ProductOptionGroup.php` & `000009_create_product_option_groups_table.php`):**
  Grupos de adicionais/tamanhos (ex: "Escolha o tamanho", "Deseja adicionais?").
* **`ProductOption` (`app/Models/ProductOption.php` & `000010_create_product_options_table.php`):**
  Os itens selecionáveis do grupo (ex: Bacon R$ 3,00, Cheddar R$ 4,50). Possui `tenant_id` (Regra 1).

### 3. Clientes e WhatsApp
* **`Customer` (`app/Models/Customer.php` & `000011_create_customers_table.php`):**
  Clientes finais que realizam pedidos via WhatsApp. Contém o histórico de pedidos e notas.
* **`CustomerAddress` (`app/Models/CustomerAddress.php` & `000012_create_customer_addresses_table.php`):**
  Endereços salvos do cliente para entrega rápida.
* **`WhatsappInstance` (`app/Models/WhatsappInstance.php` & `000006_create_whatsapp_instances_table.php`):**
  Instância ativa do WhatsApp vinculada à Evolution API (qrcode, status, conexões).
* **`Conversation` (`app/Models/Conversation.php` & `000013_create_conversations_table.php`):**
  Sessões de bate-papo abertas com os clientes, com atendente responsável (`assigned_to`).
* **`Message` (`app/Models/Message.php` & `000014_create_messages_table.php`):**
  Mensagens enviadas/recebidas via WhatsApp. O campo `media_url` armazena o endereço CDN do arquivo.

### 4. Vendas (Carrinho, Pedidos e Cupons)
* **`Cart` (`app/Models/Cart.php` & `000015_create_carts_table.php`):**
  Carrinho ativo do cliente (`open`, `closed`, `abandoned`).
* **`CartItem` (`app/Models/CartItem.php` & `000016_create_cart_items_table.php`):**
  Produtos no carrinho. Contém `tenant_id`.
* **`CartItemOption` (`app/Models/CartItemOption.php` & `000017_create_cart_item_options_table.php`):**
  Adicionais selecionados para aquele item do carrinho. Contém `tenant_id`.
* **`Order` (`app/Models/Order.php` & `000018_create_orders_table.php`):**
  Pedido consolidado, com status de produção (`preparing`, `delivery`, `completed`), valores consolidados de entrega e cupom, e um snapshot do endereço de entrega em JSON.
* **`OrderItem` (`app/Models/OrderItem.php` & `000019_create_order_items_table.php`):**
  Snapshot dos itens comprados no pedido. Contém `tenant_id`.
* **`OrderItemOption` (`app/Models/OrderItemOption.php` & `000020_create_order_item_options_table.php`):**
  Snapshot dos adicionais do item comprado. Contém `tenant_id`.
* **`Coupon` (`app/Models/Coupon.php` & `000021_create_coupons_table.php`):**
  Cupons de desconto configuráveis (fixo ou percentual) com validade e limites de uso.

### 5. Controle Financeiro
* **`Wallet` (`app/Models/Wallet.php` & `000022_create_wallets_table.php`):**
  Carteira virtual do restaurante, registrando saldos disponíveis e pendentes.
* **`WalletTransaction` (`app/Models/WalletTransaction.php` & `000023_create_wallet_transactions_table.php`):**
  Extrato da carteira, detalhando taxas cobradas pela plataforma e pelo gateway.
* **`Withdrawal` (`app/Models/Withdrawal.php` & `000024_create_withdrawals_table.php`):**
  Solicitações de saques bancários dos lojistas, com snapshot dos dados bancários em JSON e `receipt_url` externa para o comprovante.

### 6. Automações e Métricas
* **`Flow` (`app/Models/Flow.php` & `000025_create_flows_table.php`):**
  Automações/Funis de mensagens disparados por gatilhos.
* **`FlowStep` (`app/Models/FlowStep.php` & `000026_create_flow_steps_table.php`):**
  Etapas da automação com atraso configurável e payload do conteúdo em JSON. Contém `tenant_id`.
* **`AnalyticsEvent` (`app/Models/AnalyticsEvent.php` & `000027_create_analytics_events_table.php`):**
  Eventos do funil do cliente (ex: `opened_menu`, `added_to_cart`, `started_checkout`) para relatórios avançados.

---

## 🔒 Funcionamento Detalhado do Middleware `CheckTenantSubscription`

Localizado em `app/Http/Middleware/CheckTenantSubscription.php`, o middleware realiza uma verificação em múltiplos níveis para identificar e validar o Tenant, tornando-o extremamente versátil para todas as formas de requisição (Painel Web, Webhook Evolution API, chamadas de API externas):

1. **Contexto Ativo do Tenancy:** Se a rota já inicializou o tenant automaticamente via subdomínio/domínio (`tenancy()->initialized`), ele assume este tenant.
2. **Cabeçalho Customizado:** Se houver um header `X-Tenant-ID`, ele busca o tenant por este ID.
3. **Parâmetros da Rota:** Se houver um parâmetro na URL como `/api/webhook/{tenant}` ou `{tenant_id}`, ele resolve buscando pelo ID ou pelo slug.
4. **Body JSON ou Query Params:** Busca campos como `tenant_id` ou `tenant` passados no payload.
5. **Integração WhatsApp (Evolution API):** Se a requisição contiver `instance`, `instance_name` ou `instanceName` (padrão de webhooks da Evolution API), ele busca qual Tenant está associado àquela instância no banco de dados.

**Validação de Assinatura:**
Uma vez identificado o Tenant, ele consulta se existe alguma assinatura na tabela `tenant_subscriptions` com:
* Status igual a `active` ou `trialing`.
* Data limite de uso (`expires_at`) maior/igual à data e hora atual (ou nula, que indica expiração indefinida).

Caso a validação falhe (assinatura vencida, cancelada ou inadimplente), ele interrompe a requisição e retorna um HTTP `403 Forbidden` com a seguinte resposta estruturada em JSON:
```json
{
  "success": false,
  "error": "Tenant subscription is inactive, expired, or unpaid."
}
```

---

## 🔬 Relatório dos Testes Executados com Sucesso

Rodamos as migrações dentro do container Docker principal da aplicação (`deliveryzapapi-app`).
Todas as 27 migrações foram criadas em ordem, respeitando todas as dependências de chaves estrangeiras.

**Log de execução da migração:**
```bash
$ docker exec deliveryzapapi-app php artisan migrate:fresh

Dropping all tables .......................................... 541.74ms DONE
INFO  Preparing database.  
Creating migration table ...................................... 34.10ms DONE
INFO  Running migrations.  

2026_05_27_000001_create_tenants_table ........................ 60.88ms DONE
2026_05_27_000002_create_tenant_subscriptions_table .......... 110.70ms DONE
2026_05_27_000003_create_tenant_invoices_table ............... 138.51ms DONE
2026_05_27_000004_create_users_table .......................... 94.29ms DONE
2026_05_27_000005_create_tenant_gateways_table ................ 79.77ms DONE
2026_05_27_000006_create_whatsapp_instances_table ............. 79.88ms DONE
2026_05_27_000007_create_categories_table ..................... 84.49ms DONE
2026_05_27_000008_create_products_table ...................... 147.40ms DONE
2026_05_27_000009_create_product_option_groups_table ......... 122.52ms DONE
2026_05_27_000010_create_product_options_table ............... 109.99ms DONE
2026_05_27_000011_create_customers_table ...................... 62.47ms DONE
2026_05_27_000012_create_customer_addresses_table ............ 123.87ms DONE
2026_05_27_000013_create_conversations_table ................. 172.29ms DONE
2026_05_27_000014_create_messages_table ...................... 112.68ms DONE
2026_05_27_000015_create_carts_table ......................... 112.10ms DONE
2026_05_27_000016_create_cart_items_table .................... 180.29ms DONE
2026_05_27_000017_create_cart_item_options_table ............. 176.29ms DONE
2026_05_27_000018_create_orders_table ........................ 159.32ms DONE
2026_05_27_000019_create_order_items_table ................... 198.46ms DONE
2026_05_27_000020_create_order_item_options_table ............ 136.87ms DONE
2026_05_27_000021_create_coupons_table ........................ 63.19ms DONE
2026_05_27_000022_create_wallets_table ........................ 69.69ms DONE
2026_05_27_000023_create_wallet_transactions_table ........... 217.42ms DONE
2026_05_27_000024_create_withdrawals_table ................... 137.47ms DONE
2026_05_27_000025_create_flows_table .......................... 74.98ms DONE
2026_05_27_000026_create_flow_steps_table .................... 112.08ms DONE
2026_05_27_000027_create_analytics_events_table .............. 110.69ms DONE
```

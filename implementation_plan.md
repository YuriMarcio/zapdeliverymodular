# Plano de Implementação - Migrations e Models Multitenant SaaS

Este plano descreve a criação das Migrações e dos Modelos (com relacionamentos e propriedades fillable) para o sistema SaaS de Delivery Multitenant integrado via WhatsApp, em total conformidade com as regras gerais especificadas.

## Regras Gerais Implementadas

1. **tenant_id em todas as tabelas**: Todas as tabelas, exceto `tenants` em si, possuem a chave estrangeira `tenant_id` referenciando `tenants(id)` com restrição `cascadeOnDelete`.
2. **Armazenamento de URLs externas**: Campos que se referem a imagens/arquivos são modelados como strings (URLs completas e externas), já que os dados serão hospedados em um bucket S3 ou outro serviço de armazenamento externo.
3. **Ordem de Execução das Migrações**: As migrações serão nomeadas sequencialmente com timestamps ordenados para garantir que sejam executadas exatamente na ordem sugerida pelo usuário, prevenindo qualquer erro de chave estrangeira.
4. **Substituição dos Modelos e Migrações antigos**: Para manter o projeto limpo e consistente com o novo schema multitenant, os modelos e migrações pré-existentes obsoletos serão removidos do projeto.

---

## Proposta de Arquivos

### 1. Limpeza do Diretório e Exclusão de Arquivos Obsoletos
Removeremos migrações e modelos antigos no diretório para evitar conflitos de nomenclatura de tabelas (ex: `products`, `orders`, `categories`) e campos obsoletos.

### 2. Criação das Migrações (em ordem de execução)
As novas migrações serão criadas no diretório `database/migrations/` com a nomenclatura sequencial baseada na data `2026_05_27`:
- `2026_05_27_000001_create_tenants_table.php`
- `2026_05_27_000002_create_tenant_subscriptions_table.php`
- `2026_05_27_000003_create_tenant_invoices_table.php`
- `2026_05_27_000004_create_users_table.php`
- `2026_05_27_000005_create_tenant_gateways_table.php`
- `2026_05_27_000006_create_whatsapp_instances_table.php`
- `2026_05_27_000007_create_categories_table.php`
- `2026_05_27_000008_create_products_table.php`
- `2026_05_27_000009_create_product_option_groups_table.php`
- `2026_05_27_000010_create_product_options_table.php`
- `2026_05_27_000011_create_customers_table.php`
- `2026_05_27_000012_create_customer_addresses_table.php`
- `2026_05_27_000013_create_conversations_table.php`
- `2026_05_27_000014_create_messages_table.php`
- `2026_05_27_000015_create_carts_table.php`
- `2026_05_27_000016_create_cart_items_table.php`
- `2026_05_27_000017_create_cart_item_options_table.php`
- `2026_05_27_000018_create_orders_table.php`
- `2026_05_27_000019_create_order_items_table.php`
- `2026_05_27_000020_create_order_item_options_table.php`
- `2026_05_27_000021_create_coupons_table.php`
- `2026_05_27_000022_create_wallets_table.php`
- `2026_05_27_000023_create_wallet_transactions_table.php`
- `2026_05_27_000024_create_withdrawals_table.php`
- `2026_05_27_000025_create_flows_table.php`
- `2026_05_27_000026_create_flow_steps_table.php`
- `2026_05_27_000027_create_analytics_events_table.php`

### 3. Criação dos Modelos Laravel
Criaremos ou atualizaremos os seguintes modelos dentro de `app/Models/` com os seus respectivos fillables, casts e relacionamentos Eloquent completos:
1. `Tenant`
2. `TenantSubscription`
3. `TenantInvoice`
4. `User` (Substituindo o original para integrar com o novo schema)
5. `TenantGateway`
6. `WhatsappInstance`
7. `Category`
8. `Product`
9. `ProductOptionGroup`
10. `ProductOption`
11. `Customer`
12. `CustomerAddress`
13. `Conversation`
14. `Message`
15. `Cart`
16. `CartItem`
17. `CartItemOption`
18. `Order`
19. `OrderItem`
20. `OrderItemOption`
21. `Coupon`
22. `Wallet`
23. `WalletTransaction`
24. `Withdrawal`
25. `Flow`
26. `FlowStep`
27. `AnalyticsEvent`

### 4. Middleware `CheckTenantSubscription`
O middleware `CheckTenantSubscription` será criado em `app/Http/Middleware/CheckTenantSubscription.php`.
Ele validará se o `tenant_id` (que pode vir na requisição, no cabeçalho ou ser deduzido por rota/subdomínio) possui uma assinatura com status `active` ou `trialing` antes de processar webhooks do WhatsApp.

---

## Detalhamento das Migrações e Atributos

Todas as migrações que possuem o campo `tenant_id` farão o relacionamento da seguinte maneira:
```php
$table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
```

Os campos de imagens (`logo_url`, `image_url`, `promotion_img_url`, `media_url`, `receipt_url`) serão criados como:
```php
$table->string('logo_url')->nullable(); // ou image_url, etc.
```

Os valores monetários serão modelados com precisão ideal para finanças:
```php
$table->decimal('amount', 10, 2); // ou price, total, etc.
```

---

## Plano de Verificação

### Teste Automatizado
- Executaremos `php artisan migrate:fresh` para verificar a ausência de erros de foreign key e garantir que o banco seja montado perfeitamente na ordem especificada.
- Executaremos análises estáticas/verificações do PHP para garantir que não haja erros de sintaxe nos modelos e no middleware.

### Verificação Manual
- Verificação do mapeamento de relacionamentos no Tinker (ex: `$tenant->users` ou `$order->items`).

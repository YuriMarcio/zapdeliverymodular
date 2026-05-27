<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends BaseTenant
{
    protected $fillable = [
        'id',
        'name',
        'slug',
        'plan',
        'status',
        'whatsapp_instance',
        'whatsapp_connected',
        'phone',
        'logo_url',
        'primary_color',
    ];

    protected $casts = [
        'whatsapp_connected' => 'boolean',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'plan',
            'status',
            'whatsapp_instance',
            'whatsapp_connected',
            'phone',
            'logo_url',
            'primary_color',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class, 'tenant_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(TenantInvoice::class, 'tenant_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'tenant_id');
    }

    public function gateways(): HasMany
    {
        return $this->hasMany(TenantGateway::class, 'tenant_id');
    }

    public function whatsappInstances(): HasMany
    {
        return $this->hasMany(WhatsappInstance::class, 'tenant_id');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'tenant_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'tenant_id');
    }

    public function productOptionGroups(): HasMany
    {
        return $this->hasMany(ProductOptionGroup::class, 'tenant_id');
    }

    public function productOptions(): HasMany
    {
        return $this->hasMany(ProductOption::class, 'tenant_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'tenant_id');
    }

    public function customerAddresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class, 'tenant_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'tenant_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'tenant_id');
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class, 'tenant_id');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'tenant_id');
    }

    public function cartItemOptions(): HasMany
    {
        return $this->hasMany(CartItemOption::class, 'tenant_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'tenant_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'tenant_id');
    }

    public function orderItemOptions(): HasMany
    {
        return $this->hasMany(OrderItemOption::class, 'tenant_id');
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class, 'tenant_id');
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class, 'tenant_id');
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'tenant_id');
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class, 'tenant_id');
    }

    public function flows(): HasMany
    {
        return $this->hasMany(Flow::class, 'tenant_id');
    }

    public function flowSteps(): HasMany
    {
        return $this->hasMany(FlowStep::class, 'tenant_id');
    }

    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class, 'tenant_id');
    }
}
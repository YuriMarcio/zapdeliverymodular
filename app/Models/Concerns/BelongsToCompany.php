<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Facades\Tenancy;

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::creating(function ($model): void {

            $tenant = tenant();

            if (
                $tenant &&
                (! isset($model->company_id) || $model->company_id === null)
            ) {
                $model->company_id = $tenant->id;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
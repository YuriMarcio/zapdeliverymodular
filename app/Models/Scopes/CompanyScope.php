<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Stancl\Tenancy\Facades\Tenancy;

class CompanyScope implements Scope
{
    public function apply(
        Builder $builder,
        Model $model
    ): void {

        $tenant = tenant();

        if (! $tenant) {
            return;
        }

        $builder->where(
            $model->getTable() . '.company_id',
            $tenant->id
        );
    }
}
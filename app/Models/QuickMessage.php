<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuickMessage extends Model
{
    protected $fillable = ['tenant_id', 'title', 'body', 'sort_order'];
}

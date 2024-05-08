<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompaniesConfiguration extends Model
{
    protected $fillable = ['configuration_key','configuration_value', 'company_id'];

    public function company()
    {
        return $this->belongsTo(Companies::class , 'company_id');
    }
}
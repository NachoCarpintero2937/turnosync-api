<?php

namespace App\Models;

use App\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Companies extends Model
{
    protected $fillable = ['name','address' ,'latitud','longitud','status'];

    
    public function clients()
    {
        return $this->hasMany(Client::class, 'company_id');
    }

    public function configurations()
    {
        return $this->hasMany(CompaniesConfiguration::class, 'company_id');
    }

    use HasFactory;
}
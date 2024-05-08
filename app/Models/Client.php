<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = ['name', 'email', 'cod_area', 'phone', 'date_birthday', 'status','company_id'];

    public static function createClient($data)
    {
        return static::create($data);
    }

    public function updateClient($data)
    {
        try {
            $this->update($data);
            return $this;
        } catch (\Exception $e) {
            throw $e;
        }
    }
    public function softDeleteClient()
    {
        $this->status = 1; // Cambiar el estado del cliente a inactivo
        $this->save();
    }

    public function company()
{
    return $this->belongsTo(Companies::class, 'company_id');
}


    use HasFactory;
}

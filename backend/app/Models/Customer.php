<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = ['name', 'email', 'cpf', 'cpf_hash'];

    protected $hidden = ['cpf_hash'];

    protected function casts(): array
    {
        return ['email' => 'encrypted', 'cpf' => 'encrypted'];
    }
}

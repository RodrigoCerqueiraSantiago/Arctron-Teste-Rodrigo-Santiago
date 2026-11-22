<?php

namespace App\Support;

use LogicException;

class CustomerCpfIndex
{
    public static function hash(string $cpf): string
    {
        $key = config('app.key');
        if (! is_string($key) || $key === '') {
            throw new LogicException('APP_KEY is required for the CPF index.');
        }

        return hash_hmac('sha256', $cpf, hash_hmac('sha256', 'customer-cpf-index', $key, true));
    }
}

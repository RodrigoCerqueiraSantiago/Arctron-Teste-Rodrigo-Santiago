<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateDashboardUser extends Command
{
    protected $signature = 'dashboard:create-user
        {--name= : Nome do usuário}
        {--email= : E-mail do usuário}
        {--role= : Perfil admin ou reader}
        {--password-env= : Nome da variável de ambiente contendo a senha, para automação}';

    protected $description = 'Cria um usuário do dashboard sem exibir sua senha';

    public function handle(): int
    {
        $name = $this->option('name') ?? $this->ask('Nome');
        $email = $this->option('email') ?? $this->ask('E-mail');
        $role = $this->option('role') ?? $this->choice('Perfil', ['admin', 'reader'], 'reader');
        $passwordEnv = $this->option('password-env');
        if ($passwordEnv) {
            $password = getenv($passwordEnv);
            if ($password === false) {
                $this->error('Variável de senha não encontrada.');

                return self::FAILURE;
            }
        } else {
            if (! $this->input->isInteractive()) {
                $this->error('Informe a senha interativamente ou por --password-env.');

                return self::FAILURE;
            }
            $password = $this->secret('Senha (mínimo 12 caracteres)');
            if ($password !== $this->secret('Confirme a senha')) {
                $this->error('As senhas não coincidem.');

                return self::FAILURE;
            }
        }

        if (is_string($password) && (strlen($password) > 72 || str_contains($password, "\0"))) {
            $this->error('A senha deve ter no máximo 72 bytes e não pode conter caracteres nulos.');

            return self::FAILURE;
        }

        $data = Validator::make([
            'name' => $name,
            'email' => is_string($email) ? mb_strtolower($email) : $email,
            'role' => $role,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(['admin', 'reader'])],
            'password' => ['required', 'string', 'min:12', 'max:72'],
        ])->validate();

        $user = new User;
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->role = $data['role'];
        $user->password = $data['password'];
        $user->save();

        $this->info('Usuário criado.');

        return self::SUCCESS;
    }
}

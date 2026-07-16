<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Auth\AdminPassword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create';

    protected $description = 'Crea una cuenta administrativa de VitaNatural';

    public function handle(): int
    {
        $name = trim((string) $this->ask('Nombre del administrador'));
        $email = mb_strtolower(trim((string) $this->ask('Correo electronico')));
        $password = (string) $this->secret('Contrasena (minimo 12 caracteres)');
        $confirmation = (string) $this->secret('Confirma la contrasena');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', AdminPassword::rule()],
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'El correo no tiene un formato valido.',
            'email.unique' => 'Ya existe una cuenta con este correo.',
            'password.required' => 'La contrasena es obligatoria.',
            'password.confirmed' => 'Las contrasenas no coinciden.',
            'password.min' => 'La contrasena debe tener al menos 12 caracteres.',
            'password.mixed' => 'La contrasena debe incluir mayusculas y minusculas.',
            'password.letters' => 'La contrasena debe incluir letras.',
            'password.numbers' => 'La contrasena debe incluir numeros.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $admin = new User([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);
        $admin->forceFill([
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ])->save();

        $this->info("Administrador {$email} creado correctamente.");

        return self::SUCCESS;
    }
}

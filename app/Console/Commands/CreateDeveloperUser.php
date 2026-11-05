<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class CreateDeveloperUser extends Command
{
    protected $signature = 'app:create-developer
        {username? : Username akun Developer}
        {--name=Developer : Nama lengkap}
        {--email= : Email pemulihan}
        {--password= : Kata sandi; jika kosong akan ditanyakan secara tersembunyi}';

    protected $description = 'Membuat atau memperbarui akun Developer tersembunyi';

    public function handle(): int
    {
        $username = strtolower(trim((string) ($this->argument('username') ?: $this->ask('Username Developer'))));
        $password = (string) ($this->option('password') ?: $this->secret('Kata sandi Developer (minimal 12 karakter)'));

        $validator = validator(
            ['username' => $username, 'password' => $password],
            [
                'username' => ['required', 'alpha_dash', 'max:50'],
                'password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()],
            ],
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $developer = User::query()->updateOrCreate(
            ['username' => $username],
            [
                'name' => (string) $this->option('name'),
                'email' => $this->option('email') ?: "{$username}@developer.local",
                'password' => Hash::make($password),
                'role' => UserRole::Developer,
                'is_active' => true,
            ],
        );

        $this->info("Akun Developer {$developer->username} siap digunakan.");

        return self::SUCCESS;
    }
}

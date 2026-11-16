<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Create an administrator with a securely prompted password';

    public function handle(): int
    {
        $data = ['name' => $this->ask('Name'), 'email' => $this->ask('Email'), 'password' => $this->secret('Password (at least 12 characters)', false)];
        $validator = Validator::make($data, ['name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users,email', 'password' => 'required|string|min:12']);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        if (strlen($data['password']) > 72 || str_contains($data['password'], "\0")) {
            $this->error('Use a password of at most 72 bytes with no null characters.');

            return self::FAILURE;
        }

        $data['password'] = Hash::make($data['password']);
        User::create($data + ['role' => 'admin']);
        $this->info('Administrator created.');

        return self::SUCCESS;
    }
}

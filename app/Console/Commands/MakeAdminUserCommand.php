<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MakeAdminUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:admin-user {--email= : Email address of the admin} {--name= : Name of the admin} {--password= : Password for the admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a platform administrator user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->option('email') ?: $this->ask('Enter admin email:');
        $name = $this->option('name') ?: $this->ask('Enter admin name:');
        $password = $this->option('password') ?: $this->secret('Enter admin password:');

        if (empty($email) || empty($name) || empty($password)) {
            $this->error('Email, Name and Password are required.');
            return 1;
        }

        if (\App\Models\Admin::where('email', $email)->exists()) {
            $this->error("Admin with email {$email} already exists.");
            return 1;
        }

        $admin = \App\Models\Admin::create([
            'name' => $name,
            'email' => $email,
            'password' => \Illuminate\Support\Facades\Hash::make($password),
            'status' => 'active',
        ]);

        $this->info("Admin user '{$name}' ({$email}) created successfully!");
        return 0;
    }
}

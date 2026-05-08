<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetUserAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:set-admin {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set a user as admin by email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            $this->error("User with email {$email} not found!");
            return 1;
        }
        
        if ($user->isAdmin()) {
            $this->info("User {$user->name} ({$email}) is already an admin!");
            return 0;
        }
        
        $user->role = 'admin';
        $user->save();
        
        $this->info("User {$user->name} ({$email}) has been set as admin successfully!");
        return 0;
    }
}

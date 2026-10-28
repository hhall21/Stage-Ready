<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MakeAdministrator extends Command
{
    protected $signature = 'stage-ready:make-admin {email : Administrator email address} {--name= : Full name for a new account}';

    protected $description = 'Grant administrator access to a coach, or create an administrator with a password setup link';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $validator = Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        $user = User::where('email', $email)->first();
        if ($user) {
            if (! $user->is_active || $user->role !== 'coach') {
                $this->error('Use an active coach account or a new email address for an administrator.');

                return self::FAILURE;
            }
            $user->forceFill(['is_admin' => true])->save();
            $this->info('Administrator access enabled. Sign in with the existing password at '.rtrim((string) config('app.url'), '/').'/login.');

            return self::SUCCESS;
        }
        $name = trim((string) $this->option('name'));
        $validator = Validator::make(['name' => $name], ['name' => ['required', 'string', 'max:100']]);
        if ($validator->fails()) {
            $this->error('Provide --name="Full name" when creating an administrator account.');

            return self::FAILURE;
        }
        $user = new User(['name' => $name, 'email' => $email, 'password' => Str::random(64)]);
        $user->forceFill(['role' => 'coach', 'is_admin' => true, 'is_active' => true])->save();
        $token = Password::broker()->createToken($user);
        $url = rtrim((string) config('app.url'), '/').'/reset-password/'.$token.'?email='.rawurlencode($email);
        $this->info('Administrator created. Open this private link to choose a password (expires in '.config('auth.passwords.users.expire').' minutes):');
        $this->line($url);
        $this->info('No email was sent. Keep the link private. The account also has a coach workspace.');

        return self::SUCCESS;
    }
}

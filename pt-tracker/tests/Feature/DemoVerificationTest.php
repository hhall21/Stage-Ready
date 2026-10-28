<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDO;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DemoVerificationTest extends TestCase
{
    public function test_verification_accepts_public_fixtures_and_rejects_runtime_data_without_disclosing_values(): void
    {
        $root = storage_path('framework/testing/demo-verification-'.Str::uuid());
        File::ensureDirectoryExists($root);
        $path = $root.'/demo.sqlite';
        File::put($path, '');
        $database = null;
        try {
            $environment = ['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
                'APP_CONFIG_CACHE' => $root.'/config.php', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $path, 'DB_URL' => '',
                'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'BCRYPT_ROUNDS' => '4'];
            $migrate = new Process([PHP_BINARY, 'artisan', 'migrate', '--force', '--no-interaction'], base_path(), $environment);
            $migrate->setTimeout(120)->mustRun();
            $seed = new Process([PHP_BINARY, 'artisan', 'db:seed', '--class=PtTrackerSeeder', '--force', '--no-interaction'], base_path(), $environment);
            $seed->setTimeout(120)->mustRun();
            $verify = new Process([PHP_BINARY, 'database/verify-demo.php', $path], base_path(), $environment);
            $verify->mustRun();
            $this->assertStringContainsString('Demo database verified:', $verify->getOutput());

            $database = new PDO('sqlite:'.$path);
            $statement = $database->prepare('UPDATE users SET email = ? WHERE id = 1');
            $statement->execute(['private.runtime@stage-ready.invalid']);
            $verify->run();
            $this->assertFalse($verify->isSuccessful());
            $this->assertStringContainsString('Non-sample value found in users.email', $verify->getErrorOutput());
            $this->assertStringNotContainsString('private.runtime@stage-ready.invalid', $verify->getOutput().$verify->getErrorOutput());
        } finally {
            $statement = null;
            $database = null;
            $resolved = realpath($root);
            $parent = realpath(storage_path('framework/testing'));
            if ($resolved !== false && $parent !== false && str_starts_with($resolved, $parent.DIRECTORY_SEPARATOR)) {
                File::deleteDirectory($resolved);
            }
        }
    }
}

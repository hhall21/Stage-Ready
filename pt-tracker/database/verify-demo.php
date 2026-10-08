<?php

// Read-only checks for the shared development database; no app configuration needed.
$database = new PDO('sqlite:file:'.__DIR__.'/demo.sqlite?mode=ro');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($database->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') {
    throw new RuntimeException('SQLite integrity check failed.');
}
if ($database->query('PRAGMA foreign_key_check')->fetchAll() !== []) {
    throw new RuntimeException('Broken database relationships.');
}
$migrations = count(glob(__DIR__.'/migrations/*.php'));
if ((int) $database->query('SELECT COUNT(*) FROM migrations')->fetchColumn() !== $migrations) {
    throw new RuntimeException('Demo database needs updated migrations.');
}
$samples = json_decode(file_get_contents(__DIR__.'/seeders/sample-data.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($samples as $table => $rows) {
    if ((int) $database->query('SELECT COUNT(*) FROM "'.$table.'"')->fetchColumn() !== count($rows)) {
        throw new RuntimeException('Unexpected sample count for '.$table);
    }
}
foreach (['sessions', 'contact_enquiries', 'password_reset_tokens', 'jobs', 'failed_jobs', 'cache'] as $table) {
    if ((int) $database->query('SELECT COUNT(*) FROM "'.$table.'"')->fetchColumn() !== 0) {
        throw new RuntimeException('Runtime data found in '.$table);
    }
}
foreach ($database->query('SELECT password FROM users') as $user) {
    if (! password_verify('password', $user['password'])) {
        throw new RuntimeException('Sample login password is invalid.');
    }
}
if ($database->query('SELECT workout_id FROM workout_exercises GROUP BY workout_id HAVING COUNT(*) > 10')->fetchColumn() !== false) {
    throw new RuntimeException('A sample workout exceeds ten exercises.');
}
echo "Demo database verified: integrity, relationships, migrations, sample records, passwords and exercise limits.\n";

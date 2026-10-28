<?php

// Read-only checks for an explicitly supplied generated demo database.
$path = $argv[1] ?? null;
if ($path === null || ! is_file($path)) {
    throw new RuntimeException('Usage: php database/verify-demo.php /path/to/generated-demo.sqlite');
}
$database = new PDO('sqlite:file:'.realpath($path).'?mode=ro');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$query = static function (string $sql) use ($database): PDOStatement {
    $statement = $database->query($sql);
    if ($statement === false) {
        throw new RuntimeException('Demo verification query failed.');
    }

    return $statement;
};

if ($query('PRAGMA integrity_check')->fetchColumn() !== 'ok') {
    throw new RuntimeException('SQLite integrity check failed.');
}
if ($query('PRAGMA foreign_key_check')->fetchAll() !== []) {
    throw new RuntimeException('Broken database relationships.');
}
$migrationFiles = glob(__DIR__.'/migrations/*.php');
if ($migrationFiles === false) {
    throw new RuntimeException('Cannot read demo database migrations.');
}
$migrations = count($migrationFiles);
if ((int) $query('SELECT COUNT(*) FROM migrations')->fetchColumn() !== $migrations) {
    throw new RuntimeException('Demo database needs updated migrations.');
}
$sampleData = file_get_contents(__DIR__.'/seeders/sample-data.json');
if ($sampleData === false) {
    throw new RuntimeException('Cannot read demo sample data.');
}
$samples = json_decode($sampleData, true, 512, JSON_THROW_ON_ERROR);
$tableNames = $query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
$matchesValue = static function (mixed $actual, mixed $expected): bool {
    return match (true) {
        $expected === null => $actual === null,
        is_bool($expected) => $actual !== null && (int) $actual === (int) $expected,
        is_int($expected), is_float($expected) => $actual !== null && is_numeric($actual) && (float) $actual === (float) $expected,
        default => $actual === $expected,
    };
};
foreach ($samples as $table => $rows) {
    if (! preg_match('/^[a-z_][a-z0-9_]*$/D', $table) || ! in_array($table, $tableNames, true)) {
        throw new RuntimeException('Invalid sample table name.');
    }
    $records = $query('SELECT * FROM "'.$table.'" ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    if (count($records) !== count($rows)) {
        throw new RuntimeException('Unexpected sample count for '.$table);
    }
    $columns = $query('PRAGMA table_info("'.$table.'")')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $index => $row) {
        // New nullable/defaulted columns must not conceal private runtime values.
        foreach ($columns as $column) {
            $name = $column['name'];
            if (array_key_exists($name, $row)) {
                continue;
            }
            if ($table === 'users' && $name === 'email_verified_at') {
                $row[$name] = '__NOW__';
            } elseif ($table === 'users' && $name === 'billing_exempt') {
                // Fictional demo accounts deliberately bypass coach billing.
                $row[$name] = true;
            } elseif ($name === 'id') {
                $row[$name] = $index + 1;
            } else {
                $default = $column['dflt_value'] === null ? null : trim($column['dflt_value'], "'");
                $row[$name] = $default === null || strtoupper($default) === 'NULL' ? null
                    : (is_numeric($default) ? (float) $default : trim($default, "'"));
            }
        }
        foreach ($row as $column => $expected) {
            $actual = $records[$index][$column];
            if ($expected === '__NOW__') {
                if (! is_string($actual) || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $actual)) {
                    throw new RuntimeException('Unexpected sample timestamp.');
                }

                continue;
            }
            if ($table === 'users' && $column === 'password') {
                continue;
            }
            if (! $matchesValue($actual, $expected)) {
                // Never print record values: this failure may indicate private data.
                throw new RuntimeException('Non-sample value found in '.$table.'.'.$column);
            }
        }
    }
}
foreach (array_diff($tableNames, array_keys($samples), ['migrations']) as $table) {
    if (! preg_match('/^[a-z_][a-z0-9_]*$/D', $table)) {
        throw new RuntimeException('Unexpected database table name.');
    }
    if ((int) $query('SELECT COUNT(*) FROM "'.$table.'"')->fetchColumn() !== 0) {
        throw new RuntimeException('Runtime data found in '.$table);
    }
}
foreach ($query('SELECT password FROM users') as $user) {
    if (! password_verify('password', $user['password'])) {
        throw new RuntimeException('Sample login password is invalid.');
    }
}
if ($query('SELECT workout_id FROM workout_exercises GROUP BY workout_id HAVING COUNT(*) > 10')->fetchColumn() !== false) {
    throw new RuntimeException('A sample workout exceeds ten exercises.');
}
echo "Demo database verified: integrity, relationships, migrations, sample records, passwords and exercise limits.\n";

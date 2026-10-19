<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class PtTrackerSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo accounts can only be seeded in local and testing environments.');
        }

        $sampleData = json_decode(File::get(__DIR__.'/sample-data.json'), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($sampleData)) {
            throw new RuntimeException('Sample data must contain tables keyed by name.');
        }
        $tables = [];
        foreach ($sampleData as $table => $rows) {
            if (! is_string($table) || ! is_array($rows)) {
                throw new RuntimeException('Sample data must contain tables keyed by name.');
            }
            $tables[$table] = $rows;
        }

        // Never seed over records that belong to an existing installation.
        foreach (array_keys($tables) as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException('Sample data requires empty tables. Use migrate:fresh --seed only when existing data can be deleted.');
            }
        }

        $password = Hash::make('password');
        $timestamp = now()->toDateTimeString();

        DB::transaction(function () use ($tables, $password, $timestamp): void {
            foreach ($tables as $table => $rows) {
                foreach ($rows as $index => &$row) {
                    // Empty tables may still have advanced auto-increment counters.
                    // Explicit fixture IDs keep their related records consistent.
                    $row['id'] = $index + 1;
                    foreach ($row as &$value) {
                        if ($value === '__NOW__') {
                            $value = $timestamp;
                        }
                    }
                    unset($value);

                    if ($table === 'users') {
                        $row['password'] = $password;
                        $row['email_verified_at'] = $timestamp;
                    }
                }
                unset($row);

                DB::table($table)->insert($rows);
            }
        });
    }
}

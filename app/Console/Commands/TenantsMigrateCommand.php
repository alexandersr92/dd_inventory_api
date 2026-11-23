<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class TenantsMigrateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:migrate {--fresh : Drop all tables and re-run all migrations} {--seed : Seed the databases}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run migrations for the central/shared database and all dedicated tenant databases';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $migrateCmd = $this->option('fresh') ? 'migrate:fresh' : 'migrate';

        // 1. Run migrations on central/shared database explicitly
        $this->info('Running migrations on central/shared database...');
        
        $centralOptions = [
            '--database' => 'central',
            '--force' => true,
        ];

        $exitCode = $this->call($migrateCmd, $centralOptions);
        if ($exitCode !== Command::SUCCESS) {
            $this->error('Failed to migrate central database.');
            return $exitCode;
        }

        $centralDb = config('database.connections.central.database');
        $sharedDb = config('database.connections.mysql.database');

        if ($centralDb !== $sharedDb) {
            $sharedOptions = [
                '--database' => 'mysql',
                '--force' => true,
            ];

            $exitCode = $this->call($migrateCmd, $sharedOptions);
            if ($exitCode !== Command::SUCCESS) {
                $this->error('Failed to migrate shared database.');
                return $exitCode;
            }
        }

        if ($this->option('seed')) {
            $exitCode = $this->call('db:seed', [
                '--force' => true,
            ]);
            if ($exitCode !== Command::SUCCESS) {
                $this->error('Failed to seed central/shared database.');
                return $exitCode;
            }
        }

        // 2. Run migrations on all dedicated databases
        // Since Organization is a global model, it queries the central database
        $organizations = Organization::where('tenancy_type', 'dedicated')->get();

        if ($organizations->isEmpty()) {
            $this->info('No dedicated tenants found.');
            return Command::SUCCESS;
        }

        $this->info('Running migrations for dedicated tenants...');

        $originalDb = config('database.connections.mysql.database');

        foreach ($organizations as $org) {
            $dbName = $org->db_database;

            if (!$dbName) {
                $this->warn("Organization {$org->name} ({$org->id}) does not have a database name configured. Skipping.");
                continue;
            }

            $this->info("Creating/updating database {$dbName} for organization: {$org->name}...");

            try {
                // Ensure the dedicated database exists
                DB::connection('central')->statement("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

                // Switch connection configuration temporarily
                config(['database.connections.mysql.database' => $dbName]);
                DB::purge('mysql');

                $tenantOptions = [
                    '--database' => 'mysql',
                    '--force' => true,
                ];

                $exitCode = $this->call($migrateCmd, $tenantOptions);
                if ($exitCode !== Command::SUCCESS) {
                    $this->error("Failed to migrate database {$dbName}.");
                    return $exitCode;
                }

                if ($this->option('seed')) {
                    $exitCode = $this->call('db:seed', [
                        '--database' => 'mysql',
                        '--force' => true,
                    ]);
                    if ($exitCode !== Command::SUCCESS) {
                        $this->error("Failed to seed database {$dbName}.");
                        return $exitCode;
                    }
                }

                $this->info("Database {$dbName} migrated successfully.");
            } catch (\Throwable $e) {
                $this->error("Failed to migrate database {$dbName}: " . $e->getMessage());
                return Command::FAILURE;
            } finally {
                // Restore database connection
                config(['database.connections.mysql.database' => $originalDb]);
                DB::purge('mysql');
            }
        }

        $this->info('All tenant migrations completed.');
        return Command::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DatabaseBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup {--compress : Comprimir el archivo resultante usando gzip}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea una copia de seguridad (dump) de la base de datos mysql central.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando la copia de seguridad de la base de datos...');

        $connection = config('database.default');
        if ($connection !== 'mysql') {
            $this->error('Este comando solo es compatible con conexiones de tipo MySQL.');
            return 1;
        }

        $config = config("database.connections.{$connection}");
        
        $filename = 'backup-' . now()->format('Y-m-d-H-i-s') . '.sql';
        $directory = storage_path('app/backups');

        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $filePath = $directory . '/' . $filename;

        // Build password argument securely (no space between -p and the password)
        $passwordOption = '';
        if (!empty($config['password'])) {
            $passwordOption = '-p' . $config['password'];
        }

        // Find mysqldump executable path dynamically (helps when running from PHP-FPM web server environment)
        $mysqldump = 'mysqldump';
        $candidates = [
            '/usr/local/mysql/bin/mysqldump',
            '/opt/homebrew/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            '/usr/bin/mysqldump',
        ];
        foreach ($candidates as $candidate) {
            if (file_exists($candidate) && is_executable($candidate)) {
                $mysqldump = $candidate;
                break;
            }
        }
        if ($mysqldump === 'mysqldump') {
            $globDirs = glob('/usr/local/mysql-*/bin/mysqldump');
            if (!empty($globDirs)) {
                $mysqldump = $globDirs[0];
            }
        }

        $command = sprintf(
            '%s --no-tablespaces --host=%s --port=%s --user=%s %s %s > %s 2>&1',
            escapeshellarg($mysqldump),
            escapeshellarg($config['host']),
            escapeshellarg($config['port']),
            escapeshellarg($config['username']),
            $passwordOption,
            escapeshellarg($config['database']),
            escapeshellarg($filePath)
        );

        // Run the command
        exec($command, $output, $resultCode);

        if ($resultCode === 0 && file_exists($filePath) && filesize($filePath) > 0) {
            $this->info("Copia de seguridad creada correctamente: {$filename}");
            
            // Check if compression is requested
            if ($this->option('compress')) {
                $this->info('Comprimiendo archivo con gzip...');
                exec("gzip " . escapeshellarg($filePath));
                $filename .= '.gz';
                $filePath .= '.gz';
                $this->info("Archivo comprimido creado: {$filename}");
            }

            Log::info("Copia de seguridad de BD central generada con éxito: {$filename}");
            return 0;
        }

        // Output error log if failed
        $this->error('Ocurrió un error al intentar generar la copia de seguridad.');
        $errorDetails = implode("\n", $output);
        $this->error($errorDetails);
        Log::error("Fallo al generar copia de seguridad de BD central. Código: {$resultCode}. Detalle: {$errorDetails}");
        
        // Clean up empty file if created
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        return 1;
    }
}

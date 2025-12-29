<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'db:backup-external {db? : Database name (optional)}';

    protected $description = 'Backup MySQL database to external USB / HDD (plain .sql file)';

    public function handle()
    {
        /* -------------------------
           1. DATABASE CONFIG
        ------------------------- */
        $dbName = $this->argument('db') ?? config('database.connections.mysql.database');
        $dbHost = config('database.connections.mysql.host');
        $dbPort = config('database.connections.mysql.port') ?: '3306';
        $dbUser = config('database.connections.mysql.username');
        $dbPass = config('database.connections.mysql.password');

        $externalPath = rtrim(env('EXTERNAL_DRIVE_PATH'), '/\\');

        if (!is_dir($externalPath)) {
            $this->error("External directory does not exist: {$externalPath}");
            return Command::FAILURE;
        }

        if (!is_writable($externalPath)) {
            $this->error("External directory is not writable: {$externalPath}");
            return Command::FAILURE;
        }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename  = "{$dbName}_backup_{$timestamp}.sql";
        $fullPath  = "{$externalPath}\\{$filename}";

        $this->info("Starting backup of database: {$dbName}");
        $this->info("Saving to: {$fullPath}");

        /* -------------------------
           2. BUILD mysqldump COMMAND
        ------------------------- */
        $dumpCommand = [
            'mysqldump',
            '--host=' . $dbHost,
            '--port=' . $dbPort,
            '--user=' . $dbUser,
        ];

        if (!empty($dbPass)) {
            $dumpCommand[] = '--password=' . $dbPass;
        }

        // Recommended options for reliable backups (especially InnoDB)
        $dumpCommand[] = '--single-transaction';
        $dumpCommand[] = '--quick';
        $dumpCommand[] = '--lock-tables=false';

        $dumpCommand[] = $dbName;

        // Use redirection to handle paths with spaces safely on Windows
        $commandLine = implode(' ', array_map('escapeshellarg', $dumpCommand)) .
                       ' > ' . escapeshellarg($fullPath);

        $process = Process::fromShellCommandline($commandLine);
        $process->setTimeout(null);
        $process->run();

        // Show warnings (e.g., "Using a password on the command line interface can be insecure.")
        if ($process->getErrorOutput()) {
            $this->warn('mysqldump message: ' . trim($process->getErrorOutput()));
        }

        if (!$process->isSuccessful()) {
            $this->error('Backup failed!');
            if (file_exists($fullPath)) {
                unlink($fullPath); // Clean up partial file
            }
            return Command::FAILURE;
        }

        if (!file_exists($fullPath) || filesize($fullPath) === 0) {
            $this->error('Backup file was not created or is empty.');
            return Command::FAILURE;
        }

        $fileSizeMB = round(filesize($fullPath) / 1024 / 1024, 2);

        $this->info("Backup completed successfully ✔");
        $this->info("File: {$fullPath}");
        $this->info("Size: {$fileSizeMB} MB");

        return Command::SUCCESS;
    }
}
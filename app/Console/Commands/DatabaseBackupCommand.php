<?php

namespace App\Console\Commands;


use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Storage;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'db:backup-external 
    {path : External drive path}
    {db? : Database name (optional)}';


    protected $description = 'Backup MySQL database to external USB / HDD';

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

        $externalPath = rtrim($this->argument('path'), '/');

        if (!is_dir($externalPath) || !is_writable($externalPath)) {
            $this->error("External path not writable: {$externalPath}");
            return Command::FAILURE;
        }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename  = "{$dbName}_backup_{$timestamp}.sql";
        $fullPath  = "{$externalPath}/{$filename}";

        $this->info("Starting backup: {$dbName}");
        $this->info("Target: {$fullPath}");

        /* -------------------------
           2. DUMP + COMPRESS
        ------------------------- */
        $process = new Process([
            'bash',
            '-c',
            sprintf(
                'mysqldump -h%s -P%s -u%s -p%s %s | gzip > %s',
                escapeshellarg($dbHost),
                escapeshellarg($dbPort),
                escapeshellarg($dbUser),
                escapeshellarg($dbPass),
                escapeshellarg($dbName),
                escapeshellarg($fullPath)
            )
        ]);

        $process->setTimeout(null);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->error('Backup failed: ' . $process->getErrorOutput());
            return Command::FAILURE;
        }

        $this->info("Backup saved successfully ✔");
        $this->info("File size: " . round(filesize($fullPath) / 1024 / 1024, 2) . " MB");

        return Command::SUCCESS;
    }
}
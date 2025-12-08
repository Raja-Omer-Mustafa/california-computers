<?php

namespace App\Console\Commands;


use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Storage;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'db:backup {db? : Database name (optional)}';
    protected $description = 'Backup MySQL database and upload to Google Drive';

    public function handle()
    {
        // 1️⃣ Get DB config
        $dbName = $this->argument('db') ?? config('database.connections.mysql.database');
        $dbHost = config('database.connections.mysql.host');
        $dbPort = config('database.connections.mysql.port') ?: '3306';
        $dbUser = config('database.connections.mysql.username');
        $dbPass = config('database.connections.mysql.password');

        $this->info("Starting backup of database: {$dbName}");

        // 2️⃣ Prepare backup directory
        $backupDir = storage_path('app/backups');
        // if (!is_dir($backupDir)) {
        //     mkdir($backupDir, 0755, true);
        // }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "{$dbName}_backup_{$timestamp}.sql";
        $filepath = "{$backupDir}/{$filename}";

        // 3️⃣ Run mysqldump
        $process = new Process([
            'mysqldump',
            '--host=' . $dbHost,
            '--port=' . $dbPort,
            '--user=' . $dbUser,
            '--password=' . $dbPass,
            $dbName
        ]);
        $process->setTimeout(null);
        $process->run(function ($type, $buffer) {
            if ($type === 'err') {
                $this->error(trim($buffer));
            }
        });

        if (!$process->isSuccessful()) {
            $this->error('mysqldump failed: ' . $process->getErrorOutput());
            return Command::FAILURE;
        }

        file_put_contents($filepath, $process->getOutput());
        $this->info("Database dumped to: {$filename}");

        // 4️⃣ Compress backup
        $gzFilepath = $filepath . '.gz';
        $gzProcess = new Process(['gzip', $filepath]);
        $gzProcess->run();

        if (!$gzProcess->isSuccessful()) {
            $this->error('Compression failed');
            return Command::FAILURE;
        }

        $finalFile = $gzFilepath;
        $uploadName = basename($finalFile);
        $this->info("Compressed backup: {$uploadName}");

        // 5️⃣ Upload to Dropbox using stream
        try {
            if (!file_exists($finalFile)) {
                $this->error('Backup file not found: ' . $finalFile);
                return Command::FAILURE;
            }

            $this->info('File size: ' . filesize($finalFile) . ' bytes');

            $stream = fopen($finalFile, 'r');
            $dropboxPath = 'database-backups/' . $uploadName;

            // Upload
            $result = Storage::disk('dropbox')->put($dropboxPath, $stream);

            $this->info('Dropbox upload result: ' . ($result ? 'success' : 'failed'));

            if (!$result) {
                $this->error('Dropbox upload failed. Check your token and folder path.');
                return Command::FAILURE;
            }

            $this->info("Uploaded to Dropbox folder 'database-backups' as: {$uploadName}");
        } catch (\Exception $e) {
            $this->error('Dropbox upload failed: ' . $e->getMessage());
            $this->error('Trace: ' . $e->getTraceAsString());
            return Command::FAILURE;
        }

        // 6️⃣ Cleanup local files
        @unlink($filepath);   // original SQL
        @unlink($gzFilepath); // compressed file

        $this->info('Backup completed and local files cleaned up.');

        return Command::SUCCESS;
    }
}

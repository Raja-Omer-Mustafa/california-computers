<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class BackupDatabaseToDropbox extends Command
{
    /**
     * php artisan db:backup-dropbox
     */
    protected $signature = 'db:backup-dropbox';

    protected $description = 'Export the database, upload the dump to Dropbox, then delete the local copy';

    public function handle(): int
    {
        $connection = config('database.default');
        $db         = config("database.connections.{$connection}");

        $filename   = sprintf(
            '%s_backup_%s.sql',
            str_replace(' ', '_', strtolower(config('app.name'))),
            now()->format('Y-m-d_H-i-s')
        );

        $localDir  = storage_path('app/database-backups');
        $localPath = $localDir . DIRECTORY_SEPARATOR . $filename;
        $remotePath = $filename;

        if (! File::isDirectory($localDir)) {
            File::makeDirectory($localDir, 0755, true);
        }

        // 1. Dump the database
        $this->info('Dumping database...');

        $dumpSuccess = $this->dumpDatabase($db, $localPath);

        if (! $dumpSuccess || ! file_exists($localPath) || filesize($localPath) === 0) {
            $this->error('Database dump failed or produced an empty file.');
            return self::FAILURE;
        }

        // 2. Upload to Dropbox
        $this->info("Uploading {$filename} to Dropbox...");

        try {
            $stream = fopen($localPath, 'r');

            Storage::disk('dropbox')->put($remotePath, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            $this->info('Upload successful!');
        } catch (\Exception $e) {
            $this->error('Upload failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        // 3. Delete the local copy
        if (File::exists($localPath)) {
            File::delete($localPath);
            $this->info('Local backup file deleted.');
        }

        $this->info('Database backup complete.');

        return self::SUCCESS;
    }

    /**
     * Run mysqldump and write output to the given path.
     */
    protected function dumpDatabase(array $db, string $outputPath): bool
    {
        $command = [
            'mysqldump',
            '--host=' . ($db['host'] ?? '127.0.0.1'),
            '--port=' . ($db['port'] ?? 3306),
            '--user=' . $db['username'],
            '--single-transaction',
            '--skip-lock-tables',
            $db['database'],
        ];

        $process = new Process($command);
        $process->setEnv(['MYSQL_PWD' => $db['password'] ?? '']);
        $process->setTimeout(3600);

        // Write mysqldump's stdout directly to the backup file
        $handle = fopen($outputPath, 'w');

        $process->run(function ($type, $buffer) use ($handle) {
            if ($type === Process::OUT) {
                fwrite($handle, $buffer);
            }
        });

        fclose($handle);

        if (! $process->isSuccessful()) {
            $this->error($process->getErrorOutput());
            return false;
        }

        return true;
    }
}
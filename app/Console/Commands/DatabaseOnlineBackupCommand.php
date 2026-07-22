<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Exception as GoogleServiceException;
use Google\Http\MediaFileUpload;

class DatabaseOnlineBackupCommand extends Command
{
    protected $signature = 'db:backup-online {db? : Database name (optional)}';

    protected $description = 'Backup MySQL database to Google Drive (plain .sql file)';

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

        $storagePath = storage_path('app/database-backups');
        File::ensureDirectoryExists($storagePath);

        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename  = "{$dbName}_backup_{$timestamp}.sql";
        $fullPath  = "{$storagePath}/{$filename}";

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

        $credentialsPath = storage_path('app/google-drive-credentials.json');

        if (!file_exists($credentialsPath)) {
            $this->error('Google Drive credentials file not found at storage/app/google-drive-credentials.json');
            return Command::FAILURE;
        }

        $credentials = json_decode(file_get_contents($credentialsPath), true);

        if (!is_array($credentials) || ($credentials['type'] ?? null) === 'service_account') {
            $this->error('Service accounts cannot upload to personal Google Drive.');
            $this->error('Use an OAuth 2.0 Client ID JSON in storage/app/google-drive-credentials.json, then run: php artisan db:backup-online-auth');
            return Command::FAILURE;
        }

        $refreshToken = env('GOOGLE_DRIVE_REFRESH_TOKEN');

        if (empty($refreshToken)) {
            $this->error('GOOGLE_DRIVE_REFRESH_TOKEN is not set in .env');
            $this->error('Run: php artisan db:backup-online-auth');
            return Command::FAILURE;
        }

        $client = new Client();
        $client->setAuthConfig($credentialsPath);
        $client->addScope(Drive::DRIVE);
        $client->setAccessType('offline');

        $accessToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);

        if (isset($accessToken['error'])) {
            $this->error('Google Drive authentication failed: ' . ($accessToken['error_description'] ?? $accessToken['error']));
            $this->error('Run: php artisan db:backup-online-auth');
            return Command::FAILURE;
        }

        $client->setDefer(true);

        $driveService = new Drive($client);

        $fileMetadata = new DriveFile([
            'name' => $filename,
        ]);

        try {
            $request = $driveService->files->create($fileMetadata, [
                'fields' => 'id',
            ]);

            $chunkSizeBytes = 5 * 1024 * 1024;
            $media = new MediaFileUpload(
                $client,
                $request,
                'application/octet-stream',
                null,
                true,
                $chunkSizeBytes
            );
            $media->setFileSize(filesize($fullPath));

            $uploadStatus = false;
            $fileHandle = fopen($fullPath, 'rb');

            while (!$uploadStatus && !feof($fileHandle)) {
                $chunk = fread($fileHandle, $chunkSizeBytes);
                $uploadStatus = $media->nextChunk($chunk);
            }
        } catch (GoogleServiceException $exception) {
            $this->error('Google Drive upload failed: ' . $exception->getMessage());
            return Command::FAILURE;
        } finally {
            if (isset($fileHandle) && is_resource($fileHandle)) {
                fclose($fileHandle);
            }
            $client->setDefer(false);
        }

        if (!$uploadStatus) {
            $this->error('Google Drive upload failed.');
            return Command::FAILURE;
        }

        $this->info('Backup uploaded to Google Drive successfully ✔');
        $this->info("File ID: {$uploadStatus->id}");

        unlink($fullPath);

        return Command::SUCCESS;
    }
}
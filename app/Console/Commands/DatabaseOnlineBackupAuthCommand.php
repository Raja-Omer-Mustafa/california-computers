<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Google\Client;
use Google\Service\Drive;

class DatabaseOnlineBackupAuthCommand extends Command
{
    protected $signature = 'db:backup-online-auth';

    protected $description = 'Authorize Google Drive access and print a refresh token for db:backup-online';

    public function handle()
    {
        $credentialsPath = storage_path('app/google-drive-credentials.json');

        if (!file_exists($credentialsPath)) {
            $this->error('Google Drive credentials file not found at storage/app/google-drive-credentials.json');
            return Command::FAILURE;
        }

        $credentials = json_decode(file_get_contents($credentialsPath), true);

        if (!is_array($credentials) || ($credentials['type'] ?? null) === 'service_account') {
            $this->error('OAuth client credentials are required.');
            $this->error('Create an OAuth 2.0 Client ID in Google Cloud Console and save the JSON to storage/app/google-drive-credentials.json');
            return Command::FAILURE;
        }

        $client = new Client();
        $client->setAuthConfig($credentialsPath);
        $client->setRedirectUri($this->resolveRedirectUri($credentials));
        $client->addScope(Drive::DRIVE);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        $this->info('Using redirect URI: ' . $client->getRedirectUri());
        $this->warn('Add this exact redirect URI under Authorized redirect URIs in Google Cloud Console.');
        $this->newLine();
        $this->info('Open this URL in your browser and authorize access:');
        $this->line($client->createAuthUrl());
        $this->newLine();

        $authCode = $this->ask('Enter the authorization code');

        if (empty($authCode)) {
            $this->error('Authorization code is required.');
            return Command::FAILURE;
        }

        $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

        if (isset($accessToken['error'])) {
            $this->error('Authorization failed: ' . ($accessToken['error_description'] ?? $accessToken['error']));
            return Command::FAILURE;
        }

        if (empty($accessToken['refresh_token'])) {
            $this->error('No refresh token was returned. Revoke app access in your Google account and run this command again.');
            return Command::FAILURE;
        }

        $this->info('Authorization successful.');
        $this->newLine();
        $this->line('Add this to your .env file:');
        $this->line('GOOGLE_DRIVE_REFRESH_TOKEN=' . $accessToken['refresh_token']);

        return Command::SUCCESS;
    }

    private function resolveRedirectUri(array $credentials): string
    {
        $redirectUri = env('GOOGLE_DRIVE_REDIRECT_URI');

        if (!empty($redirectUri)) {
            return $redirectUri;
        }

        foreach (['web', 'installed'] as $clientType) {
            $configuredRedirectUri = $credentials[$clientType]['redirect_uris'][0] ?? null;

            if (!empty($configuredRedirectUri)) {
                return $configuredRedirectUri;
            }
        }

        return 'http://localhost';
    }
}

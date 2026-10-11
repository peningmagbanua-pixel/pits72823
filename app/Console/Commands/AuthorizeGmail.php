<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AuthorizeGmail extends Command
{
    protected $signature = 'gmail:authorize';

    protected $description = 'Print the Google OAuth consent URL needed to obtain a Gmail API refresh token';

    public function handle(): int
    {
        $state = Str::random(40);
        Cache::put('gmail_auth.state', $state, now()->addMinutes(15));

        $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => config('services.gmail_api.client_id'),
            'redirect_uri' => config('services.gmail_api.redirect'),
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'scope' => 'https://www.googleapis.com/auth/gmail.send',
            'state' => $state,
        ]);

        $this->info('Open this URL in a browser and approve access:');
        $this->newLine();
        $this->line($url);
        $this->newLine();
        $this->comment('The refresh token will be shown on the callback page and saved to storage/app/gmail-oauth-token.txt for 15 minutes.');

        return self::SUCCESS;
    }
}

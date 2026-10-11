<?php

namespace App\Mail\Transports;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Message;

/**
 * Sends mail through the Gmail REST API over HTTPS.
 *
 * Used because Railway blocks outbound SMTP ports (25/465/587),
 * while HTTPS (443) is allowed. Requires a Google Cloud project
 * with the Gmail API enabled plus an OAuth refresh token with the
 * gmail.send scope (see the gmail:authorize artisan command).
 */
class GmailApiTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $refreshToken,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();

        if (! $original instanceof Message) {
            throw new TransportException('Gmail API transport only supports Symfony Email messages.');
        }

        if ($original instanceof \Symfony\Component\Mime\Email) {
            $rfc822 = $original->toString();
        } else {
            $rfc822 = $original->getHeaders()->toString() . "\r\n\r\n" . (string) $original->getBody();
        }

        $raw = rtrim(strtr(base64_encode($rfc822), '+/', '-_'), '=');

        $response = Http::withToken($this->accessToken())
            ->timeout(30)
            ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
                'raw' => $raw,
            ]);

        if ($response->failed()) {
            throw new TransportException(
                'Gmail API send failed [' . $response->status() . ']: ' . $response->body()
            );
        }
    }

    private function accessToken(): string
    {
        return cache()->remember('gmail_api.access_token', 3000, function () {
            $response = Http::asForm()
                ->timeout(30)
                ->post('https://oauth2.googleapis.com/token', [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $this->refreshToken,
                    'grant_type' => 'refresh_token',
                ]);

            if ($response->failed()) {
                throw new TransportException(
                    'Gmail API token refresh failed [' . $response->status() . ']: ' . $response->body()
                );
            }

            $token = $response->json('access_token');

            if (! $token) {
                throw new TransportException('Gmail API token refresh returned no access token.');
            }

            return $token;
        });
    }

    public function __toString(): string
    {
        return 'gmail-api';
    }
}

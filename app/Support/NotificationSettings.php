<?php

namespace App\Support;

class NotificationSettings
{
    public const EVENT_SUBMITTED = 1;
    public const EVENT_APPROVED = 2;
    public const EVENT_REJECTED = 3;
    public const EVENT_RESET = 4;
    public const EVENT_EXAM_SCHEDULED = 5;
    public const EVENT_ACCOUNT_CREATED = 6;

    /**
     * Whether an outgoing email for the given notification event may be
     * sent: the global "Auto Send Email" switch must be on AND the event's
     * own email flag (Admin Settings > Notifications) must be on. Both
     * default to enabled when the settings file has not stored them yet.
     */
    public static function shouldSend(int $eventId): bool
    {
        $settings = self::load();

        if (! ($settings['app_settings']['auto_send_email'] ?? true)) {
            return false;
        }

        $notification = collect($settings['notification_settings'] ?? [])
            ->firstWhere('id', $eventId);

        if ($notification === null) {
            return true;
        }

        return (bool) ($notification['email'] ?? true);
    }

    private static function load(): array
    {
        $path = storage_path('app/landing_settings.json');

        if (! file_exists($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true) ?? [];
    }
}

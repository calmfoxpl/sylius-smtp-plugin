<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusSmtpPlugin\Support;

use Calmfox\SyliusSmtpPlugin\Core\Settings\AuthMethod;
use Calmfox\SyliusSmtpPlugin\Core\Settings\Encryption;
use Calmfox\SyliusSmtpPlugin\Core\Settings\MailSettings;

/** Settings for a test, with the boring fields filled in. */
final class Settings
{
    public static function make(
        string $encryption = Encryption::TLS,
        string $authMethod = AuthMethod::AUTO,
        string $username = 'shop@example.com',
        string $password = 'correct horse',
        string $providerId = 'custom',
        int $port = 587,
        string $host = 'mail.example.com',
    ): MailSettings {
        return new MailSettings(
            enabled: true,
            providerId: $providerId,
            host: $host,
            port: $port,
            encryption: $encryption,
            authMethod: $authMethod,
            username: $username,
            password: $password,
        );
    }
}

<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Settings;

use Calmfox\SyliusSmtpPlugin\Core\Health\Thresholds;
use Calmfox\SyliusSmtpPlugin\Core\Log\Retention;
use Calmfox\SyliusSmtpPlugin\Core\Settings\ResolvedSettings;
use Calmfox\SyliusSmtpPlugin\Core\Settings\SettingsResolver;
use Calmfox\SyliusSmtpPlugin\Entity\SmtpSettings;

/**
 * What the shop will send with: the file, then the panel, then the provider's own rules.
 *
 * Precedence is the interesting part. Anything named in `config/packages/` is a default the panel
 * starts from; with `locked: true` the file wins outright and the panel goes read-only. That way a
 * shop with managed deployments can put its credentials in environment variables and a shop
 * without one can paste them into a form, and neither arrangement is second class.
 *
 * Resolved once per request: a page that sends three e-mails should not decrypt the password three
 * times.
 */
final class SettingsProvider
{
    private ?ResolvedSettings $resolved = null;

    /** @param array<string, mixed> $fileConfiguration the processed bundle configuration */
    public function __construct(
        private readonly array $fileConfiguration,
        private readonly SettingsRepository $repository,
        private readonly Secret $secret,
    ) {
    }

    public function resolve(): ResolvedSettings
    {
        return $this->resolved ??= (new SettingsResolver())->resolve($this->raw());
    }

    public function isLocked(): bool
    {
        return true === ($this->fileConfiguration['locked'] ?? false);
    }

    public function settings(): SmtpSettings
    {
        return $this->repository->findOrCreate();
    }

    public function encryptPassword(string $plain): string
    {
        return $this->secret->encrypt($plain);
    }

    /** Forget the resolved copy, so a form that has just saved does not answer with the old one. */
    public function forget(): void
    {
        $this->resolved = null;
    }

    public function healthCheckEnabled(): bool
    {
        return true === $this->health()['enabled'];
    }

    public function checkEverySeconds(): int
    {
        return max(60, (int) $this->health()['interval']);
    }

    public function thresholds(): Thresholds
    {
        $health = $this->health();

        return Thresholds::fromArray([
            'failed_checks' => $health['failed_checks'],
            'send_failures' => $health['send_failures'],
            'send_window' => $health['send_window'],
            'repeat_after' => $health['repeat_after'],
        ]);
    }

    public function warnsInPanel(): bool
    {
        return true === $this->alerts()['panel'];
    }

    public function warnsByEmail(): bool
    {
        return true === $this->alerts()['email'] && '' !== $this->alertRecipient();
    }

    public function alertRecipient(): string
    {
        $fromFile = trim((string) ($this->alerts()['recipient'] ?? ''));
        if ($this->isLocked()) {
            return $fromFile;
        }

        $stored = trim((string) $this->repository->find()?->getAlertRecipient());

        return '' !== $stored ? $stored : $fromFile;
    }

    public function warnsByWebhook(): bool
    {
        return true === $this->alerts()['webhook'] && '' !== $this->webhookUrl();
    }

    public function webhookUrl(): string
    {
        $fromFile = trim((string) ($this->alerts()['webhook_url'] ?? ''));
        if ($this->isLocked()) {
            return $fromFile;
        }

        $stored = trim((string) $this->repository->find()?->getWebhookUrl());

        return '' !== $stored ? $stored : $fromFile;
    }

    public function logEnabled(): bool
    {
        return true === $this->log()['enabled'];
    }

    public function logsBody(): bool
    {
        return true === $this->log()['store_body'];
    }

    public function retentionDays(): int
    {
        return Retention::days($this->log()['retention_days']);
    }

    /**
     * The settings as the framework-free resolver wants them.
     *
     * With `locked: true` the file is the whole story. Otherwise the stored row is the authority
     * — that is what the panel edits — and the file fills in whatever the row leaves empty, which
     * is what makes a fresh installation start from sensible values. The password is the one
     * exception: a value from the file (an environment variable, in practice) wins, because that
     * is the better place to keep one and a shop that does so should not have to also paste it
     * into a form.
     *
     * @return array<string, mixed>
     */
    private function raw(): array
    {
        $file = $this->fileConfiguration;
        $server = \is_array($file['server'] ?? null) ? $file['server'] : [];
        $sender = \is_array($file['sender'] ?? null) ? $file['sender'] : [];
        $stored = $this->isLocked() ? null : $this->repository->find();

        return [
            'enabled' => null !== $stored ? $stored->isEnabled() : (bool) ($file['enabled'] ?? false),
            'provider' => self::pick($stored?->getProvider(), $server['provider'] ?? null, 'custom'),
            'host' => self::pick($stored?->getHost(), $server['host'] ?? null),
            'port' => self::pick($stored?->getPort(), $server['port'] ?? null),
            'encryption' => self::pick($stored?->getEncryption(), $server['encryption'] ?? null, 'tls'),
            'auth' => self::pick($stored?->getAuth(), $server['auth'] ?? null, 'auto'),
            'username' => self::pick($stored?->getUsername(), $server['username'] ?? null),
            'password' => $this->password($server['password'] ?? null, $stored),
            'timeout' => self::pick($stored?->getTimeout(), $server['timeout'] ?? null),
            'verify_certificate' => null !== $stored
                ? $stored->isVerifyCertificate()
                : (bool) ($server['verify_certificate'] ?? true),
            'from_name' => self::pick($stored?->getFromName(), $sender['from_name'] ?? null),
            'from_email' => self::pick($stored?->getFromEmail(), $sender['from_email'] ?? null),
            'return_path' => self::pick($stored?->getReturnPath(), $sender['return_path'] ?? null),
        ];
    }

    private function password(mixed $fromFile, ?SmtpSettings $stored): string
    {
        $configured = trim((string) $fromFile);
        if ('' !== $configured) {
            // From an environment variable, so already in the clear.
            return $configured;
        }

        return $this->secret->decrypt($stored?->getPassword());
    }

    /** The first of these that somebody actually filled in. */
    private static function pick(mixed $stored, mixed $fromFile, mixed $fallback = null): mixed
    {
        foreach ([$stored, $fromFile, $fallback] as $candidate) {
            if (null !== $candidate && '' !== $candidate) {
                return $candidate;
            }
        }

        return $fallback;
    }

    /** @return array<string, mixed> */
    private function health(): array
    {
        return \is_array($this->fileConfiguration['health'] ?? null) ? $this->fileConfiguration['health'] : [];
    }

    /** @return array<string, mixed> */
    private function alerts(): array
    {
        return \is_array($this->fileConfiguration['alerts'] ?? null) ? $this->fileConfiguration['alerts'] : [];
    }

    /** @return array<string, mixed> */
    private function log(): array
    {
        return \is_array($this->fileConfiguration['log'] ?? null) ? $this->fileConfiguration['log'] : [];
    }
}

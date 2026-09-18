<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Settings;

/**
 * Encrypts the one value in this plugin that must not be readable in a database dump.
 *
 * Sylius has no encryptor of its own, and an SMTP password in a plain column is a credential that
 * travels with every copy of the database — to a developer's laptop, to a staging server, into a
 * backup somebody keeps on a desktop. It can send mail as the shop, so it is worth the small
 * amount of code.
 *
 * Libsodium's secretbox, keyed by a hash of the application secret, with a fresh nonce per value.
 * Rotating `APP_SECRET` therefore makes the stored password unreadable, which is the honest
 * behaviour — it is reported as "no password" and the panel asks for it again, rather than the
 * shop silently failing to log in.
 */
final class Secret
{
    private const PREFIX = 'calmfox-smtp:v1:';

    public function __construct(private readonly string $applicationSecret)
    {
    }

    public function encrypt(string $plain): string
    {
        if ('' === $plain) {
            return '';
        }

        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plain, $nonce, $this->key());

        return self::PREFIX . base64_encode($nonce . $cipher);
    }

    /** Anything we cannot read is treated as absent, which the panel then asks about. */
    public function decrypt(?string $stored): string
    {
        if (null === $stored || '' === $stored) {
            return '';
        }
        if (!str_starts_with($stored, self::PREFIX)) {
            // A value written before this plugin encrypted them, or put there by hand.
            return $stored;
        }

        $raw = base64_decode(substr($stored, \strlen(self::PREFIX)), true);
        if (!\is_string($raw) || \strlen($raw) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return '';
        }

        $nonce = substr($raw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->key());

        return \is_string($plain) ? $plain : '';
    }

    private function key(): string
    {
        return sodium_crypto_generichash($this->applicationSecret, '', \SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}

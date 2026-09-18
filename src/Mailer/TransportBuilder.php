<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Mailer;

use Calmfox\SyliusSmtpPlugin\Core\Settings\AuthMethod;
use Calmfox\SyliusSmtpPlugin\Core\Settings\Encryption;
use Calmfox\SyliusSmtpPlugin\Core\Settings\MailSettings;
use Symfony\Component\Mailer\Transport\Smtp\Auth\CramMd5Authenticator;
use Symfony\Component\Mailer\Transport\Smtp\Auth\LoginAuthenticator;
use Symfony\Component\Mailer\Transport\Smtp\Auth\PlainAuthenticator;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Builds the Symfony Mailer transport the message actually leaves on.
 *
 * Symfony's own ESMTP transport, configured from our settings — no third transport implementation
 * to keep working. Two details are deliberate.
 *
 * Transports are cached by the fingerprint of the settings they were built from, so a request
 * that sends five messages opens one connection and a settings change takes effect immediately
 * without anybody restarting anything.
 *
 * `auto` becomes Symfony's own negotiation: with no authenticator named, the transport picks from
 * what the server advertises, which is exactly what we want. Naming one is for the rarer case of
 * a server that advertises more than it will accept.
 */
final class TransportBuilder
{
    /** @var array<string, EsmtpTransport> */
    private array $transports = [];

    public function build(MailSettings $settings): TransportInterface
    {
        $fingerprint = $settings->fingerprint();
        if (isset($this->transports[$fingerprint])) {
            return $this->transports[$fingerprint];
        }

        $transport = new EsmtpTransport(
            $settings->host,
            $settings->port,
            Encryption::SSL === $settings->encryption,
        );

        $transport->setLocalDomain(LocalDomain::of($settings));

        if (Encryption::NONE !== $settings->encryption) {
            $transport->setRequireTls(true);
        }
        if ($settings->usesAuthentication()) {
            $transport->setUsername($settings->username);
            $transport->setPassword($settings->password);
            $authenticator = $this->authenticator($settings->authMethod);
            if (null !== $authenticator) {
                $transport->setAuthenticators([$authenticator]);
            }
        }

        // The timeout and the TLS options live on the socket stream, and only on that one:
        // a transport configured to hand its mail to a local process has no socket to set them
        // on. Asking first is the difference between a setting being ignored and a fatal error.
        $stream = $transport->getStream();
        if ($stream instanceof SocketStream) {
            $stream->setTimeout((float) $settings->timeout);

            if (!$settings->verifyCertificate) {
                // Only ever reached because somebody turned the check off and was warned.
                $stream->setStreamOptions([
                    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true],
                ]);
            }
        }

        return $this->transports[$fingerprint] = $transport;
    }

    private function authenticator(string $method): LoginAuthenticator|PlainAuthenticator|CramMd5Authenticator|null
    {
        return match ($method) {
            AuthMethod::LOGIN => new LoginAuthenticator(),
            AuthMethod::PLAIN => new PlainAuthenticator(),
            AuthMethod::CRAM_MD5 => new CramMd5Authenticator(),
            default => null,
        };
    }
}

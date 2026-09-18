<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Probe;

use Calmfox\SyliusSmtpPlugin\Core\Diagnosis\Cause;
use Calmfox\SyliusSmtpPlugin\Core\Diagnosis\Diagnosis;
use Calmfox\SyliusSmtpPlugin\Core\Probe\ProbeResult;
use Calmfox\SyliusSmtpPlugin\Core\Probe\SmtpDialogue;
use Calmfox\SyliusSmtpPlugin\Core\Probe\Stage;
use Calmfox\SyliusSmtpPlugin\Core\Settings\Encryption;
use Calmfox\SyliusSmtpPlugin\Core\Settings\MailSettings;
use Calmfox\SyliusSmtpPlugin\Mailer\LocalDomain;

/**
 * One check of the connection: open a socket, hold the conversation, time it, name what happened.
 *
 * The socket lives here and every decision lives in the framework-free core, which is why the
 * interesting cases — a refused password, a handshake that fails halfway, a server that answers
 * in HTTP — are unit tested and this class stays short enough to read in one go.
 */
final class SmtpProbe
{
    public function __construct(private readonly SmtpDialogue $dialogue)
    {
    }

    public function run(MailSettings $settings): ProbeResult
    {
        if ('' === $settings->host) {
            return ProbeResult::failed(Stage::CONNECT, Cause::NOT_CONFIGURED);
        }

        $started = microtime(true);
        $errno = 0;
        $error = '';

        $handle = @stream_socket_client(
            sprintf(
                '%s%s:%d',
                Encryption::SSL === $settings->encryption ? 'ssl://' : 'tcp://',
                $settings->host,
                $settings->port,
            ),
            $errno,
            $error,
            (float) $settings->timeout,
            \STREAM_CLIENT_CONNECT,
            stream_context_create([
                'ssl' => [
                    'verify_peer' => $settings->verifyCertificate,
                    'verify_peer_name' => $settings->verifyCertificate,
                    'allow_self_signed' => !$settings->verifyCertificate,
                    'peer_name' => $settings->host,
                    'SNI_enabled' => true,
                ],
            ]),
        );

        if (!\is_resource($handle)) {
            return ProbeResult::failed(
                Stage::CONNECT,
                Diagnosis::fromConnectionError((int) $errno, (string) $error),
                null,
                '' !== (string) $error ? (string) $error : 'no answer',
                milliseconds: self::since($started),
            );
        }

        $stream = new SocketStream($handle, $settings->timeout);
        $result = $this->dialogue->run($stream, $settings, LocalDomain::of($settings));

        if (Cause::TLS_HANDSHAKE_FAILED === $result->cause && '' !== $stream->lastError()) {
            // PHP's warning knows whether this was a certificate or a protocol version; the
            // conversation could only see that TLS did not happen.
            $result = $result->withCause(Diagnosis::fromConnectionError(0, $stream->lastError()), $stream->lastError());
        }

        return $result->withTiming(self::since($started));
    }

    private static function since(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}

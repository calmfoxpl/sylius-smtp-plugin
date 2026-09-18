<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Mailer;

use Calmfox\SyliusSmtpPlugin\Core\Settings\MailSettings;

/**
 * The name the shop introduces itself with, in EHLO.
 *
 * Some providers refuse a bare `localhost`, and a few check that it looks like a name at all.
 * The domain of the sending address is both true and acceptable everywhere, which makes it a
 * better answer than the server's own hostname — that is often a hosting company's.
 */
final class LocalDomain
{
    public static function of(MailSettings $settings): string
    {
        foreach ([$settings->fromEmail, $settings->returnPath, $settings->username] as $candidate) {
            $at = strrpos((string) $candidate, '@');
            if (false !== $at) {
                $domain = substr((string) $candidate, $at + 1);
                if ('' !== $domain) {
                    return $domain;
                }
            }
        }

        return '[127.0.0.1]';
    }
}

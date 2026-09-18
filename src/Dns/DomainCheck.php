<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Dns;

use Calmfox\SyliusSmtpPlugin\Core\Dns\DomainInspector;
use Calmfox\SyliusSmtpPlugin\Core\Dns\DomainVerdict;
use Calmfox\SyliusSmtpPlugin\Core\Dns\SenderDomain;
use Calmfox\SyliusSmtpPlugin\Core\Dns\Suggestion;
use Calmfox\SyliusSmtpPlugin\Settings\SettingsProvider;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * The domain check as the rest of the plugin sees it, with the one rule that matters: only
 * `refresh()` is allowed to touch the network.
 *
 * Everything that renders — the report, the notice on the dashboard, the settings screen — asks
 * for the stored verdict and gets `null` when there is none. `dns_get_record()` takes no timeout
 * it can be told about: it inherits `/etc/resolv.conf`, commonly five seconds and two attempts,
 * so a page that looked up a record could hang for ten seconds to tell somebody their SPF is
 * wrong. That would be a worse fault than the one being reported.
 *
 * The stored verdict is keyed by the settings it was reached under, so editing the provider
 * throws it away rather than showing yesterday's verdict about a provider nobody uses now.
 */
final class DomainCheck
{
    /** Six hours: DNS is changed by hand, and rarely twice in a day. */
    public const LIFETIME = 21600;

    public function __construct(
        private readonly SettingsProvider $settings,
        private readonly DomainInspector $inspector,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly bool $enabled,
    ) {
    }

    /** The stored verdict, or null. Never asks DNS anything. */
    public function stored(): ?DomainVerdict
    {
        if (!$this->enabled) {
            return null;
        }

        try {
            // A PSR-6 pool rather than the callback cache on purpose: reading must not write.
            // The callback form would store an empty entry on every admin page that renders,
            // which is a cache write per page view to answer "nothing has been checked yet".
            $item = $this->cache->getItem($this->key());
            if (!$item->isHit()) {
                return null;
            }

            $row = $item->get();

            return \is_array($row) ? DomainVerdict::fromArray($row) : null;
        } catch (\Throwable $error) {
            $this->logger->warning('Calmfox SMTP: the stored domain verdict could not be read.', ['exception' => $error]);

            return null;
        }
    }

    /** Asks DNS and keeps the answer. For the button, the scheduled command and the console. */
    public function refresh(): ?DomainVerdict
    {
        if (!$this->enabled) {
            return null;
        }

        $verdict = $this->inspector->inspect($this->settings->resolve()->settings);

        try {
            $item = $this->cache->getItem($this->key());
            $item->set($verdict->toArray());
            $item->expiresAfter(self::LIFETIME);
            $this->cache->save($item);
        } catch (\Throwable $error) {
            $this->logger->warning('Calmfox SMTP: the domain verdict could not be stored.', ['exception' => $error]);
        }

        return $verdict;
    }

    /**
     * What the shop's own domain suggests it is set up to send through.
     *
     * Only for the settings screen, and only on a button: this is the one place where somebody
     * is sitting in front of the panel having asked the question, so a wait is honest there.
     */
    public function suggest(?string $fallbackDomain = null): Suggestion
    {
        $domain = SenderDomain::of($this->settings->resolve()->settings) ?? $fallbackDomain;

        return null === $domain ? Suggestion::none() : $this->inspector->suggest($domain);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    private function key(): string
    {
        return 'calmfox_smtp_dns_' . $this->settings->resolve()->settings->fingerprint();
    }
}

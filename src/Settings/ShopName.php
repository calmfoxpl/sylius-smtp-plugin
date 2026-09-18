<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Settings;

use Doctrine\Persistence\ObjectRepository;
use Sylius\Component\Core\Model\ChannelInterface;

/**
 * What to call the shop in a warning.
 *
 * An alert that says "the shop cannot send e-mail" is much less useful to somebody who looks
 * after four of them. The default channel's name is the closest thing Sylius has to the shop's
 * own name, and it is resolved through the repository rather than the channel context because
 * these warnings are mostly written from a console command, where there is no request and
 * therefore no current channel.
 */
final class ShopName
{
    private ?string $resolved = null;

    /** @param ObjectRepository<ChannelInterface> $channels */
    public function __construct(
        private readonly ObjectRepository $channels,
        private readonly string $fallback = 'Sylius',
    ) {
    }

    public function __toString(): string
    {
        return $this->get();
    }

    public function get(): string
    {
        if (null !== $this->resolved) {
            return $this->resolved;
        }

        try {
            $channel = $this->channels->findOneBy(['enabled' => true]);
            $name = trim((string) $channel?->getName());
        } catch (\Throwable) {
            $name = '';
        }

        return $this->resolved = '' !== $name ? $name : $this->fallback;
    }
}

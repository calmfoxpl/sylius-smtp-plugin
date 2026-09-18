<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Entity;

use Calmfox\SyliusSmtpPlugin\Core\Health\HealthState;
use Doctrine\ORM\Mapping as ORM;

/**
 * The current verdict about whether the shop can send, and the counters behind it.
 *
 * One row, overwritten in place: this is a cache of a judgement, not a history. A table that grew
 * a row every quarter of an hour would be this plugin's largest within a year and nobody would
 * ever read the old ones.
 *
 * The columns are the fields of the framework-free {@see HealthState}, which is what the
 * decisions are made on; this class only knows how to keep one and hand it back.
 */
#[ORM\Entity]
#[ORM\Table(name: 'calmfox_smtp_health')]
class HealthRecord
{
    public const ID = 1;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $id = self::ID;

    /** @var array<string, int|string|null> */
    #[ORM\Column(type: 'json')]
    private array $state = [];

    public function getId(): int
    {
        return $this->id;
    }

    public function getState(): HealthState
    {
        return HealthState::fromArray($this->state);
    }

    public function setState(HealthState $state): void
    {
        $this->state = $state->toArray();
    }
}

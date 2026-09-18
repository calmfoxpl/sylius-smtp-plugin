<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Health;

use Calmfox\SyliusSmtpPlugin\Core\Health\HealthState;
use Calmfox\SyliusSmtpPlugin\Entity\HealthRecord;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Where the verdict is kept: one row, overwritten in place.
 *
 * Every read and every write is wrapped, and that is not defensive habit — it is the plugin's
 * most important safety property. This is called from the mail transport, so a migration that has
 * not been run, or a database that is briefly read-only, must not turn into an order confirmation
 * that never goes out. Losing the health record is a nuisance; losing the e-mail is the thing we
 * exist to prevent.
 */
final class HealthStore
{
    private ?HealthState $cached = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function load(): HealthState
    {
        if (null !== $this->cached) {
            return $this->cached;
        }

        try {
            $record = $this->entityManager->find(HealthRecord::class, HealthRecord::ID);

            return $this->cached = $record?->getState() ?? new HealthState();
        } catch (\Throwable $error) {
            $this->logger->warning('Calmfox SMTP: the health record could not be read.', ['exception' => $error]);

            return new HealthState();
        }
    }

    public function save(HealthState $state): void
    {
        try {
            $record = $this->entityManager->find(HealthRecord::class, HealthRecord::ID) ?? new HealthRecord();
            $record->setState($state);
            $this->entityManager->persist($record);
            $this->entityManager->flush();
            $this->cached = $state;
        } catch (\Throwable $error) {
            $this->logger->warning('Calmfox SMTP: the health record could not be written.', ['exception' => $error]);
        }
    }
}

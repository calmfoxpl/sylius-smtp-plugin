<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Log;

use Calmfox\SyliusSmtpPlugin\Core\Log\Retention;
use Calmfox\SyliusSmtpPlugin\Entity\EmailLogEntry;
use Calmfox\SyliusSmtpPlugin\Settings\SettingsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Throws away log entries that are past their retention.
 *
 * It runs whether or not the log is switched on, because the reason to delete is not
 * housekeeping: a shop that turned the log off last month should not still be holding a fortnight
 * of its customers' order confirmations.
 *
 * Deleted in slices. A shop that has been sending for a year before anybody sets a retention
 * would otherwise meet one delete of millions of rows, which locks the table for as long as it
 * takes — and a scheduled job should not be able to become an outage.
 */
final class Pruner
{
    private const BATCH = 5000;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingsProvider $settings,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function prune(?int $days = null): int
    {
        $days ??= $this->settings->retentionDays();
        $cutoff = 0 === $days
            // Zero means "all of it", which is what somebody asking to empty the log means. The
            // retention rules read a zero as "unset" and hand back the default, so it is spelled
            // out here rather than quietly keeping a month.
            ? new \DateTimeImmutable('+1 second')
            : new \DateTimeImmutable('@' . Retention::cutoff(time(), $days));

        $removed = 0;

        try {
            do {
                $ids = $this->entityManager->createQueryBuilder()
                    ->select('entry.id')
                    ->from(EmailLogEntry::class, 'entry')
                    ->where('entry.sentAt < :cutoff')
                    ->setParameter('cutoff', $cutoff)
                    ->setMaxResults(self::BATCH)
                    ->getQuery()
                    ->getSingleColumnResult();

                if ([] === $ids) {
                    break;
                }

                $removed += (int) $this->entityManager->createQueryBuilder()
                    ->delete(EmailLogEntry::class, 'entry')
                    ->where('entry.id IN (:ids)')
                    ->setParameter('ids', $ids)
                    ->getQuery()
                    ->execute();
            } while (\count($ids) === self::BATCH);
        } catch (\Throwable $error) {
            $this->logger->warning('Calmfox SMTP: the log could not be pruned.', ['exception' => $error]);
        }

        return $removed;
    }
}

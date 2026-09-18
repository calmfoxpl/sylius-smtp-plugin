<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Log;

use Calmfox\SyliusSmtpPlugin\Core\Log\Outcome;
use Calmfox\SyliusSmtpPlugin\Entity\EmailLogEntry;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Reads the log for the one page that shows it.
 *
 * A page of entries and a count, newest first, with the two filters that answer the question
 * people actually arrive with: "did this address get anything?" and "what failed today?".
 */
final class LogRepository
{
    public const PER_PAGE = 30;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /** @return array{entries: list<EmailLogEntry>, total: int, pages: int, page: int} */
    public function page(int $page = 1, string $outcome = '', string $search = ''): array
    {
        $page = max(1, $page);
        $total = (int) $this->query($outcome, $search)
            ->select('COUNT(entry.id)')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<EmailLogEntry> $entries */
        $entries = $this->query($outcome, $search)
            ->select('entry')
            ->orderBy('entry.sentAt', 'DESC')
            ->setFirstResult(($page - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE)
            ->getQuery()
            ->getResult();

        return [
            'entries' => $entries,
            'total' => $total,
            'pages' => max(1, (int) ceil($total / self::PER_PAGE)),
            'page' => $page,
        ];
    }

    public function find(int $id): ?EmailLogEntry
    {
        return $this->entityManager->find(EmailLogEntry::class, $id);
    }

    public function save(EmailLogEntry $entry): void
    {
        $this->entityManager->persist($entry);
        $this->entityManager->flush();
    }

    private function query(string $outcome, string $search): \Doctrine\ORM\QueryBuilder
    {
        $query = $this->entityManager->createQueryBuilder()->from(EmailLogEntry::class, 'entry');

        if (\in_array($outcome, Outcome::ALL, true)) {
            $query->andWhere('entry.outcome = :outcome')->setParameter('outcome', $outcome);
        }
        if ('' !== trim($search)) {
            $query
                ->andWhere('entry.recipients LIKE :search OR entry.subject LIKE :search OR entry.fromAddress LIKE :search')
                ->setParameter('search', '%' . trim($search) . '%');
        }

        return $query;
    }
}

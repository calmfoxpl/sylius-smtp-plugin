<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Settings;

use Calmfox\SyliusSmtpPlugin\Entity\SmtpSettings;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * The one settings row, fetched or invented.
 *
 * Reads are wrapped because this is on the path of every outgoing message: a migration that has
 * not been run yet must not turn into an order confirmation nobody receives. With no row, the
 * plugin behaves as if it were switched off, which is what a fresh installation means.
 */
final class SettingsRepository
{
    private ?SmtpSettings $cached = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function find(): ?SmtpSettings
    {
        if (null !== $this->cached) {
            return $this->cached;
        }

        try {
            return $this->cached = $this->entityManager->find(SmtpSettings::class, SmtpSettings::ID);
        } catch (\Throwable $error) {
            $this->logger->warning('Calmfox SMTP: the settings could not be read.', ['exception' => $error]);

            return null;
        }
    }

    /** For the form: the stored row, or a fresh one that has not been saved yet. */
    public function findOrCreate(): SmtpSettings
    {
        return $this->find() ?? new SmtpSettings();
    }

    public function save(SmtpSettings $settings): void
    {
        $this->entityManager->persist($settings);
        $this->entityManager->flush();
        $this->cached = $settings;
    }
}

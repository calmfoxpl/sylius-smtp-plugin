<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusSmtpPlugin\Unit\Settings;

use Calmfox\SyliusSmtpPlugin\Core\Settings\Encryption;
use Calmfox\SyliusSmtpPlugin\Entity\SmtpSettings;
use Calmfox\SyliusSmtpPlugin\Settings\Secret;
use Calmfox\SyliusSmtpPlugin\Settings\SettingsProvider;
use Calmfox\SyliusSmtpPlugin\Settings\SettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * The merge of the file and the panel, for the one value whose "nothing" means something.
 *
 * Needs Doctrine's interface to stand in for the database, so unlike the core tests it is skipped
 * in a checkout with no vendor directory.
 */
final class SettingsProviderTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('Needs doctrine/orm.');
        }
    }

    public function testNoEncryptionChosenInThePanelStaysNoEncryption(): void
    {
        $stored = new SmtpSettings();
        $stored->setEnabled(true);
        $stored->setHost('localhost');
        $stored->setPort(25);
        $stored->setEncryption(Encryption::NONE);

        self::assertSame(Encryption::NONE, $this->provider([], $stored)->resolve()->settings->encryption);
    }

    public function testNoEncryptionSetInTheFileStaysNoEncryption(): void
    {
        $file = ['enabled' => true, 'server' => ['host' => 'localhost', 'port' => 25, 'encryption' => Encryption::NONE]];

        self::assertSame(Encryption::NONE, $this->provider($file, null)->resolve()->settings->encryption);
    }

    public function testWithNothingSaidAnywhereTheConnectionIsEncrypted(): void
    {
        self::assertSame(Encryption::TLS, $this->provider([], null)->resolve()->settings->encryption);
    }

    /** @param array<string, mixed> $file */
    private function provider(array $file, ?SmtpSettings $stored): SettingsProvider
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn($stored);

        return new SettingsProvider(
            $file,
            new SettingsRepository($entityManager, new NullLogger()),
            new Secret('not-a-real-secret'),
        );
    }
}

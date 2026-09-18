<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * The settings, as edited in the panel. One row, always.
 *
 * In the database rather than in `config/packages/` because of what actually happens to these
 * values: an API key is rotated on a Tuesday afternoon by whoever has the provider's dashboard
 * open, and that should not need a deployment. Anything a deployment *should* own can be pinned
 * in YAML instead, and then this row is not read at all.
 *
 * The password is stored encrypted. Sylius has no encryptor of its own, so the plugin brings a
 * small one keyed by the application secret — which means a database dump handed to a developer
 * does not come with the shop's sending credentials in it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'calmfox_smtp_settings')]
class SmtpSettings
{
    /** There is one mailer, so there is one row, and it always has this id. */
    public const ID = 1;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $id = self::ID;

    #[ORM\Column(type: 'boolean')]
    private bool $enabled = false;

    #[ORM\Column(type: 'string', length: 32)]
    private string $provider = 'custom';

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $host = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $port = null;

    #[ORM\Column(type: 'string', length: 8)]
    private string $encryption = 'tls';

    #[ORM\Column(type: 'string', length: 16)]
    private string $auth = 'auto';

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $username = null;

    /** Ciphertext. Never the password itself, and never logged. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $password = null;

    #[ORM\Column(type: 'integer')]
    private int $timeout = 5;

    #[ORM\Column(type: 'boolean')]
    private bool $verifyCertificate = true;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $fromEmail = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $fromName = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $returnPath = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $alertRecipient = null;

    #[ORM\Column(type: 'string', length: 1024, nullable: true)]
    private ?string $webhookUrl = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): void
    {
        $this->provider = $provider;
    }

    public function getHost(): ?string
    {
        return $this->host;
    }

    public function setHost(?string $host): void
    {
        $this->host = $host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function setPort(?int $port): void
    {
        $this->port = $port;
    }

    public function getEncryption(): string
    {
        return $this->encryption;
    }

    public function setEncryption(string $encryption): void
    {
        $this->encryption = $encryption;
    }

    public function getAuth(): string
    {
        return $this->auth;
    }

    public function setAuth(string $auth): void
    {
        $this->auth = $auth;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): void
    {
        $this->username = $username;
    }

    /** The ciphertext as stored; decrypting it is the settings provider's job. */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): void
    {
        $this->password = $password;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    public function isVerifyCertificate(): bool
    {
        return $this->verifyCertificate;
    }

    public function setVerifyCertificate(bool $verifyCertificate): void
    {
        $this->verifyCertificate = $verifyCertificate;
    }

    public function getFromEmail(): ?string
    {
        return $this->fromEmail;
    }

    public function setFromEmail(?string $fromEmail): void
    {
        $this->fromEmail = $fromEmail;
    }

    public function getFromName(): ?string
    {
        return $this->fromName;
    }

    public function setFromName(?string $fromName): void
    {
        $this->fromName = $fromName;
    }

    public function getReturnPath(): ?string
    {
        return $this->returnPath;
    }

    public function setReturnPath(?string $returnPath): void
    {
        $this->returnPath = $returnPath;
    }

    public function getAlertRecipient(): ?string
    {
        return $this->alertRecipient;
    }

    public function setAlertRecipient(?string $alertRecipient): void
    {
        $this->alertRecipient = $alertRecipient;
    }

    public function getWebhookUrl(): ?string
    {
        return $this->webhookUrl;
    }

    public function setWebhookUrl(?string $webhookUrl): void
    {
        $this->webhookUrl = $webhookUrl;
    }
}

<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Entity;

use Calmfox\SyliusSmtpPlugin\Core\Log\Outcome;
use Doctrine\ORM\Mapping as ORM;

/**
 * One message the shop tried to send.
 *
 * This table holds personal data — names, addresses, order contents, the occasional password
 * reset link — which is why the body is a separate nullable column that is only filled when
 * somebody asks for it, why the retention is short, and why a body is dropped the moment a
 * resend succeeds and there is nothing left to do with it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'calmfox_smtp_log')]
#[ORM\Index(columns: ['sent_at'], name: 'calmfox_smtp_log_sent_at')]
#[ORM\Index(columns: ['outcome', 'sent_at'], name: 'calmfox_smtp_log_outcome')]
class EmailLogEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime_immutable', name: 'sent_at')]
    private \DateTimeImmutable $sentAt;

    #[ORM\Column(type: 'string', length: 16)]
    private string $outcome = Outcome::SENT;

    #[ORM\Column(type: 'string', length: 32)]
    private string $provider = '';

    #[ORM\Column(type: 'string', length: 255)]
    private string $endpoint = '';

    #[ORM\Column(type: 'string', length: 255)]
    private string $fromAddress = '';

    #[ORM\Column(type: 'string', length: 255)]
    private string $recipients = '';

    #[ORM\Column(type: 'string', length: 255)]
    private string $subject = '';

    #[ORM\Column(type: 'integer')]
    private int $messageSize = 0;

    #[ORM\Column(type: 'integer')]
    private int $durationMs = 0;

    #[ORM\Column(type: 'integer')]
    private int $attempts = 1;

    #[ORM\Column(type: 'string', length: 40, nullable: true)]
    private ?string $cause = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $error = null;

    /** The message itself, kept only for a failure that could still be sent again. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rawMessage = null;

    /**
     * The envelope, kept in full because the recipient column is shortened for the grid and a
     * resend needs every address, not the first three and a count.
     *
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    private array $envelopeRecipients = [];

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $envelopeSender = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $resentAt = null;

    public function __construct()
    {
        $this->sentAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getOutcome(): string
    {
        return $this->outcome;
    }

    public function setOutcome(string $outcome): void
    {
        $this->outcome = \in_array($outcome, Outcome::ALL, true) ? $outcome : Outcome::FAILED;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): void
    {
        $this->provider = $provider;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function setEndpoint(string $endpoint): void
    {
        $this->endpoint = $endpoint;
    }

    public function getFromAddress(): string
    {
        return $this->fromAddress;
    }

    public function setFromAddress(string $fromAddress): void
    {
        $this->fromAddress = mb_substr($fromAddress, 0, 255);
    }

    public function getRecipients(): string
    {
        return $this->recipients;
    }

    public function setRecipients(string $recipients): void
    {
        $this->recipients = mb_substr($recipients, 0, 255);
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): void
    {
        $this->subject = mb_substr($subject, 0, 255);
    }

    public function getMessageSize(): int
    {
        return $this->messageSize;
    }

    public function setMessageSize(int $messageSize): void
    {
        $this->messageSize = $messageSize;
    }

    public function getDurationMs(): int
    {
        return $this->durationMs;
    }

    public function setDurationMs(int $durationMs): void
    {
        $this->durationMs = $durationMs;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function anotherAttempt(): void
    {
        ++$this->attempts;
        $this->resentAt = new \DateTimeImmutable();
    }

    public function getCause(): ?string
    {
        return $this->cause;
    }

    public function setCause(?string $cause): void
    {
        $this->cause = $cause;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): void
    {
        $this->error = null === $error ? null : mb_substr($error, 0, 1000);
    }

    public function getRawMessage(): ?string
    {
        return $this->rawMessage;
    }

    public function setRawMessage(?string $rawMessage): void
    {
        $this->rawMessage = $rawMessage;
    }

    public function forgetBody(): void
    {
        $this->rawMessage = null;
    }

    /** @return list<string> */
    public function getEnvelopeRecipients(): array
    {
        return $this->envelopeRecipients;
    }

    /** @param list<string> $recipients */
    public function setEnvelopeRecipients(array $recipients): void
    {
        $this->envelopeRecipients = $recipients;
    }

    public function getEnvelopeSender(): ?string
    {
        return $this->envelopeSender;
    }

    public function setEnvelopeSender(?string $sender): void
    {
        $this->envelopeSender = $sender;
    }

    public function getResentAt(): ?\DateTimeImmutable
    {
        return $this->resentAt;
    }

    public function hasFailed(): bool
    {
        return Outcome::FAILED === $this->outcome;
    }

    /** A resend is only offered where there is something left to send. */
    public function canBeResent(): bool
    {
        return $this->hasFailed() &&
            null !== $this->rawMessage &&
            '' !== $this->rawMessage &&
            [] !== $this->envelopeRecipients;
    }
}

<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Text;

use Calmfox\SyliusSmtpPlugin\Core\Diagnosis\Cause;
use Calmfox\SyliusSmtpPlugin\Core\Diagnosis\Diagnosis;
use Calmfox\SyliusSmtpPlugin\Core\Health\HealthState;
use Calmfox\SyliusSmtpPlugin\Core\Health\Status;
use Calmfox\SyliusSmtpPlugin\Core\Settings\Issue;
use Calmfox\SyliusSmtpPlugin\Core\Settings\MailSettings;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Every sentence this plugin says to a person.
 *
 * The core decides things and names them with codes; this is where a code becomes language. The
 * split is what makes translation possible at all — a sentence written inside the core could
 * never be translated — and it is also the only way to keep the tone consistent, because the
 * whole vocabulary can be read in one place in five minutes.
 *
 * The house rules for that wording:
 *
 *  - say what happened, then the one thing most worth checking, and stop,
 *  - never claim more than we know: the check proves a connection, never a delivery,
 *  - a hint is a hint ("most often", not "the problem is"),
 *  - no protocol vocabulary where a plain word exists, and the server's own words kept verbatim
 *    where there is none, because that is what a hosting company will ask for.
 */
final class Wording
{
    public const DOMAIN = 'messages';

    private const PREFIX = 'calmfox_smtp.';

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function status(string $status): string
    {
        return $this->say('status.' . Status::normalize($status));
    }

    /** What went wrong, short enough for a heading. */
    public function cause(string $cause): string
    {
        return $this->say('cause.' . $this->known($cause));
    }

    /** The same thing as a sentence, about this shop's own server. */
    public function detail(string $cause, MailSettings $settings): string
    {
        return $this->say('detail.' . $this->known($cause), [
            '%endpoint%' => $settings->endpoint(),
            '%host%' => $settings->host,
            '%timeout%' => (string) $settings->timeout,
        ]);
    }

    /** The one thing most worth checking, given the provider. Never more than one. */
    public function hint(string $hint): string
    {
        return $this->say('hint.' . $hint);
    }

    /** How far the conversation got, as a phrase that fits after "while". */
    public function stage(string $stage): string
    {
        return $this->say('stage.' . ('' === $stage ? 'finishing' : $stage));
    }

    public function issue(Issue $issue): string
    {
        $parameters = [];
        foreach ($issue->params as $index => $value) {
            $parameters['%' . ($index + 1) . '%'] = (string) $value;
        }

        return $this->say('issue.' . $issue->code, $parameters);
    }

    /**
     * One line that says everything: the state, the stage and the place.
     *
     * This is what goes in the banner, in the alert's subject and in the console. One sentence,
     * because it is read by somebody in the middle of something else.
     */
    public function headline(HealthState $state, MailSettings $settings): string
    {
        if (Status::OK === $state->status) {
            return $this->say('headline.ok', ['%endpoint%' => $settings->endpoint()]);
        }
        if (Status::UNKNOWN === $state->status) {
            return $this->say('headline.unknown', ['%endpoint%' => $settings->endpoint()]);
        }

        return $this->say('headline.broken', [
            '%cause%' => $this->cause($state->cause),
            '%stage%' => $this->stage($state->stage),
            '%endpoint%' => $settings->endpoint(),
        ]);
    }

    /**
     * The heading for the sender domain's records, kept deliberately apart from "cannot send".
     *
     * The distinction is the whole point of having it. Red means no mail is leaving the shop and
     * somebody has to act within the hour. This means mail is leaving and may not be arriving,
     * which is a different job, usually for whoever looks after the domain. Saying both in the
     * same red box would rob the red one of its meaning within a week.
     */
    public function deliverabilityHeadline(?string $domain): string
    {
        if (null === $domain) {
            return $this->say('deliverability.unknown_domain');
        }

        return $this->say('deliverability.headline', ['%domain%' => $domain]);
    }

    public function deliverabilityFine(?string $domain): string
    {
        if (null === $domain) {
            return $this->say('deliverability.unknown_domain');
        }

        return $this->say('deliverability.fine', ['%domain%' => $domain]);
    }

    /** What the domain publishes, as a fact rather than a complaint. */
    public function domainFact(string $what, ?bool $state): string
    {
        if (null === $state) {
            return $this->say('domain.not_checked');
        }
        if ('spf_provider' === $what) {
            return $this->say($state ? 'domain.authorises' : 'domain.does_not_authorise');
        }

        return $this->say($state ? 'domain.published' : 'domain.not_published');
    }

    /** The sentence that says it is over, which is what earns the next warning a reading. */
    public function recovery(MailSettings $settings): string
    {
        return $this->say('headline.recovered', ['%endpoint%' => $settings->endpoint()]);
    }

    /**
     * Everything we know about a failure, in the order it is useful.
     *
     * @return list<string>
     */
    public function explain(HealthState $state, MailSettings $settings): array
    {
        $lines = [$this->detail($state->cause, $settings)];

        $hint = Diagnosis::hint($state->cause, $settings);
        if (null !== $hint) {
            $lines[] = $this->hint($hint);
        }
        if ('' !== $state->reply && Cause::NOT_CONFIGURED !== $state->cause) {
            $lines[] = $this->say('server_said', ['%reply%' => $state->reply]);
        }

        return $lines;
    }

    /**
     * A sentence that is its own key: the provider hints.
     *
     * Those live in the framework-free catalogue next to the host and the port they describe,
     * which is the right place for them — but it means their English text is the key. Symfony
     * falls back to the key when a translation is missing, so a shop reading English is the worst
     * case rather than a shop reading a key.
     */
    public function raw(string $text): string
    {
        return '' === $text ? '' : $this->translator->trans($text, [], self::DOMAIN);
    }

    /** @param array<string, string> $parameters */
    public function say(string $key, array $parameters = []): string
    {
        return $this->translator->trans(self::PREFIX . $key, $parameters, self::DOMAIN);
    }

    /** A cause we have never heard of is reported as unknown rather than as a missing key. */
    private function known(string $cause): string
    {
        return \in_array($cause, Cause::ALL, true) ? $cause : Cause::UNKNOWN;
    }
}

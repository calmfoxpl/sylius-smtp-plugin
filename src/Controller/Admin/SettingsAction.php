<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Controller\Admin;

use Calmfox\SyliusSmtpPlugin\Core\Provider\Provider;
use Calmfox\SyliusSmtpPlugin\Core\Provider\ProviderCatalog;
use Calmfox\SyliusSmtpPlugin\Entity\SmtpSettings;
use Calmfox\SyliusSmtpPlugin\Form\Type\SmtpSettingsType;
use Calmfox\SyliusSmtpPlugin\Settings\SettingsProvider;
use Calmfox\SyliusSmtpPlugin\Settings\SettingsRepository;
use Calmfox\SyliusSmtpPlugin\Text\Wording;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Configuration → E-mail sending: pick a provider, paste the two credentials, save.
 *
 * The provider catalogue is handed to the page as data, so that choosing one fills in the server,
 * the port and the encryption and shows what *that provider* means by its credential fields —
 * which is the difference between this form and a form with two boxes marked "user name" and
 * "password".
 *
 * Where the settings are pinned in `config/packages/`, the form is not offered at all: a field
 * that quietly does nothing is worse than a sentence explaining where the values come from.
 */
final readonly class SettingsAction
{
    public function __construct(
        private SettingsProvider $settings,
        private SettingsRepository $repository,
        private Wording $wording,
        private FormFactoryInterface $formFactory,
        private UrlGeneratorInterface $urlGenerator,
        private Environment $twig,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $settings = $this->settings->settings();
        $form = $this->formFactory->create(SmtpSettingsType::class, $settings);

        if (!$this->settings->isLocked()) {
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $this->save($settings, (string) $form->get('plainPassword')->getData());

                $session = $request->getSession();
                if ($session instanceof FlashBagAwareSessionInterface) {
                    $session->getFlashBag()->add('success', 'calmfox_smtp.flash.settings_saved');
                }

                return new RedirectResponse($this->urlGenerator->generate('calmfox_smtp_health'));
            }
        }

        return new Response($this->twig->render('@CalmfoxSyliusSmtpPlugin/admin/settings.html.twig', [
            'form' => $form->createView(),
            'locked' => $this->settings->isLocked(),
            'presets' => $this->presets(),
            'issues' => $this->issues(),
        ]));
    }

    /** An empty password field means "leave the stored one alone", not "delete it". */
    private function save(SmtpSettings $settings, string $typedPassword): void
    {
        if ('' !== $typedPassword) {
            $settings->setPassword($this->settings->encryptPassword($typedPassword));
        }

        $this->repository->save($settings);
        $this->settings->forget();
    }

    /** @return array<string, array<string, mixed>> the catalogue, translated, for the browser */
    private function presets(): array
    {
        $presets = [];
        foreach (ProviderCatalog::all() as $provider) {
            $presets[$provider->id] = [
                'label' => $provider->label,
                'host' => $provider->host,
                'port' => $provider->port,
                'encryption' => $provider->encryption,
                'auth' => $provider->authMethod,
                'pinnedUsername' => $provider->pinnedUsername,
                'hints' => array_map($this->wording->raw(...), $provider->hints()),
                'hostVariants' => $this->hostVariants($provider),
                'docsUrl' => $provider->docsUrl,
            ];
        }

        return $presets;
    }

    /**
     * Region names are translated; the hosts they point at are not.
     *
     * @return array<string, string>
     */
    private function hostVariants(Provider $provider): array
    {
        $variants = [];
        foreach ($provider->hostVariants as $region => $host) {
            $variants[$this->wording->raw((string) $region)] = $host;
        }

        return $variants;
    }

    /** @return list<array{severity: string, text: string}> */
    private function issues(): array
    {
        $issues = [];
        foreach ($this->settings->resolve()->issues as $issue) {
            $issues[] = ['severity' => $issue->severity, 'text' => $this->wording->issue($issue)];
        }

        return $issues;
    }
}

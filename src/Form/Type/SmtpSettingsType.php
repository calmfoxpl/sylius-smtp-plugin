<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Form\Type;

use Calmfox\SyliusSmtpPlugin\Core\Provider\ProviderCatalog;
use Calmfox\SyliusSmtpPlugin\Core\Settings\AuthMethod;
use Calmfox\SyliusSmtpPlugin\Core\Settings\Encryption;
use Calmfox\SyliusSmtpPlugin\Core\Settings\MailSettings;
use Calmfox\SyliusSmtpPlugin\Entity\SmtpSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The settings form.
 *
 * @extends AbstractType<SmtpSettings>
 *
 * The password is unmapped on purpose: an empty field means "leave the stored one alone", which
 * is what somebody editing a port and saving expects, and it also means the ciphertext is never
 * sent to a browser and back. The action encrypts whatever was typed.
 */
final class SmtpSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', CheckboxType::class, [
                'required' => false,
                'label' => 'calmfox_smtp.form.enabled',
                'help' => 'calmfox_smtp.form.enabled_help',
            ])
            ->add('provider', ChoiceType::class, [
                'choices' => $this->providers(),
                'label' => 'calmfox_smtp.form.provider',
                'help' => 'calmfox_smtp.form.provider_help',
            ])
            ->add('host', TextType::class, [
                'required' => false,
                'label' => 'calmfox_smtp.form.host',
                'help' => 'calmfox_smtp.form.host_help',
            ])
            ->add('port', IntegerType::class, [
                'required' => false,
                'label' => 'calmfox_smtp.form.port',
                'help' => 'calmfox_smtp.form.port_help',
                'constraints' => [new Assert\Range(min: 1, max: 65535)],
            ])
            ->add('encryption', ChoiceType::class, [
                'choices' => [
                    'calmfox_smtp.form.encryption_tls' => Encryption::TLS,
                    'calmfox_smtp.form.encryption_ssl' => Encryption::SSL,
                    'calmfox_smtp.form.encryption_none' => Encryption::NONE,
                ],
                'label' => 'calmfox_smtp.form.encryption',
                'help' => 'calmfox_smtp.form.encryption_help',
            ])
            ->add('auth', ChoiceType::class, [
                'choices' => [
                    'calmfox_smtp.form.auth_auto' => AuthMethod::AUTO,
                    'calmfox_smtp.form.auth_login' => AuthMethod::LOGIN,
                    'calmfox_smtp.form.auth_plain' => AuthMethod::PLAIN,
                    'calmfox_smtp.form.auth_crammd5' => AuthMethod::CRAM_MD5,
                    'calmfox_smtp.form.auth_none' => AuthMethod::NONE,
                ],
                'label' => 'calmfox_smtp.form.auth',
                'help' => 'calmfox_smtp.form.auth_help',
            ])
            ->add('username', TextType::class, [
                'required' => false,
                'label' => 'calmfox_smtp.form.username',
            ])
            ->add('plainPassword', PasswordType::class, [
                'required' => false,
                'mapped' => false,
                'label' => 'calmfox_smtp.form.password',
                'help' => 'calmfox_smtp.form.password_help',
                'attr' => ['autocomplete' => 'new-password'],
            ])
            ->add('timeout', IntegerType::class, [
                'label' => 'calmfox_smtp.form.timeout',
                'help' => 'calmfox_smtp.form.timeout_help',
                'constraints' => [new Assert\Range(min: MailSettings::MIN_TIMEOUT, max: MailSettings::MAX_TIMEOUT)],
            ])
            ->add('verifyCertificate', CheckboxType::class, [
                'required' => false,
                'label' => 'calmfox_smtp.form.verify_certificate',
                'help' => 'calmfox_smtp.form.verify_certificate_help',
            ])
            ->add('fromEmail', EmailType::class, [
                'required' => false,
                'label' => 'calmfox_smtp.form.from_email',
                'help' => 'calmfox_smtp.form.from_email_help',
            ])
            ->add('fromName', TextType::class, [
                'required' => false,
                'label' => 'calmfox_smtp.form.from_name',
            ])
            ->add('returnPath', EmailType::class, [
                'required' => false,
                'label' => 'calmfox_smtp.form.return_path',
                'help' => 'calmfox_smtp.form.return_path_help',
            ])
            ->add('alertRecipient', EmailType::class, [
                'required' => false,
                'label' => 'calmfox_smtp.form.alert_recipient',
                'help' => 'calmfox_smtp.form.alert_recipient_help',
            ])
            ->add('webhookUrl', UrlType::class, [
                'required' => false,
                'default_protocol' => 'https',
                'label' => 'calmfox_smtp.form.webhook_url',
                'help' => 'calmfox_smtp.form.webhook_url_help',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SmtpSettings::class,
            'translation_domain' => 'messages',
        ]);
    }

    /**
     * Brand names are not translated; only "a server of my own" is a phrase.
     *
     * @return array<string, string>
     */
    private function providers(): array
    {
        $choices = [];
        foreach (ProviderCatalog::all() as $provider) {
            $label = $provider->isCustom() ? 'calmfox_smtp.form.provider_custom' : $provider->label;
            $choices[$label] = $provider->id;
        }

        return $choices;
    }
}

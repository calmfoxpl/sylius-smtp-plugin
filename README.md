# Sylius SMTP Plugin

[![Build](https://github.com/calmfoxpl/sylius-smtp-plugin/actions/workflows/build.yml/badge.svg)](https://github.com/calmfoxpl/sylius-smtp-plugin/actions/workflows/build.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

SMTP for Sylius with ready-made settings for the popular providers, a log of what was sent, a
periodic check of the connection, and a warning in the panel the day the shop stops being able to
send.

The last part is the reason this plugin exists. Sending e-mail through a provider is a solved
problem; noticing that it has stopped is not. An API key is revoked, a colleague rotates a
password, a hosting company closes port 587, a provider retires a mechanism — and the shop carries
on taking orders and quietly sending nothing. The usual way a shopkeeper finds out is a customer
asking where their confirmation is, two days later.

## Features

- **Ready-made settings for fifteen providers:** Brevo, Mailgun, SendGrid, Postmark, Amazon SES,
  Mailjet, SMTP2GO, Resend, SparkPost, Mailchimp Transactional, Elastic Email, Zoho, Gmail and
  Google Workspace, Microsoft 365 and Outlook, and a server of your own. Picking one fills in the
  server, the port and the encryption — and, more usefully, says what *that provider* means by
  the two credential fields, which is different at every one of them and guessable at none:
  SendGrid wants the literal word `apikey` as the login, Postmark wants one token in both fields,
  Amazon wants credentials that look exactly like the AWS keys that will never work.
- **A health check that costs nothing:** it opens a connection, raises encryption, logs in and
  says goodbye — without sending a message. No quota is used, no inbox is touched, and the
  failures that actually happen are caught: an expired key, a rotated password, a blocked port, an
  untrusted certificate, a mechanism a provider has dropped.
- **A check on whether the mail will be believed.** The connection check proves the shop can hand
  a message to its provider; it cannot say whether a receiver will accept it. So the sender
  domain's own records are read — SPF, DKIM and DMARC — and the one question that matters is
  answered: does the domain actually authorise the provider this shop sends through? A shop that
  moved providers and never updated SPF sends perfectly happily for weeks while Gmail files
  everything under spam, and its own test message, sent to a colleague at the same domain,
  arrives beautifully. Nested records are followed one level, the way a receiver does, so an
  agency's record in the middle is not mistaken for a fault. The provider-to-record table was
  checked against live DNS rather than written from memory, and where a provider mints a DKIM
  selector per domain — Amazon and Postmark among them — nothing is claimed at all, because the
  absence of a name we guessed would prove nothing.
- **A suggestion from DNS while the settings are empty.** A submission service published under
  RFC 6186, and the provider the domain's own SPF says it is already set up for — offered as a
  question, never filled in, and never carrying a credential, because DNS can say which service
  a domain uses and never who the shop is at that service.
- **A warning that reaches somebody.** Four channels, because on a shop whose mail is down one of
  them is broken by definition:
  - a notice on the dashboard and a mark in the menu — and a separate, amber one for the domain,
    because "cannot send" and "may not be arriving" are different jobs on different clocks and
    one colour for both would get both ignored,
  - an e-mail, sent deliberately through the server's own local mail command rather than the
    provider that is not working,
  - a webhook for Slack, Teams, Discord or a monitor of your own,
  - a line in the application log and a non-zero exit code from `bin/console calmfox:smtp:health`.
- **Quiet when it should be.** A single failed check is a note in the panel, not a phone call:
  mail servers have bad minutes. A refused password is reported at once, because that will not fix
  itself. A problem is reported once, again if its reason changes, once more after a long silence —
  and when it is over, that is reported too.
- **Failures the check cannot see are caught anyway.** Every real message the shop sends is
  watched, including the ones sent asynchronously through Messenger. Three failures inside half an
  hour is a broken shop whatever a connection test says, and a message that goes out clears the
  record, because that is the strongest evidence there is.
- **A log with a resend button.** Sender, recipients, subject, the server's answer — and, for
  failures, the message itself, so the eleven order confirmations that did not go out between nine
  and eleven can be sent once the key is fixed.
- **Careful with the credential.** The password is encrypted with libsodium, keyed by the
  application secret, so a database dump does not come with the shop's sending credentials in it.
  It is never written to the log, the alert, the webhook payload or the application log, and the
  AUTH lines of a failed conversation are redacted before anything is stored.
- **Careful with customer data.** Outgoing mail is customer correspondence. Bodies are not stored
  unless asked for, retention is 30 days by default, and old entries are deleted whether the log is
  switched on or not.
- **Nothing changes until you ask.** With the plugin off, or configured too incompletely to send,
  the application's own `MAILER_DSN` does exactly what it did before. Uninstalling is a setting
  rather than a deployment.
- **Settings where they belong.** Credentials in the panel, so a rotated API key does not need a
  deployment — or pinned in `config/packages/` with `locked: true` for a shop whose deployments are
  managed, and then the panel shows them read-only instead of offering fields that do nothing.
- **Translations:** English and Polish.

## What it does not do

Worth saying plainly, because a plugin that overstates this is worse than none:

- **It cannot tell you a message was delivered.** The check proves a connection and a login. Only
  a real message proves delivery, and only the mailbox it lands in can tell you whether it reached
  the inbox or the spam folder. The panel has a button for sending one, and says as much.
- **It reads deliverability records; it does not fix them.** SPF, DKIM and DMARC live on your
  domain and are changed where that domain is hosted. The plugin reads them, says plainly when
  they do not authorise the provider in use, and stops there — it will not edit anybody's DNS.
- **Reading them is not the whole of deliverability.** Reputation, complaint rates, content
  filtering and whatever a provider's own dashboard knows are all outside what DNS can answer. A
  domain with perfect records can still be filtered, and the panel says what it checked rather
  than implying it checked everything.
- **It does not queue.** A message that cannot be sent fails, exactly as it would without the
  plugin — and is then in the log, where it can be sent again.
- **It does not use provider HTTP APIs.** One SMTP path, which every provider in the list
  supports, and one place for failures to be diagnosed.

## Requirements

| | Version |
|---|---|
| Sylius | 2.1 |
| PHP | 8.2, 8.3, 8.4 (with `sodium`) |
| Symfony | 7.4 |

## Installation

```bash
composer require calmfox/sylius-smtp-plugin
```

Register the plugin in `config/bundles.php`:

```php
Calmfox\SyliusSmtpPlugin\CalmfoxSyliusSmtpPlugin::class => ['all' => true],
```

Import its configuration — this is what puts the warning on the dashboard — in
`config/packages/calmfox_sylius_smtp.yaml`:

```yaml
imports:
    - { resource: '@CalmfoxSyliusSmtpPlugin/config/config.yaml' }
```

Import its routes, under the admin prefix so its pages are behind the admin firewall — in
`config/routes/calmfox_smtp.yaml`:

```yaml
calmfox_smtp_admin:
    resource: '@CalmfoxSyliusSmtpPlugin/config/routes/admin.yaml'
    prefix: /admin
```

Create the tables:

```bash
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Then *Configuration → E-mail sending*: pick a provider, paste the two credentials, save, and press
**Check now** on the health report.

## Making the check periodic

The check runs when it is asked to. One line of cron, or a Symfony Scheduler entry, is what makes
it periodic:

```
*/15 * * * * cd /path/to/shop && bin/console calmfox:smtp:health --quiet
41 */6 * * * cd /path/to/shop && bin/console calmfox:smtp:dns --quiet
0 3 * * *    cd /path/to/shop && bin/console calmfox:smtp:log:prune --quiet
```

Every quarter of an hour is about as long as anybody wants to be unable to send without knowing
it, and the check is cheap: one connection, one login, no message.

## Settings in a file

Everything is optional; anything set here becomes the default the panel starts from. With
`locked: true` the file is the last word and the panel goes read-only — the right arrangement for a
shop whose deployments are managed.

```yaml
# config/packages/calmfox_sylius_smtp.yaml
calmfox_sylius_smtp:
    enabled: true
    server:
        provider: 'brevo'
        username: '%env(SMTP_USER)%'
        password: '%env(SMTP_PASSWORD)%'
    health:
        failed_checks: 2        # failed checks before a transient problem is called a failure
        send_failures: 3        # real messages that have to fail before the shop is called broken
        send_window: 1800       # the window those failures have to fall inside
        repeat_after: 21600     # silence before the same problem is reported a second time
    alerts:
        recipient: 'shop@example.com'
        webhook: true
        webhook_url: '%env(SMTP_ALERT_WEBHOOK)%'
    dns:
        enabled: true           # read what the sending domain publishes: SPF, DKIM, DMARC
    log:
        store_body: true        # only for failures, and only what makes a resend possible
        retention_days: 30
```

A password in the file is read as-is — put it in an environment variable. A password typed into the
panel is encrypted with the application secret.

## The console

```bash
bin/console calmfox:smtp:health          # 0 works, 1 a problem that may pass, 2 broken, 3 not configured
bin/console calmfox:smtp:health --json   # the whole verdict, credentials excluded
bin/console calmfox:smtp:health --force  # check now rather than reusing a recent verdict
bin/console calmfox:smtp:dns             # what the sender domain publishes, and whether it authorises the provider
bin/console calmfox:smtp:test you@example.com
bin/console calmfox:smtp:log:prune --days=7
```

The exit code is the interface for Zabbix, for a systemd timer, and for a deployment script that
would rather not go live with a shop that cannot e-mail anybody.

## Tests

The part with the decisions in it — the provider presets, the settings they resolve to, the SMTP
conversation, the reading it gives a shopkeeper, the choice to warn or stay quiet — knows nothing
about Sylius and is tested against written-down transcripts of what real mail servers say,
including the ones nobody can arrange on demand: a refused password, a handshake that fails
halfway, a server that answers in HTTP. The same suite checks that the container, the routes, the
templates and the two translation files agree with each other, and it runs without Sylius
installed:

```bash
vendor/bin/phpunit -c phpunit.xml.dist
```

## The same thing for Magento

[calmfox/magento-smtp-module](https://github.com/calmfoxpl/magento-smtp-module) is this plugin's
counterpart for Magento 2: the same provider catalogue, the same health check and the same
wording, built on Magento's own transport and configuration.

## Licence

MIT. See [LICENSE](LICENSE).

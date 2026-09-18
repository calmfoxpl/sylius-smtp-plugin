# Changelog

All notable changes to this project are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- A check of what the sender domain publishes: SPF, DKIM and DMARC, read by
  `calmfox:smtp:dns` and on a button in the panel, answering the one question a connection check
  cannot — whether the domain authorises the provider the shop sends through. Nested records are
  followed one level, as a receiver does; two records at once, a cost over the ten-lookup limit
  and a `+all` are reported as the hard faults they are; DKIM is only judged where the provider
  uses a selector that is the same for every customer.
- A separate, amber notice for that on the dashboard, kept apart from the red "cannot send" one
  so that neither teaches an administrator to ignore the other.
- A suggestion from DNS while the settings are still empty: a submission service published under
  RFC 6186, and the provider a domain's own SPF says it is set up for — offered as a question,
  never filled in, and never carrying a credential.
- `calmfox_sylius_smtp.dns.enabled` to turn the whole of it off.

### Fixed

- The plugin's pages now sit inside the admin panel — side menu, top bar, the panel's own form
  styling and flash messages. Sylius 2's base layout has none of these, so the pages rendered
  bare, with no way back to the rest of the panel.
- "No encryption" chosen in the panel, or set in the file, is no longer quietly turned back into
  STARTTLS. The empty string that stands for it was being read as a field nobody had filled in,
  which made a local relay on port 25 or a development mail catcher impossible to use.

### Changed

- The installation steps now include importing the plugin's configuration, without which the
  warning on the dashboard is never shown.

## [1.0.0] - 2026-09-18

### Added

- SMTP sending for Sylius as a decorator over Symfony Mailer's default transport, so messages sent
  synchronously and through Messenger are both covered, and the application's own `MAILER_DSN`
  still works whenever the plugin is off or configured too incompletely to send.
- Ready-made settings for fifteen providers, each with the server, port and encryption filled in
  and the provider's own meaning of the two credential fields explained next to the fields —
  including the logins that are pinned (SendGrid, Resend, SparkPost) and the token Postmark wants
  twice.
- A health check that connects, raises encryption and logs in without sending a message, reporting
  the stage it reached, the cause, the server's own words and how long it took.
- A diagnosis layer that turns operating system, TLS and provider errors into one named cause and
  one provider-specific thing to check.
- Warnings through four channels — a notice on the dashboard and a mark in the menu, an e-mail sent
  via the server's local mail command, a webhook, and the log plus the exit code of
  `calmfox:smtp:health` — spoken once, again on a changed reason, once more after a long silence,
  and once when it is over.
- Watching of real sends, so a shop whose messages are failing is reported as broken whatever a
  connection test says, and a successful send clears the record.
- A log of what was sent, with subjects decoded, credentials redacted, optional message bodies, a
  resend button for failures, batched pruning and a retention that applies whether or not the log
  is switched on.
- Settings in the panel with the password encrypted by libsodium, or pinned in
  `config/packages/calmfox_sylius_smtp.yaml` with `locked: true`.
- The console commands `calmfox:smtp:health`, `calmfox:smtp:test` and `calmfox:smtp:log:prune`.
- English and Polish translations, with a test that keeps them complete.

[Unreleased]: https://github.com/calmfoxpl/sylius-smtp-plugin/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/calmfoxpl/sylius-smtp-plugin/releases/tag/v1.0.0

# Changelog

All notable changes to this project are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

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

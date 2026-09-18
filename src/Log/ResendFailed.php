<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Log;

/** A resend that did not happen, carrying a sentence already fit to show somebody. */
final class ResendFailed extends \RuntimeException
{
}

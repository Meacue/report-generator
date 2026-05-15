<?php

declare(strict_types=1);

namespace App\Domain\Settings\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when the supplied Bitrix24 webhook URL does not match the canonical
 * `https://<portal>/rest/<user_id>/<api_key>/` shape or fails sanity checks.
 *
 * Extends a plain SPL exception so the parser can be tested without booting
 * the Laravel container.
 */
final class InvalidWebhookUrlException extends InvalidArgumentException
{
}

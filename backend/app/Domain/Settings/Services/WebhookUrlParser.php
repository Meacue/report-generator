<?php

declare(strict_types=1);

namespace App\Domain\Settings\Services;

use App\Domain\Settings\DTOs\ParsedWebhook;
use App\Domain\Settings\Exceptions\InvalidWebhookUrlException;

/**
 * Parses a Bitrix24 incoming webhook URL into its three constituent parts.
 *
 * Expected canonical form (trailing slash optional):
 *   https://<portal-host>[:<port>]/rest/<user_id>/<api_key>/
 *
 * Anything else (extra path segments, query strings, missing parts, bad
 * characters in the api_key) is rejected with {@see InvalidWebhookUrlException}.
 */
final class WebhookUrlParser
{
    /**
     * Regex describing the canonical webhook URL shape.
     *
     * Groups:
     *   - rest: scheme + host + optional port + `/rest`
     *   - user: numeric Bitrix24 operator id
     *   - key:  alphanumeric webhook secret
     */
    private const string PATTERN = '#^(?<rest>https?://[^/\s]+(?::\d+)?/rest)/(?<user>\d+)/(?<key>[A-Za-z0-9]+)/?$#';

    private const string EXPECTED_SHAPE_MESSAGE = 'Webhook URL must match https://<portal>.bitrix24.ru/rest/<user_id>/<api_key>/';

    /**
     * Minimum length of the api_key segment. Bitrix24 webhooks are typically
     * 32 alphanumeric characters; 8 is a defensive lower bound that still
     * rejects obvious garbage like `/rest/1/abc/`.
     */
    private const int MIN_API_KEY_LENGTH = 8;

    public function parse(string $url): ParsedWebhook
    {
        $trimmed = trim($url);

        if ($trimmed === '') {
            throw new InvalidWebhookUrlException(self::EXPECTED_SHAPE_MESSAGE);
        }

        if (preg_match(self::PATTERN, $trimmed, $matches) !== 1) {
            throw new InvalidWebhookUrlException(self::EXPECTED_SHAPE_MESSAGE);
        }

        $restUrl = $matches['rest'];
        $userId = $matches['user'];
        $apiKey = $matches['key'];

        if ((int) $userId <= 0) {
            throw new InvalidWebhookUrlException(self::EXPECTED_SHAPE_MESSAGE);
        }

        if (strlen($apiKey) < self::MIN_API_KEY_LENGTH) {
            throw new InvalidWebhookUrlException(self::EXPECTED_SHAPE_MESSAGE);
        }

        return new ParsedWebhook(
            restUrl: $restUrl,
            userId: $userId,
            apiKey: $apiKey,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Settings\DTOs;

/**
 * Immutable representation of a Bitrix24 incoming webhook URL split into
 * the three parts our infrastructure layer needs to assemble REST endpoints:
 * the portal REST root, the operator user_id, and the secret api_key.
 */
final readonly class ParsedWebhook
{
    public function __construct(
        public string $restUrl,
        public string $userId,
        public string $apiKey,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Settings;

use App\Domain\Settings\Exceptions\InvalidWebhookUrlException;
use App\Domain\Settings\Services\WebhookUrlParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WebhookUrlParserTest extends TestCase
{
    private WebhookUrlParser $parser;

    public function test_parses_canonical_webhook_with_trailing_slash(): void
    {
        // GIVEN: a canonical webhook URL with trailing slash
        $url = 'https://aerokod.bitrix24.ru/rest/250/hcdim7lf2ogndgu3/';

        // WHEN: the parser processes it
        $result = $this->parser->parse($url);

        // THEN: all three parts are extracted correctly
        $this->assertSame('https://aerokod.bitrix24.ru/rest', $result->restUrl);
        $this->assertSame('250', $result->userId);
        $this->assertSame('hcdim7lf2ogndgu3', $result->apiKey);
    }

    public function test_parses_canonical_webhook_without_trailing_slash(): void
    {
        // GIVEN: a canonical webhook URL without trailing slash
        $url = 'https://aerokod.bitrix24.ru/rest/250/hcdim7lf2ogndgu3';

        // WHEN: the parser processes it
        $result = $this->parser->parse($url);

        // THEN: all three parts are extracted correctly
        $this->assertSame('https://aerokod.bitrix24.ru/rest', $result->restUrl);
        $this->assertSame('250', $result->userId);
        $this->assertSame('hcdim7lf2ogndgu3', $result->apiKey);
    }

    public function test_parses_http_scheme(): void
    {
        // GIVEN: a webhook URL using plain http (not https)
        $url = 'http://x.bitrix24.ru/rest/1/aaaaaaaa/';

        // WHEN: the parser processes it
        $result = $this->parser->parse($url);

        // THEN: restUrl preserves http scheme
        $this->assertSame('http://x.bitrix24.ru/rest', $result->restUrl);
        $this->assertSame('1', $result->userId);
        $this->assertSame('aaaaaaaa', $result->apiKey);
    }

    public function test_parses_custom_port(): void
    {
        // GIVEN: a webhook URL with a custom port
        $url = 'https://portal.bitrix24.ru:8443/rest/1/aaaaaaaa/';

        // WHEN: the parser processes it
        $result = $this->parser->parse($url);

        // THEN: the port is included in the restUrl
        $this->assertSame('https://portal.bitrix24.ru:8443/rest', $result->restUrl);
        $this->assertSame('1', $result->userId);
        $this->assertSame('aaaaaaaa', $result->apiKey);
    }

    public function test_trims_whitespace(): void
    {
        // GIVEN: a valid webhook URL padded with whitespace
        $url = '  https://x.bitrix24.ru/rest/1/aaaaaaaa/  ';

        // WHEN: the parser processes it
        $result = $this->parser->parse($url);

        // THEN: parsing succeeds and restUrl is clean
        $this->assertSame('https://x.bitrix24.ru/rest', $result->restUrl);
        $this->assertSame('1', $result->userId);
        $this->assertSame('aaaaaaaa', $result->apiKey);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidUrlProvider(): array
    {
        return [
            'empty string'          => [''],
            'whitespace only'       => ['   '],
            'garbage input'         => ['foo'],
            'missing api key'       => ['https://x.bitrix24.ru/rest/250/'],
            'missing user id'       => ['https://x.bitrix24.ru/rest/hcdim7lf2ogndgu3/'],
            'non-numeric user id'   => ['https://x.bitrix24.ru/rest/abc/aaaaaaaa/'],
            'api key too short'     => ['https://x.bitrix24.ru/rest/1/short/'],
            'user id zero'          => ['https://x.bitrix24.ru/rest/0/aaaaaaaa/'],
            'extra path segments'   => ['https://x.bitrix24.ru/rest/1/aaaaaaaa/extra/'],
            'query string appended' => ['https://x.bitrix24.ru/rest/1/aaaaaaaa?foo=1'],
        ];
    }

    #[DataProvider('invalidUrlProvider')]
    public function test_rejects_invalid_url(string $url): void
    {
        // GIVEN / WHEN / THEN: invalid input always throws InvalidWebhookUrlException
        $this->expectException(InvalidWebhookUrlException::class);

        $this->parser->parse($url);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new WebhookUrlParser();
    }
}

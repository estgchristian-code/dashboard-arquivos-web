<?php

namespace Tests\Feature\Exports;

use Tests\Feature\Imports\ImportTestCase;

class ExportEndpointsTest extends ImportTestCase
{
    private const OWNER_TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const OTHER_TOKEN = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function test_csv_endpoint_downloads_the_converted_spreadsheet(): void
    {
        $import = $this->importFixture('valid.xlsx', token: self::OWNER_TOKEN);

        $response = $this->withSession($this->sessionFor(self::OWNER_TOKEN))
            ->get(route('exports.csv', ['importId' => $import->id]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Caneta', $content);
    }

    public function test_json_endpoint_returns_the_rows_and_analysis(): void
    {
        $import = $this->importFixture('valid.xlsx', token: self::OWNER_TOKEN);

        $response = $this->withSession($this->sessionFor(self::OWNER_TOKEN))
            ->get(route('exports.json', ['importId' => $import->id]));

        $response->assertOk();
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));

        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($import->id, $data['import']['id']);
        $this->assertNotEmpty($data['data']['rows']);
        $this->assertSame($import->rowCount, $data['import']['row_count']);
    }

    public function test_pdf_endpoint_generates_a_real_pdf_document(): void
    {
        $import = $this->importFixture('valid.xlsx', token: self::OWNER_TOKEN);

        $response = $this->withSession($this->sessionFor(self::OWNER_TOKEN))
            ->get(route('exports.pdf', ['importId' => $import->id]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_png_endpoint_returns_the_chart_image_for_the_owner(): void
    {
        $import = $this->importFixture('valid.xlsx', token: self::OWNER_TOKEN);
        $binary = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lK3F7wAAAABJRU5ErkJggg==',
            true,
        );

        $response = $this->withSession($this->sessionFor(self::OWNER_TOKEN))
            ->post(route('exports.png', ['importId' => $import->id]), [
                'image' => 'data:image/png;base64,'.base64_encode($binary),
            ]);

        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertSame($binary, $response->getContent());
    }

    public function test_png_endpoint_rejects_a_different_visitor(): void
    {
        $import = $this->importFixture('valid.xlsx', token: self::OTHER_TOKEN);

        $response = $this->withSession($this->sessionFor(self::OWNER_TOKEN))
            ->post(route('exports.png', ['importId' => $import->id]), [
                'image' => 'data:image/png;base64,'.base64_encode('not-really-a-png'),
            ]);

        $response->assertForbidden();
    }

    /**
     * @return array<string, string>
     */
    private function sessionFor(string $token): array
    {
        return ['datacanvas.visitor_token' => $token];
    }
}

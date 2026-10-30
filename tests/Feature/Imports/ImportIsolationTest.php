<?php

namespace Tests\Feature\Imports;

use App\Services\Imports\ImportRepository;
use App\Services\Imports\VisitorIdentity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ImportIsolationTest extends ImportTestCase
{
    public function test_another_visitor_cannot_read_or_delete_the_import(): void
    {
        $import = $this->importFixture('valid.csv', token: 'visitante-a');
        $repository = app(ImportRepository::class);

        $this->assertNotNull($repository->findOwned($import->id, $this->ownerHash('visitante-a')));
        $this->assertNull($repository->findOwned($import->id, $this->ownerHash('visitante-b')));
        $this->assertFalse($repository->deleteOwned($import->id, $this->ownerHash('visitante-b')));
        $this->assertTrue(Storage::disk('imports')->exists($import->id.'/metadata.json'));

        $this->assertTrue($repository->deleteOwned($import->id, $this->ownerHash('visitante-a')));
        $this->assertFalse(Storage::disk('imports')->exists($import->id.'/metadata.json'));
    }

    public function test_unknown_or_traversal_identifiers_return_null(): void
    {
        $repository = app(ImportRepository::class);
        $ownerHash = $this->ownerHash();

        $this->assertNull($repository->findOwned(str_repeat('a', 32), $ownerHash));
        $this->assertNull($repository->findOwned('../../etc/passwd', $ownerHash));
        $this->assertNull($repository->findOwned('..\\..\\windows\\win.ini', $ownerHash));
        $this->assertNull($repository->findOwned('', $ownerHash));
    }

    public function test_expired_import_is_not_returned_and_is_deleted_on_access(): void
    {
        config()->set('datacanvas.retention_hours', 1);

        Carbon::setTestNow('2026-01-10 10:00:00');
        $import = $this->importFixture('valid.csv');

        Carbon::setTestNow('2026-01-10 12:00:00');

        $this->assertNull(app(ImportRepository::class)->findOwned($import->id, $this->ownerHash()));
        $this->assertFalse(Storage::disk('imports')->exists($import->id.'/metadata.json'));
    }

    public function test_visitor_token_is_random_per_session_and_never_stored_in_clear(): void
    {
        $identity = app(VisitorIdentity::class);
        $firstToken = $identity->token();
        $firstHash = $identity->ownerHash();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $firstToken);
        $this->assertSame($firstHash, $identity->ownerHash());

        session()->flush();

        $secondToken = app(VisitorIdentity::class)->token();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $secondToken);
        $this->assertNotSame($firstToken, $secondToken);
        $this->assertNotSame($firstHash, app(VisitorIdentity::class)->ownerHash());

        $import = $this->importFixture('valid.csv');
        $metadata = Storage::disk('imports')->get($import->id.'/metadata.json');

        $this->assertStringNotContainsString($secondToken, $metadata);
        $this->assertStringContainsString($this->ownerHash(), $metadata);
    }
}

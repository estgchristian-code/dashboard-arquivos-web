<?php

namespace App\Http\Controllers\Exports;

use App\Http\Controllers\Controller;
use App\Services\Exports\ExportService;
use App\Services\Imports\ImportRepository;
use App\Services\Imports\VisitorIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExportController extends Controller
{
    public function csv(Request $request, string $importId)
    {
        $this->assertValidImportId($importId);

        return app(ExportService::class)->exportCsv($importId);
    }

    public function json(Request $request, string $importId)
    {
        $this->assertValidImportId($importId);

        $result = app(ExportService::class)->exportJson($importId);

        return response($result['content'], 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$result['filename'].'"',
        ]);
    }

    public function pdf(Request $request, string $importId)
    {
        $this->assertValidImportId($importId);

        $result = app(ExportService::class)->exportPdf($importId);

        return response($result['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$result['filename'].'"',
        ]);
    }

    public function png(Request $request, string $importId)
    {
        $this->assertValidImportId($importId);

        $imageData = $request->input('image');
        if (! is_string($imageData) || $imageData === '') {
            abort(422, 'Imagem não fornecida.');
        }

        // Remove data URI prefix if present
        if (str_contains($imageData, ',')) {
            $imageData = explode(',', $imageData)[1];
        }

        $decoded = base64_decode($imageData, true);
        if ($decoded === false) {
            abort(422, 'Imagem inválida.');
        }

        // Basic size limit
        if (strlen($decoded) > 5 * 1024 * 1024) {
            abort(422, 'Imagem muito grande.');
        }

        $import = app(ImportRepository::class)->findOwned($importId, app(VisitorIdentity::class)->ownerHash());
        if ($import === null) {
            abort(403, 'Não autorizado.');
        }

        $filename = 'grafico-'.date('YmdHis').'.png';

        return response($decoded, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function assertValidImportId(string $importId): void
    {
        Validator::make(['importId' => $importId], [
            'importId' => ['string', 'regex:/^[a-f0-9]{32}$/'],
        ])->validate();
    }
}

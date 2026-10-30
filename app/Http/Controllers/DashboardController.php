<?php

namespace App\Http\Controllers;

use App\Services\Analysis\AnalysisService;
use App\Services\Imports\ImportRepository;
use App\Services\Imports\VisitorIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const SESSION_KEY = 'datacanvas.current_import';

    public function show(?string $importId = null): View|RedirectResponse
    {
        if ($importId === null) {
            $importId = session(self::SESSION_KEY);
        }

        if (! is_string($importId)) {
            return redirect()->route('home');
        }

        $identity = app(VisitorIdentity::class);
        $repository = app(ImportRepository::class);
        $stored = $repository->findOwned($importId, $identity->ownerHash());

        if ($stored === null) {
            session()->forget(self::SESSION_KEY);

            return redirect()->route('home');
        }

        session([self::SESSION_KEY => $stored->id]);

        // Ensure analysis exists
        app(AnalysisService::class)->analyzeForOwner($stored->id, $identity->ownerHash());

        return view('dashboard', ['importId' => $stored->id]);
    }
}

<?php

namespace App\Services\Analysis;

use App\Services\Analysis\Data\ImportAnalysis;
use App\Services\Imports\ImportRepository;

final class AnalysisService
{
    public function __construct(
        private readonly DataAnalyzer $analyzer,
        private readonly ImportRepository $repository,
    ) {}

    public function analyzeForOwner(string $importId, string $ownerHash): ImportAnalysis
    {
        // Check ownership
        $import = $this->repository->findOwned($importId, $ownerHash);
        if ($import === null) {
            throw new \InvalidArgumentException('Importação não encontrada ou não autorizada.');
        }

        // Return cached if exists
        $cached = $this->analyzer->getStoredAnalysis($importId, $ownerHash);
        if ($cached !== null) {
            return $cached;
        }

        return $this->analyzer->analyze($importId, $ownerHash);
    }

    public function getAnalysis(string $importId, string $ownerHash): ?ImportAnalysis
    {
        $import = $this->repository->findOwned($importId, $ownerHash);
        if ($import === null) {
            return null;
        }

        return $this->analyzer->getStoredAnalysis($importId, $ownerHash);
    }

    public function forceAnalyze(string $importId, string $ownerHash): ImportAnalysis
    {
        $import = $this->repository->findOwned($importId, $ownerHash);
        if ($import === null) {
            throw new \InvalidArgumentException('Importação não encontrada ou não autorizada.');
        }

        return $this->analyzer->analyze($importId, $ownerHash);
    }
}

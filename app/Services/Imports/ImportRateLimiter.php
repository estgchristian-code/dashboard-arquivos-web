<?php

namespace App\Services\Imports;

use App\Exceptions\ImportException;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Limita tentativas de importação por visitante anônimo.
 *
 * O identificador é o hash do token de sessão, nunca o IP, para não
 * registrar dados pessoais. As janelas são independentes.
 */
final class ImportRateLimiter
{
    private const PREFIX = 'datacanvas-import';

    public function ensureAllowed(string $ownerHash): void
    {
        if (! (bool) config('datacanvas.rate_limit.enabled', true)) {
            return;
        }

        $perMinute = max(1, (int) config('datacanvas.rate_limit.per_minute', 5));
        $perHour = max(1, (int) config('datacanvas.rate_limit.per_hour', 20));

        $minuteKey = self::PREFIX.':minute:'.$ownerHash;
        $hourKey = self::PREFIX.':hour:'.$ownerHash;

        if (RateLimiter::tooManyAttempts($minuteKey, $perMinute)) {
            throw ImportException::rateLimited(RateLimiter::availableIn($minuteKey));
        }

        if (RateLimiter::tooManyAttempts($hourKey, $perHour)) {
            throw ImportException::rateLimited(RateLimiter::availableIn($hourKey));
        }

        RateLimiter::hit($minuteKey, 60);
        RateLimiter::hit($hourKey, 3600);
    }
}

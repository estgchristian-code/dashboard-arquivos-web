<?php

namespace App\Services\Imports;

use Illuminate\Contracts\Session\Session;

/**
 * Identidade anônima e efêmera do visitante.
 *
 * O token aleatório fica apenas na sessão do servidor. O hash é o que
 * identifica o proprietário de cada importação, sem guardar o token.
 */
final class VisitorIdentity
{
    private const SESSION_KEY = 'datacanvas.visitor_token';

    public function __construct(private readonly Session $session) {}

    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);

        if (! is_string($token) || preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->put(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public function ownerHash(): string
    {
        return hash('sha256', $this->token());
    }
}

<?php

namespace App\Services\StoreIntegration\DTO;

use DateTimeInterface;

/**
 * OAuth credentials returned from token exchange.
 */
readonly class OAuthCredentials
{
    public function __construct(
        public string $accessToken,
        public array $scopes,
        public ?DateTimeInterface $expiresAt = null,
        public ?string $refreshToken = null,
        public array $metadata = [],
    ) {}
}

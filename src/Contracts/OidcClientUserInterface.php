<?php

namespace Admin9\OidcClient\Contracts;

interface OidcClientUserInterface
{
    /**
     * Get the OIDC subject identifier for this user.
     */
    public function getOidcIdentifier(): ?string;

    /**
     * Get the stored Auth Server refresh token.
     */
    public function getOidcRefreshToken(): ?string;

    /**
     * Set the Auth Server refresh token.
     */
    public function setOidcRefreshToken(?string $refreshToken): void;
}

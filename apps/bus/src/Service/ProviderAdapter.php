<?php
declare(strict_types=1);

namespace Marketplace\Bus\Service;

interface ProviderAdapter
{
    public function providerKey(): string;

    /** @return array<\Marketplace\Bus\Domain\NormalizedProduct> */
    public function normalize(array $payload): array;
}


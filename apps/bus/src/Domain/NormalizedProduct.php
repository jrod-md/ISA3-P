<?php
declare(strict_types=1);

namespace Marketplace\Bus\Domain;

final class NormalizedProduct
{
    public function __construct(
        public readonly string $provider,
        public readonly string $externalId,
        public readonly string $title,
        public readonly ?string $description,
        public readonly string $category,
        public readonly ?string $brand,
        public readonly float $price,
        public readonly string $currency,
        public readonly int $stock,
    ) {
    }

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'external_id' => $this->externalId,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'brand' => $this->brand,
            'price' => $this->price,
            'currency' => $this->currency,
            'stock' => $this->stock,
        ];
    }
}


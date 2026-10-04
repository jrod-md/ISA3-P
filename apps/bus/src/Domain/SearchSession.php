<?php
declare(strict_types=1);

namespace Marketplace\Bus\Domain;

use Marketplace\Shared\Support\Uuid;

final class SearchSession
{
    private function __construct(
        public readonly string $id,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $expiresAt,
        public readonly int $ttlSeconds,
    ) {
    }

    public static function create(int $ttlSeconds): self
    {
        $created = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return new self(Uuid::v4(), $created, $created->modify("+{$ttlSeconds} seconds"), $ttlSeconds);
    }

    public function createdIso(): string
    {
        return $this->createdAt->format('Y-m-d\TH:i:s.u\Z');
    }

    public function expiresIso(): string
    {
        return $this->expiresAt->format('Y-m-d\TH:i:s.u\Z');
    }
}


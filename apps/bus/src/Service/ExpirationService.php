<?php
declare(strict_types=1);

namespace Marketplace\Bus\Service;

use Marketplace\Bus\Repository\PdoSearchRepository;

final class ExpirationService
{
    public function __construct(private readonly PdoSearchRepository $repository)
    {
    }

    public function cleanup(): int
    {
        return $this->repository->deleteExpired();
    }
}


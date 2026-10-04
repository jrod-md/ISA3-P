<?php
declare(strict_types=1);

namespace Marketplace\Bus\Service;

final class AllProvidersFailed extends \RuntimeException
{
    public function __construct(public readonly array $details)
    {
        parent::__construct('All selected providers failed');
    }
}


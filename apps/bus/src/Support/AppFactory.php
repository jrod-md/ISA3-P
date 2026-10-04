<?php
declare(strict_types=1);

namespace Marketplace\Bus\Support;

use Marketplace\Bus\Config\Config;
use Marketplace\Bus\Http\ProviderClient;
use Marketplace\Bus\Repository\PdoSearchRepository;
use Marketplace\Bus\Service\AlphaAdapter;
use Marketplace\Bus\Service\BetaAdapter;
use Marketplace\Bus\Service\GammaAdapter;
use Marketplace\Bus\Service\SearchOrchestrator;
use Marketplace\Shared\Support\Database;

final class AppFactory
{
    public static function repository(): PdoSearchRepository
    {
        return new PdoSearchRepository(Database::connect(Config::string('BUS_DB_NAME', 'bus_meta')));
    }

    public static function orchestrator(): SearchOrchestrator
    {
        return new SearchOrchestrator(
            self::repository(),
            new ProviderClient(),
            new AlphaAdapter(),
            new BetaAdapter(),
            new GammaAdapter(),
        );
    }
}


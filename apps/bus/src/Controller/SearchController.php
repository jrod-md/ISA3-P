<?php
declare(strict_types=1);

namespace Marketplace\Bus\Controller;

use Marketplace\Bus\Service\AllProvidersFailed;
use Marketplace\Bus\Support\AppFactory;
use Marketplace\Shared\Domain\SearchCriteria;
use Marketplace\Shared\Support\JsonResponse;
use Marketplace\Shared\Support\ValidationException;

final class SearchController
{
    public function search(): never
    {
        try {
            $criteria = SearchCriteria::fromQuery($_GET);
            JsonResponse::send(AppFactory::orchestrator()->search($criteria));
        } catch (ValidationException $exception) {
            JsonResponse::error($exception->errorCode, $exception->getMessage(), $exception->httpStatus, $exception->details);
        } catch (AllProvidersFailed $exception) {
            JsonResponse::error('ALL_PROVIDERS_FAILED', $exception->getMessage(), 502, $exception->details);
        } catch (\Throwable $exception) {
            error_log('Bus search error: ' . $exception->getMessage());
            JsonResponse::error('INTERNAL_ERROR', 'The search could not be completed', 500);
        }
    }

    public function cached(string $id): never
    {
        try {
            if (!preg_match('/^[0-9a-f-]{36}$/i', $id)) {
                JsonResponse::error('NOT_FOUND', 'Search not found', 404);
            }
            $search = AppFactory::repository()->find($id);
            if ($search === null) {
                JsonResponse::error('NOT_FOUND', 'Search not found or already destroyed', 404);
            }
            JsonResponse::send($search);
        } catch (\Throwable $exception) {
            error_log('Cached search error: ' . $exception->getMessage());
            JsonResponse::error('INTERNAL_ERROR', 'The cached search could not be read', 500);
        }
    }
}


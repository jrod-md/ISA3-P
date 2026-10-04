<?php
declare(strict_types=1);

namespace Marketplace\Bus\Controller;

use Marketplace\Bus\Support\AdminAuth;
use Marketplace\Bus\Support\AppFactory;
use Marketplace\Shared\Support\JsonResponse;

final class AdminController
{
    public function login(): never
    {
        $body = json_decode(file_get_contents('php://input') ?: '{}', true);
        if (!is_array($body)) {
            JsonResponse::error('MALFORMED_JSON', 'Request body must be JSON', 400);
        }
        if (!is_string($body['username'] ?? null) || !is_string($body['password'] ?? null)) {
            JsonResponse::error('INVALID_INPUT', 'Introduce usuario y contraseña', 400);
        }
        if (!AdminAuth::login($body['username'], $body['password'])) {
            JsonResponse::error('UNAUTHORIZED', 'Credenciales incorrectas', 401);
        }
        JsonResponse::send(['authenticated' => true, 'user' => AdminAuth::current(), 'csrf' => AdminAuth::csrf()]);
    }

    public function logout(): never
    {
        AdminAuth::logout();
        JsonResponse::send(['authenticated' => false]);
    }

    public function status(): never
    {
        JsonResponse::send(['authenticated' => AdminAuth::loggedIn(), 'user' => AdminAuth::current()]);
    }

    public function list(): never
    {
        $this->requireAuth();
        $searches = AppFactory::repository()->list();
        JsonResponse::send(['count' => count($searches), 'searches' => $searches]);
    }

    public function delete(string $id): never
    {
        $this->requireAuth();
        if (!preg_match('/^[0-9a-f-]{36}$/i', $id) || !AppFactory::repository()->delete($id)) {
            JsonResponse::error('NOT_FOUND', 'Search not found', 404);
        }
        JsonResponse::send(['deleted' => 1, 'search_id' => $id]);
    }

    public function deleteExpired(): never
    {
        $this->requireAuth();
        $deleted = AppFactory::repository()->deleteExpired();
        JsonResponse::send(['deleted' => $deleted]);
    }

    public function deleteAll(): never
    {
        $this->requireAuth();
        $deleted = AppFactory::repository()->deleteAll();
        JsonResponse::send(['deleted' => $deleted]);
    }

    private function requireAuth(): void
    {
        if (!AdminAuth::authenticated()) {
            if (AdminAuth::loggedIn()) { JsonResponse::error('FORBIDDEN', 'Esta operación requiere el rol Administrador', 403); }
            JsonResponse::error('UNAUTHORIZED', 'Administrator authentication required', 401);
        }
    }
}

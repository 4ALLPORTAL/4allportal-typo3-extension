<?php

declare(strict_types=1);

namespace Fourallportal\Fourallportalext\Middleware;

use Doctrine\DBAL\Exception;
use Fourallportal\Fourallportalext\Endpoint\AuthEndpoint;
use Fourallportal\Fourallportalext\Endpoint\FileEndpoint;
use Fourallportal\Fourallportalext\Service\TokenService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\NormalizedParams;

/**
 * Routes the 4ALLPORTAL connector API requests (`/api/auth`, `/api/files/...`)
 * to their endpoint handlers. All other requests pass through untouched.
 *
 * Registered before the base-redirect-resolver (see Configuration/RequestMiddlewares.php)
 * so language-base redirects never hijack API paths, and before the frontend
 * authentication middleware because access is controlled by a bearer token
 * instead of a frontend session.
 *
 * Adding a new endpoint: implement a handler method (see FileEndpoint), then
 * add a route match in dispatch() below - there is no annotation-based
 * auto-discovery anymore.
 */
final class ApiMiddleware implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly AuthEndpoint $authEndpoint,
        private readonly FileEndpoint $fileEndpoint,
        private readonly TokenService $tokenService,
    )
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $this->resolveApiPath($request);

        if ($path !== '/api/auth' && !str_starts_with($path, '/api/files')) {
            return $handler->handle($request);
        }

        try {
            return $this->dispatch($request->getMethod(), $path, $request);
        } catch (Throwable $e) {
            $this->logger?->error('Unhandled exception in file API: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse(
                [
                    'error' => true,
                    'message' => 'Internal server error',
                    'statusCode' => 500,
                ],
                500
            );
        }
    }

    /**
     * @throws Exception
     */
    private function dispatch(string $method, string $path, ServerRequestInterface $request): ResponseInterface
    {
        if ($path === '/api/auth') {
            return $method === 'POST' ? $this->authEndpoint->handle($request) : $this->notFound();
        }

        if (!$this->isAuthenticated($request)) {
            return $this->unauthorized();
        }

        if ($path === '/api/files') {
            return $method === 'POST' ? $this->fileEndpoint->upload($request) : $this->notFound();
        }

        if (preg_match('#^/api/files/(\d+)$#', $path, $matches) === 1) {
            $uid = (int)$matches[1];

            return match ($method) {
                'GET' => $this->fileEndpoint->get($uid),
                'DELETE' => $this->fileEndpoint->delete($uid),
                'PUT' => $this->fileEndpoint->updateMetadata($uid, $request),
                default => $this->notFound(),
            };
        }

        if ($method === 'POST' && preg_match('#^/api/files/(\d+)/(rename|move)$#', $path, $matches) === 1) {
            $uid = (int)$matches[1];

            return $matches[2] === 'rename'
                ? $this->fileEndpoint->rename($uid, $request)
                : $this->fileEndpoint->move($uid, $request);
        }

        return $this->notFound();
    }

    /**
     * Validates the bearer token and re-checks that the fe_user behind it is
     * still active, so disabled users are locked out despite stateless tokens.
     *
     * @throws Exception
     */
    private function isAuthenticated(ServerRequestInterface $request): bool
    {
        $header = $this->resolveAuthorizationHeader($request);

        if (!str_starts_with($header, 'Bearer ')) {
            return false;
        }

        $payload = $this->tokenService->validate(substr($header, strlen('Bearer ')));

        if ($payload === null) {
            return false;
        }

        $user = $this->authEndpoint->findActiveUser($payload['uid']);

        return $user !== null && $this->tokenService->matchesPassword($payload, (string)$user['password']);
    }

    /**
     * Reads the Authorization header, falling back to apache_request_headers().
     * Apache with mod_php exposes Authorization only there, not in $_SERVER, so
     * TYPO3 never puts it into the PSR-7 request - without this fallback bearer
     * auth silently fails on a common server setup.
     */
    private function resolveAuthorizationHeader(ServerRequestInterface $request): string
    {
        $header = $request->getHeaderLine('Authorization');

        if ($header === '' && function_exists('apache_request_headers')) {
            foreach (apache_request_headers() as $name => $value) {
                if (strtolower($name) === 'authorization') {
                    return $value;
                }
            }
        }

        return $header;
    }

    /**
     * Strips the site path prefix so route matching also works for
     * installations in a subdirectory.
     */
    private function resolveApiPath(ServerRequestInterface $request): string
    {
        $path = $request->getUri()->getPath();
        $normalizedParams = $request->getAttribute('normalizedParams');

        if ($normalizedParams instanceof NormalizedParams) {
            $sitePath = rtrim($normalizedParams->getSitePath(), '/');
            if ($sitePath !== '' && str_starts_with($path, $sitePath)) {
                $path = substr($path, strlen($sitePath));
            }
        }

        return $path;
    }

    private function unauthorized(): ResponseInterface
    {
        return new JsonResponse(
            [
                'status' => 403,
                'error' => 'Unauthorized. Please login.',
                'code' => '',
            ],
            403
        );
    }

    private function notFound(): ResponseInterface
    {
        return new JsonResponse(
            [
                'error' => true,
                'message' => 'Endpoint not found',
                'statusCode' => 404,
            ],
            404
        );
    }
}

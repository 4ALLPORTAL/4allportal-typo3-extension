<?php

declare(strict_types=1);

namespace Fourallportal\Fourallportalext\Endpoint;

use Doctrine\DBAL\Exception;
use Fourallportal\Fourallportalext\Service\TokenService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Crypto\PasswordHashing\InvalidPasswordHashException;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\EndTimeRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\StartTimeRestriction;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Handles `POST /api/auth`: verifies fe_user credentials and issues a bearer token.
 *
 * The response shape is part of the connector API contract and must not
 * change. The connector only reads the `token` field and treats it as an
 * opaque string.
 */
final class AuthEndpoint
{
    private const TABLE_FRONTEND_USERS = 'fe_users';

    public function __construct(
        private readonly TokenService        $tokenService,
        private readonly ConnectionPool      $connectionPool,
        private readonly PasswordHashFactory $passwordHashFactory,
    )
    {
    }

    /**
     * @throws Exception
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = json_decode((string)$request->getBody(), true);
        $username = is_array($body) ? (string)($body['username'] ?? '') : '';
        $password = is_array($body) ? (string)($body['password'] ?? '') : '';

        $user = $username !== '' && $password !== ''
            ? $this->verifyCredentials($username, $password)
            : null;

        if ($user === null) {
            return new JsonResponse(
                [
                    'status' => 403,
                    'error' => 'Invalid credentials.',
                    'code' => '',
                ],
                403
            );
        }

        $lastLogin = (int)($GLOBALS['EXEC_TIME'] ?? time());
        $this->updateLastLogin((int)$user['uid'], $lastLogin);

        return new JsonResponse([
            'uid' => (int)$user['uid'],
            'username' => (string)$user['username'],
            'usergroup' => GeneralUtility::intExplode(',', (string)$user['usergroup'], true),
            'token' => $this->tokenService->issue((int)$user['uid']),
            'first_name' => (string)($user['first_name'] ?? ''),
            'last_name' => (string)($user['last_name'] ?? ''),
            'lastlogin' => $lastLogin,
        ]);
    }

    /**
     * Records the successful login. TYPO3's regular authentication service
     * maintains `fe_users.lastlogin`, which this endpoint bypasses, so it is
     * updated here to keep the field meaningful.
     *
     * @throws Exception
     */
    private function updateLastLogin(int $uid, int $timestamp): void
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE_FRONTEND_USERS);
        $connection->update(
            self::TABLE_FRONTEND_USERS,
            ['lastlogin' => $timestamp],
            ['uid' => $uid]
        );
    }

    /**
     * Fetches an fe_user that is neither deleted, disabled nor outside its
     * start/end time window. Used per request to re-validate token holders,
     * so disabling a user takes effect immediately despite stateless tokens.
     *
     * @throws Exception
     */
    public function findActiveUser(int $uid): ?array
    {
        $queryBuilder = $this->createRestrictedQueryBuilder();
        $row = $queryBuilder
            ->select('*')
            ->from(self::TABLE_FRONTEND_USERS)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAssociative();

        return $row ?: null;
    }

    /**
     * @throws Exception
     */
    private function verifyCredentials(string $username, string $password): ?array
    {
        $queryBuilder = $this->createRestrictedQueryBuilder();
        $row = $queryBuilder
            ->select('*')
            ->from(self::TABLE_FRONTEND_USERS)
            ->where(
                $queryBuilder->expr()->eq('username', $queryBuilder->createNamedParameter($username))
            )
            ->executeQuery()
            ->fetchAssociative();

        if (!$row) {
            try {
                // burn comparable time as a real hash check to avoid username
                // enumeration via response timing (CWE-208, mirrors core mimicAuthUser())
                $this->passwordHashFactory->getDefaultHashInstance('FE')->getHashedPassword($password);
            } catch (InvalidPasswordHashException) {
                // timing equalization only - a broken hash config fails the real check too
            }
            return null;
        }

        try {
            $hashInstance = $this->passwordHashFactory->get((string)$row['password'], 'FE');
        } catch (InvalidPasswordHashException) {
            return null;
        }

        return $hashInstance->checkPassword($password, (string)$row['password']) ? $row : null;
    }

    /**
     * The default StartTime/EndTime restrictions rely on $GLOBALS['SIM_ACCESS_TIME'],
     * which is not populated this early in the middleware chain - so all
     * restrictions are added explicitly with the request time.
     */
    private function createRestrictedQueryBuilder(): QueryBuilder
    {
        $accessTime = (int)($GLOBALS['EXEC_TIME'] ?? time());
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_FRONTEND_USERS);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(HiddenRestriction::class))
            ->add(GeneralUtility::makeInstance(StartTimeRestriction::class, $accessTime))
            ->add(GeneralUtility::makeInstance(EndTimeRestriction::class, $accessTime));

        return $queryBuilder;
    }
}

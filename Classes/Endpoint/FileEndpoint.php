<?php

declare(strict_types=1);

namespace Fourallportal\Fourallportalext\Endpoint;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\Exception\ExistingTargetFileNameException;
use TYPO3\CMS\Core\Resource\Exception\ExistingTargetFolderException;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderWritePermissionsException;
use TYPO3\CMS\Core\Resource\File as ResourceFile;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;

/**
 * FAL operations behind the `/api/files` routes.
 *
 * Response payloads, error messages and status codes are part of the
 * connector API contract and must not change.
 */
final class FileEndpoint
{
    private const string ERROR_FILE_NOT_FOUND = 'File not found';
    private const string ERROR_UID_REQUIRED = 'uid is required';
    private const string ERROR_TARGET_PATH_REQUIRED = 'targetPath is required';

    public function __construct(
        private readonly ResourceFactory   $resourceFactory,
        private readonly StorageRepository $storageRepository,
    )
    {
    }

    public function upload(ServerRequestInterface $request): ResponseInterface
    {
        $uploadedFile = $this->extractFirstUploadedFile($request);

        $arguments = (array)($request->getParsedBody() ?? []);
        $targetPath = (string)($arguments['targetPath'] ?? '');
        $fileName = (string)($arguments['fileName'] ?? '');
        $storageUid = (int)($arguments['storageUid'] ?? 1);

        if ($uploadedFile === null) {
            return $this->errorResponse('No file uploaded', 400);
        }

        if ($targetPath === '') {
            return $this->errorResponse(self::ERROR_TARGET_PATH_REQUIRED, 400);
        }

        $tempPath = null;
        try {
            $storage = $this->storageRepository->findByUid($storageUid);
            if ($storage === null) {
                return $this->errorResponse('Storage not found: ' . $storageUid, 400);
            }

            $folder = $this->getOrCreateFolder($storage, $targetPath);

            if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
                return $this->errorResponse('Upload failed with error code: ' . $uploadedFile->getError(), 400);
            }

            $finalFileName = $fileName !== '' ? $fileName : ($uploadedFile->getClientFilename() ?? 'unnamed');
            $tempPath = $this->extractTempPath($uploadedFile);

            $fileObject = $storage->addFile(
                $tempPath,
                $folder,
                $finalFileName,
                DuplicationBehavior::REPLACE
            );

            return $this->jsonResponse($this->fileData($fileObject));
        } catch (ExistingTargetFileNameException) {
            return $this->errorResponse('File already exists', 409);
        } catch (Exception $e) {
            return $this->errorResponse('Upload failed: ' . $e->getMessage(), 500);
        } finally {
            if ($tempPath !== null && is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    public function get(int $uid): ResponseInterface
    {
        if ($uid === 0) {
            return $this->errorResponse(self::ERROR_UID_REQUIRED, 400);
        }

        try {
            $fileObject = $this->resourceFactory->getFileObject($uid);
            return $this->jsonResponse($this->fileData($fileObject));
        } catch (FileDoesNotExistException) {
            return $this->errorResponse(self::ERROR_FILE_NOT_FOUND, 404);
        } catch (Exception $e) {
            return $this->errorResponse('Failed to retrieve file: ' . $e->getMessage(), 500);
        }
    }

    public function delete(int $uid): ResponseInterface
    {
        if ($uid === 0) {
            return $this->errorResponse(self::ERROR_UID_REQUIRED, 400);
        }

        try {
            $fileObject = $this->resourceFactory->getFileObject($uid);
            $parentFolder = $fileObject->getParentFolder();
            $storage = $fileObject->getStorage();

            $storage->deleteFile($fileObject);
            $this->deleteEmptyFolders($storage, $parentFolder);

            return $this->jsonResponse(['success' => true, 'message' => 'File deleted successfully']);
        } catch (FileDoesNotExistException) {
            return $this->errorResponse(self::ERROR_FILE_NOT_FOUND, 404);
        } catch (Exception $e) {
            return $this->errorResponse('Failed to delete file: ' . $e->getMessage(), 500);
        }
    }

    public function rename(int $uid, ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->parseBody($request);
        $newFileName = (string)($body['newFileName'] ?? '');
        $conflictStrategy = (string)($body['conflictStrategy'] ?? 'RENAME');

        if ($uid === 0) {
            return $this->errorResponse(self::ERROR_UID_REQUIRED, 400);
        }

        if ($newFileName === '') {
            return $this->errorResponse('newFileName is required', 400);
        }

        try {
            $fileObject = $this->resourceFactory->getFileObject($uid);
            $previousName = $fileObject->getName();

            $renamedFile = $fileObject->getStorage()->renameFile(
                $fileObject,
                $newFileName,
                $this->resolveDuplicationBehavior($conflictStrategy)
            );

            return $this->jsonResponse([
                'uid' => $renamedFile->getUid(),
                'identifier' => $renamedFile->getIdentifier(),
                'name' => $renamedFile->getName(),
                'previousName' => $previousName,
                'modifiedAt' => date('c', $renamedFile->getModificationTime()),
            ]);
        } catch (FileDoesNotExistException) {
            return $this->errorResponse(self::ERROR_FILE_NOT_FOUND, 404);
        } catch (Exception $e) {
            return $this->errorResponse('Failed to rename file: ' . $e->getMessage(), 500);
        }
    }

    public function move(int $uid, ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->parseBody($request);
        $targetPath = (string)($body['targetPath'] ?? '');
        $newFileName = (string)($body['newFileName'] ?? '');
        $conflictStrategy = (string)($body['conflictStrategy'] ?? 'REPLACE');

        if ($uid === 0) {
            return $this->errorResponse(self::ERROR_UID_REQUIRED, 400);
        }

        if ($targetPath === '') {
            return $this->errorResponse(self::ERROR_TARGET_PATH_REQUIRED, 400);
        }

        try {
            $fileObject = $this->resourceFactory->getFileObject($uid);
            $storage = $fileObject->getStorage();
            $previousPath = $fileObject->getIdentifier();
            $fileName = $newFileName !== '' ? $newFileName : $fileObject->getName();
            $oldParentFolder = $fileObject->getParentFolder();

            $targetFolder = $this->getOrCreateFolder($storage, $targetPath);

            $movedFile = $fileObject->moveTo(
                $targetFolder,
                $fileName,
                $this->resolveDuplicationBehavior($conflictStrategy)
            );

            $this->deleteEmptyFolders($storage, $oldParentFolder);

            return $this->jsonResponse([
                'uid' => $movedFile->getUid(),
                'identifier' => $movedFile->getIdentifier(),
                'name' => $movedFile->getName(),
                'previousPath' => $previousPath,
                'modifiedAt' => date('c', $movedFile->getModificationTime()),
            ]);
        } catch (FileDoesNotExistException) {
            return $this->errorResponse(self::ERROR_FILE_NOT_FOUND, 404);
        } catch (Exception $e) {
            return $this->errorResponse('Failed to move file: ' . $e->getMessage(), 500);
        }
    }

    public function updateMetadata(int $uid, ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->parseBody($request);

        if ($uid === 0) {
            return $this->errorResponse(self::ERROR_UID_REQUIRED, 400);
        }

        if (empty($body)) {
            return $this->errorResponse('No metadata provided', 400);
        }

        try {
            $fileObject = $this->resourceFactory->getFileObject($uid);
            $metaData = $fileObject->getMetaData();

            $allowedFields = ['title', 'description', 'alternative', 'keywords', 'copyright'];
            foreach ($allowedFields as $field) {
                if (isset($body[$field])) {
                    $metaData[$field] = $body[$field];
                }
            }

            /**
             * @noinspection PhpInternalEntityUsedInspection save() is @internal, but the only
             * non-internal alternative (DataHandler) requires a backend user, which does not
             * exist in this frontend API context
             */
            $metaData->save();

            return $this->jsonResponse(['success' => true, 'message' => 'Metadata updated successfully']);
        } catch (FileDoesNotExistException) {
            return $this->errorResponse(self::ERROR_FILE_NOT_FOUND, 404);
        } catch (Exception $e) {
            return $this->errorResponse('Failed to update metadata: ' . $e->getMessage(), 500);
        }
    }

    /**
     * JSON bodies must be decoded from the raw body: the core request factory
     * runs parse_str() over PUT/PATCH/DELETE bodies, which turns a JSON string
     * into a garbage form array instead of leaving the parsed body empty.
     */
    private function parseBody(ServerRequestInterface $request): array
    {
        if (!str_contains($request->getHeaderLine('Content-Type'), 'json')) {
            $parsed = $request->getParsedBody();
            if (is_array($parsed) && $parsed !== []) {
                return $parsed;
            }
        }

        $decoded = json_decode((string)$request->getBody(), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function extractFirstUploadedFile(ServerRequestInterface $request): ?UploadedFileInterface
    {
        $uploads = $request->getUploadedFiles();

        while (is_array($uploads) && $uploads !== []) {
            $uploads = reset($uploads);
        }

        return $uploads instanceof UploadedFileInterface ? $uploads : null;
    }

    private function fileData(ResourceFile $file): array
    {
        return [
            'uid' => $file->getUid(),
            'identifier' => $file->getIdentifier(),
            'name' => $file->getName(),
            'size' => $file->getSize(),
            'mimeType' => $file->getMimeType(),
            'extension' => $file->getExtension(),
            'storageUid' => $file->getStorage()->getUid(),
            'createdAt' => date('c', $file->getCreationTime()),
            'modifiedAt' => date('c', $file->getModificationTime()),
        ];
    }

    private function jsonResponse(array $data, int $statusCode = 200): ResponseInterface
    {
        return new JsonResponse($data, $statusCode);
    }

    private function errorResponse(string $message, int $statusCode): ResponseInterface
    {
        return $this->jsonResponse(
            [
                'error' => true,
                'message' => $message,
                'statusCode' => $statusCode,
            ],
            $statusCode
        );
    }

    private function extractTempPath(UploadedFileInterface $uploadedFile): string
    {
        $stream = $uploadedFile->getStream();
        $uri = $stream->getMetadata('uri');

        if (is_string($uri) && file_exists($uri)) {
            return $uri;
        }

        $tempPath = sys_get_temp_dir() . '/' . uniqid('upload_', true);
        $uploadedFile->moveTo($tempPath);

        return $tempPath;
    }

    private function resolveDuplicationBehavior(string $strategy): DuplicationBehavior
    {
        return match (strtoupper($strategy)) {
            'REPLACE' => DuplicationBehavior::REPLACE,
            'CANCEL' => DuplicationBehavior::CANCEL,
            default => DuplicationBehavior::RENAME,
        };
    }

    /**
     * @throws InsufficientFolderAccessPermissionsException
     * @throws InsufficientFolderWritePermissionsException
     * @throws ExistingTargetFolderException
     */
    private function getOrCreateFolder(ResourceStorage $storage, string $path): Folder
    {
        $path = trim($path, '/');

        if ($path === '') {
            return $storage->getRootLevelFolder();
        }

        $pathSegments = array_filter(explode('/', $path), static fn(string $s): bool => $s !== '');

        if (empty($pathSegments)) {
            return $storage->getRootLevelFolder();
        }

        $currentFolder = $storage->getRootLevelFolder();

        foreach ($pathSegments as $segment) {
            $currentFolder = $storage->hasFolderInFolder($segment, $currentFolder)
                ? $storage->getFolderInFolder($segment, $currentFolder)
                : $storage->createFolder($segment, $currentFolder);
        }

        return $currentFolder;
    }

    private function deleteEmptyFolders(ResourceStorage $storage, Folder $folder): void
    {
        try {
            if ($folder->getFileCount() > 0 || count($folder->getSubfolders()) > 0) {
                return;
            }

            $parentFolder = $folder->getParentFolder();
            $storage->deleteFolder($folder, true);
            $this->deleteEmptyFolders($storage, $parentFolder);
        } catch (Exception) {
            // Silent fail - folder cleanup is non-critical
        }
    }
}

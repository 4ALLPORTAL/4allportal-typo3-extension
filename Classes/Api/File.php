<?php

declare(strict_types=1);

namespace Fourallportal\Fourallportalext\Api;

use Exception;
use Nng\Nnrestapi\Annotations as Api;
use Nng\Nnrestapi\Api\AbstractApi;
use Psr\Http\Message\UploadedFileInterface;
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
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @Api\Endpoint()
 */
class File extends AbstractApi
{
    private const ERROR_FILE_NOT_FOUND = 'File not found';
    private const ERROR_UID_REQUIRED = 'uid is required';
    private const ERROR_TARGET_PATH_REQUIRED = 'targetPath is required';

    /**
     * @Api\Route("POST /api/files")
     * @Api\Upload("default")
     * @Api\Access("fe_users")
     */
    public function uploadFile(): array
    {
        $uploads = $this->request->getUploadedFiles();

        $arguments = $this->request->getArguments();
        $targetPath = $arguments['targetPath'] ?? $_POST['targetPath'] ?? '';
        $fileName = $arguments['fileName'] ?? $_POST['fileName'] ?? '';
        $storageUid = (int)($arguments['storageUid'] ?? $_POST['storageUid'] ?? 1);

        if (empty($uploads)) {
            return $this->errorResponse('No file uploaded', 400);
        }

        if ($targetPath === '') {
            return $this->errorResponse(self::ERROR_TARGET_PATH_REQUIRED, 400);
        }

        try {
            $storage = $this->getResourceFactory()->getStorageObject($storageUid);
            $folder = $this->getOrCreateFolder($storage, $targetPath);

            /** @var UploadedFileInterface $uploadedFile */
            $uploadedFile = reset($uploads);

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

            return $this->fileResponse($fileObject);

        } catch (ExistingTargetFileNameException) {
            return $this->errorResponse('File already exists', 409);
        } catch (Exception $e) {
            return $this->errorResponse('Upload failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * @Api\Route("GET /api/files/{uid}")
     * @Api\Access("fe_users")
     */
    public function getFile(int $uid = 0): array
    {
        $uid = $this->resolveUid($uid);

        if ($uid === 0) {
            return $this->errorResponse(self::ERROR_UID_REQUIRED, 400);
        }

        try {
            $fileObject = $this->getResourceFactory()->getFileObject($uid);
            return $this->fileResponse($fileObject);

        } catch (FileDoesNotExistException) {
            return $this->errorResponse(self::ERROR_FILE_NOT_FOUND, 404);
        } catch (Exception $e) {
            return $this->errorResponse('Failed to retrieve file: ' . $e->getMessage(), 500);
        }
    }

    /**
     * @Api\Route("DELETE /api/files/{uid}")
     * @Api\Access("fe_users")
     */
    public function deleteFile(int $uid = 0): array
    {
        $uid = $this->resolveUid($uid);

        if ($uid === 0) {
            return $this->errorResponse(self::ERROR_UID_REQUIRED, 400);
        }

        try {
            $fileObject = $this->getResourceFactory()->getFileObject($uid);
            $parentFolder = $fileObject->getParentFolder();
            $storage = $fileObject->getStorage();

            $storage->deleteFile($fileObject);

            if ($parentFolder instanceof Folder) {
                $this->deleteEmptyFolders($storage, $parentFolder);
            }

            return ['success' => true, 'message' => 'File deleted successfully'];

        } catch (FileDoesNotExistException) {
            return $this->errorResponse(self::ERROR_FILE_NOT_FOUND, 404);
        } catch (Exception $e) {
            return $this->errorResponse('Failed to delete file: ' . $e->getMessage(), 500);
        }
    }

    /**
     * @Api\Route("POST /api/files/{uid}/rename")
     * @Api\Access("fe_users")
     */
    public function renameFile(int $uid = 0): array
    {
        $uid = $this->resolveUid($uid);
        $body = $this->request->getBody();
        $newFileName = $body['newFileName'] ?? '';
        $conflictStrategy = $body['conflictStrategy'] ?? 'RENAME';

        if ($uid === 0) {
            return $this->errorResponse(self::ERROR_UID_REQUIRED, 400);
        }

        if ($newFileName === '') {
            return $this->errorResponse('newFileName is required', 400);
        }

        try {
            $fileObject = $this->getResourceFactory()->getFileObject($uid);
            $previousName = $fileObject->getName();

            $renamedFile = $fileObject->getStorage()->renameFile(
                $fileObject,
                $newFileName,
                $this->resolveDuplicationBehavior($conflictStrategy)
            );

            return [
                'uid' => $renamedFile->getUid(),
                'identifier' => $renamedFile->getIdentifier(),
                'name' => $renamedFile->getName(),
                'previousName' => $previousName,
                'modifiedAt' => date('c', $renamedFile->getModificationTime()),
            ];

        } catch (FileDoesNotExistException) {
            return $this->errorResponse(self::ERROR_FILE_NOT_FOUND, 404);
        } catch (Exception $e) {
            return $this->errorResponse('Failed to rename file: ' . $e->getMessage(), 500);
        }
    }

    /**
     * @Api\Route("POST /api/files/{uid}/move")
     * @Api\Access("fe_users")
     */
    public function moveFile(int $uid = 0): array
    {
        $uid = $this->resolveUid($uid);
        $body = $this->request->getBody();
        $targetPath = $body['targetPath'] ?? '';
        $newFileName = $body['newFileName'] ?? '';
        $conflictStrategy = $body['conflictStrategy'] ?? 'REPLACE';

        if ($uid === 0) {
            return $this->errorResponse(self::ERROR_UID_REQUIRED, 400);
        }

        if ($targetPath === '') {
            return $this->errorResponse(self::ERROR_TARGET_PATH_REQUIRED, 400);
        }

        try {
            $fileObject = $this->getResourceFactory()->getFileObject($uid);
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

            if ($oldParentFolder instanceof Folder) {
                $this->deleteEmptyFolders($storage, $oldParentFolder);
            }

            return [
                'uid' => $movedFile->getUid(),
                'identifier' => $movedFile->getIdentifier(),
                'name' => $movedFile->getName(),
                'previousPath' => $previousPath,
                'modifiedAt' => date('c', $movedFile->getModificationTime()),
            ];

        } catch (FileDoesNotExistException) {
            return $this->errorResponse(self::ERROR_FILE_NOT_FOUND, 404);
        } catch (Exception $e) {
            return $this->errorResponse('Failed to move file: ' . $e->getMessage(), 500);
        }
    }

    /**
     * @Api\Route("PUT /api/files/{uid}")
     * @Api\Access("fe_users")
     */
    public function updateMetadata(int $uid = 0): array
    {
        $uid = $this->resolveUid($uid);
        $body = $this->request->getBody();

        if ($uid === 0) {
            return $this->errorResponse(self::ERROR_UID_REQUIRED, 400);
        }

        if (empty($body)) {
            return $this->errorResponse('No metadata provided', 400);
        }

        try {
            $fileObject = $this->getResourceFactory()->getFileObject($uid);
            $metaData = $fileObject->getMetaData();

            $allowedFields = ['title', 'description', 'alternative', 'keywords', 'copyright'];
            foreach ($allowedFields as $field) {
                if (isset($body[$field])) {
                    $metaData[$field] = $body[$field];
                }
            }

            $metaData->save();

            return ['success' => true, 'message' => 'Metadata updated successfully'];

        } catch (FileDoesNotExistException) {
            return $this->errorResponse(self::ERROR_FILE_NOT_FOUND, 404);
        } catch (Exception $e) {
            return $this->errorResponse('Failed to update metadata: ' . $e->getMessage(), 500);
        }
    }

    private function resolveUid(int $uid): int
    {
        return $uid ?: (int)($this->request->getArguments()['uid'] ?? 0);
    }

    private function getResourceFactory(): ResourceFactory
    {
        return GeneralUtility::makeInstance(ResourceFactory::class);
    }

    private function fileResponse(ResourceFile $file): array
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

    private function errorResponse(string $message, int $statusCode): array
    {
        http_response_code($statusCode);

        return [
            'error' => true,
            'message' => $message,
            'statusCode' => $statusCode,
        ];
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

            if ($parentFolder instanceof Folder) {
                $this->deleteEmptyFolders($storage, $parentFolder);
            }
        } catch (Exception) {
            // Silent fail - folder cleanup is non-critical
        }
    }
}

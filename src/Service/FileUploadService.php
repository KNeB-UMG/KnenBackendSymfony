<?php

namespace App\Service;

use App\Entity\File;
use App\Entity\Member;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class FileUploadService
{
    // File categories
    public const string CATEGORY_PROFILE_PICTURE = 'profile_picture';
    public const string CATEGORY_EVENT_PHOTO = 'event_photo';
    public const string CATEGORY_PROJECT_PHOTO = 'project_photo';
    public const string CATEGORY_TECHNOLOGY_ICON = 'technology_icon';
    public const string CATEGORY_GENERAL = 'general';

    // File types
    public const string TYPE_IMAGE = 'image';
    public const string TYPE_DOCUMENT = 'document';
    public const string TYPE_ARCHIVE = 'archive';
    public const string TYPE_OTHER = 'other';

    // Permissions
    public const string PERMISSION_PUBLIC = 'public';
    public const string PERMISSION_MEMBERS_ONLY = 'members_only';
    public const string PERMISSION_MODERATORS_ONLY = 'moderators_only';
    public const string PERMISSION_ADMINS_ONLY = 'admins_only';

    private const array ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const array ALLOWED_DOCUMENT_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/csv',
        'application/rtf',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.presentation'
    ];
    private const array ALLOWED_ARCHIVE_TYPES = ['application/zip', 'application/x-rar-compressed'];

    private const int MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    private const int MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5MB

    private string $uploadsDirectory;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
        string $uploadsDirectory
    ) {
        $this->uploadsDirectory = rtrim($uploadsDirectory, '/');
    }

    public function uploadFile(
        UploadedFile $uploadedFile,
        string $category,
        string $permissions,
        Member $uploader,
        ?int $maxWidth = null,
        ?int $maxHeight = null
    ): array {
        $validation = $this->validateFile($uploadedFile, $category);
        if (!$validation['success']) {
            return $validation;
        }

        $originalName = $uploadedFile->getClientOriginalName();
        $mimeType = $uploadedFile->getMimeType();
        $size = $uploadedFile->getSize();
        $fileType = $this->determineFileType($mimeType);

        // Generate unique filename
        $safeFilename = $this->slugger->slug(pathinfo($originalName, PATHINFO_FILENAME));
        $extension = $uploadedFile->guessExtension();
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;

        // Create category directory
        $categoryDir = $this->uploadsDirectory . '/' . $category;
        if (!is_dir($categoryDir)) {
            mkdir($categoryDir, 0755, true);
        }

        $filePath = $categoryDir . '/' . $newFilename;

        try {
            // Handle image compression if needed
            if ($fileType === self::TYPE_IMAGE && ($maxWidth || $maxHeight)) {
                $this->processAndSaveImage($uploadedFile, $filePath, $maxWidth, $maxHeight);
            } else {
                $uploadedFile->move($categoryDir, $newFilename);
            }

            // Create File entity
            $file = new File();
            $file->setOriginalName($originalName);
            $file->setFilePath($category . '/' . $newFilename);
            $file->setMimeType($mimeType);
            $file->setFileType($fileType);
            $file->setCategory($category);
            $file->setPermissions($permissions);
            $file->setSize(filesize($filePath));
            $file->setUploader($uploader);

            $this->entityManager->persist($file);
            $this->entityManager->flush();

            return [
                'success' => true,
                'file' => $file
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Błąd podczas zapisywania pliku'
            ];
        }
    }

    private function validateFile(UploadedFile $file, string $category): array
    {
        if (!$file->isValid()) {
            return ['success' => false, 'message' => 'Plik jest uszkodzony'];
        }

        $mimeType = $file->getMimeType();
        $size = $file->getSize();

        // Check file size
        $maxSize = in_array($mimeType, self::ALLOWED_IMAGE_TYPES) ? self::MAX_IMAGE_SIZE : self::MAX_FILE_SIZE;
        if ($size > $maxSize) {
            return ['success' => false, 'message' => 'Plik jest za duży (max ' . ($maxSize / 1024 / 1024) . 'MB)'];
        }

        // Check mime type based on category
        if ($category === self::CATEGORY_PROFILE_PICTURE ||
            $category === self::CATEGORY_EVENT_PHOTO ||
            $category === self::CATEGORY_PROJECT_PHOTO ||
            $category === self::CATEGORY_TECHNOLOGY_ICON) {
            if (!in_array($mimeType, self::ALLOWED_IMAGE_TYPES)) {
                return ['success' => false, 'message' => 'Dozwolone są tylko pliki: JPG, PNG, WebP'];
            }
        } else {
            $allowedTypes = array_merge(
                self::ALLOWED_IMAGE_TYPES,
                self::ALLOWED_DOCUMENT_TYPES,
                self::ALLOWED_ARCHIVE_TYPES
            );
            if (!in_array($mimeType, $allowedTypes)) {
                return ['success' => false, 'message' => 'Nieprawidłowy typ pliku'];
            }
        }

        return ['success' => true];
    }

    private function determineFileType(string $mimeType): string
    {
        if (in_array($mimeType, self::ALLOWED_IMAGE_TYPES)) {
            return self::TYPE_IMAGE;
        }
        if (in_array($mimeType, self::ALLOWED_DOCUMENT_TYPES)) {
            return self::TYPE_DOCUMENT;
        }
        if (in_array($mimeType, self::ALLOWED_ARCHIVE_TYPES)) {
            return self::TYPE_ARCHIVE;
        }
        return self::TYPE_OTHER;
    }

    private function processAndSaveImage(UploadedFile $file, string $outputPath, ?int $maxWidth, ?int $maxHeight): void
    {
        $manager = new ImageManager(new Driver());
        $image = $manager->read($file->getPathname());

        if ($maxWidth || $maxHeight) {
            $image->scaleDown($maxWidth, $maxHeight);
        }

        // Convert to JPEG for consistency and compression
        $image->toJpeg(85)->save($outputPath);
    }

    public function deleteFile(File $file): array
    {
        try {
            $fullPath = $this->uploadsDirectory . '/' . $file->getFilePath();
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            $this->entityManager->remove($file);
            $this->entityManager->flush();

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Błąd podczas usuwania pliku'];
        }
    }

    public function canUserAccessFile(File $file, ?Member $user): bool
    {
        return match ($file->getPermissions()) {
            self::PERMISSION_PUBLIC => true,
            self::PERMISSION_MEMBERS_ONLY => $user !== null && $user->isActiveUser(),
            self::PERMISSION_MODERATORS_ONLY => $user !== null && ($user->getRole() === Member::ROLE_MODERATOR || $user->getRole() === Member::ROLE_ADMIN),
            self::PERMISSION_ADMINS_ONLY => $user !== null && $user->getRole() === Member::ROLE_ADMIN,
            default => false
        };
    }

    public function getFileUrl(File $file): string
    {
        return '/api/file/' . $file->getId() . '/download';
    }

    public function getFullFilePath(File $file): string
    {
        return $this->uploadsDirectory . '/' . $file->getFilePath();
    }
}

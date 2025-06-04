<?php

namespace App\Controller;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\OpenApi\Model;
use App\Entity\File;
use App\Entity\Member;
use App\Entity\Technology;
use App\Service\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/api/file/general',
            controller: self::class . '::uploadGeneralFile',
            openapi: new Model\Operation(
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'file' => ['type' => 'string', 'format' => 'binary'],
                                    'permissions' => ['type' => 'string', 'enum' => ['public', 'members_only', 'moderators_only', 'admins_only']],
                                ],
                                'required' => ['file', 'permissions']
                            ]
                        ]
                    ])
                )
            ),
            security: "is_granted('ROLE_MODERATOR')",
            name: 'app_file_upload_general'
        ),
        new Post(
            uriTemplate: '/api/file/technology/{id}/icon',
            controller: self::class . '::uploadTechnologyIcon',
            openapi: new Model\Operation(
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'file' => ['type' => 'string', 'format' => 'binary'],
                                ],
                                'required' => ['file']
                            ]
                        ]
                    ])
                )
            ),
            security: "is_granted('ROLE_ADMIN')",
            name: 'app_file_upload_technology_icon'
        ),
        new Get(
            uriTemplate: '/api/files/general',
            controller: self::class . '::getGeneralFiles',
            security: "is_granted('ROLE_USER')",
            name: 'app_files_general_list'
        ),
        new Get(
            uriTemplate: '/api/file/{id}/download',
            controller: self::class . '::downloadFile',
            name: 'app_file_download'
        ),
        new Delete(
            uriTemplate: '/api/file/{id}',
            controller: self::class . '::deleteFile',
            security: "is_granted('ROLE_MODERATOR')",
            name: 'app_file_delete'
        )
    ]
)]
final class FileController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FileUploadService $fileUploadService
    ) {}

    #[Route('/api/file/general', name: 'app_file_upload_general', methods: ['POST'])]
    public function uploadGeneralFile(Request $request, UserInterface $user): JsonResponse
    {
        /** @var Member $member */
        $member = $user;

        $uploadedFile = $request->files->get('file');
        if (!$uploadedFile) {
            return new JsonResponse(['message' => 'Brak pliku'], Response::HTTP_BAD_REQUEST);
        }

        $permissions = $request->request->get('permissions');
        if (!$permissions || !in_array($permissions, [
                FileUploadService::PERMISSION_PUBLIC,
                FileUploadService::PERMISSION_MEMBERS_ONLY,
                FileUploadService::PERMISSION_MODERATORS_ONLY,
                FileUploadService::PERMISSION_ADMINS_ONLY
            ])) {
            return new JsonResponse(['message' => 'Nieprawidłowe uprawnienia'], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->fileUploadService->uploadFile(
            $uploadedFile,
            FileUploadService::CATEGORY_GENERAL,
            $permissions,
            $member
        );

        if (!$result['success']) {
            return new JsonResponse(['message' => $result['message']], Response::HTTP_BAD_REQUEST);
        }

        $file = $result['file'];
        return new JsonResponse([
            'message' => 'Plik został przesłany pomyślnie',
            'file' => [
                'id' => $file->getId(),
                'originalName' => $file->getOriginalName(),
                'size' => $file->getSize(),
                'type' => $file->getFileType(),
                'permissions' => $file->getPermissions(),
                'downloadUrl' => $this->fileUploadService->getFileUrl($file)
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/file/technology/{id}/icon', name: 'app_file_upload_technology_icon', methods: ['POST'])]
    public function uploadTechnologyIcon(int $id, Request $request, UserInterface $user): JsonResponse
    {
        /** @var Member $member */
        $member = $user;

        $technology = $this->entityManager->getRepository(Technology::class)->find($id);
        if (!$technology) {
            return new JsonResponse(['message' => 'Nie znaleziono technologii'], Response::HTTP_NOT_FOUND);
        }

        $uploadedFile = $request->files->get('file');
        if (!$uploadedFile) {
            return new JsonResponse(['message' => 'Brak pliku'], Response::HTTP_BAD_REQUEST);
        }

        // Remove old icon if exists
        if ($technology->getIcon()) {
            // Let the service handle file deletion
            $existingFiles = $this->entityManager->getRepository(File::class)
                ->findBy(['category' => FileUploadService::CATEGORY_TECHNOLOGY_ICON]);

            foreach ($existingFiles as $file) {
                if (basename($file->getFilePath()) === $technology->getIcon()) {
                    $this->fileUploadService->deleteFile($file);
                    break;
                }
            }
        }

        $result = $this->fileUploadService->uploadFile(
            $uploadedFile,
            FileUploadService::CATEGORY_TECHNOLOGY_ICON,
            FileUploadService::PERMISSION_PUBLIC,
            $member,
            64, // Max width for icons
            64  // Max height for icons
        );

        if (!$result['success']) {
            return new JsonResponse(['message' => $result['message']], Response::HTTP_BAD_REQUEST);
        }

        $file = $result['file'];

        // Update technology icon path
        $iconFileName = basename($file->getFilePath());
        $technology->setIcon($iconFileName);
        $this->entityManager->persist($technology);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Ikona technologii została zaktualizowana',
            'technology' => [
                'id' => $technology->getId(),
                'name' => $technology->getName(),
                'icon' => $technology->getIcon()
            ]
        ]);
    }

    #[Route('/api/files/general', name: 'app_files_general_list', methods: ['GET'])]
    public function getGeneralFiles(UserInterface $user): JsonResponse
    {
        /** @var Member $member */
        $member = $user;

        $allFiles = $this->entityManager->getRepository(File::class)
            ->findBy(['category' => FileUploadService::CATEGORY_GENERAL]);

        $accessibleFiles = array_filter($allFiles, function(File $file) use ($member) {
            return $this->fileUploadService->canUserAccessFile($file, $member);
        });

        $filesData = array_map(function(File $file) {
            return [
                'id' => $file->getId(),
                'originalName' => $file->getOriginalName(),
                'size' => $file->getSize(),
                'type' => $file->getFileType(),
                'permissions' => $file->getPermissions(),
                'uploadedBy' => $file->getUploader() ? $file->getUploader()->getFullName() : null,
                'downloadUrl' => $this->fileUploadService->getFileUrl($file)
            ];
        }, $accessibleFiles);

        return new JsonResponse($filesData);
    }

    #[Route('/api/file/{id}/download', name: 'app_file_download', methods: ['GET'])]
    public function downloadFile(int $id, Request $request): Response
    {
        $file = $this->entityManager->getRepository(File::class)->find($id);
        if (!$file) {
            return new JsonResponse(['message' => 'Plik nie został znaleziony'], Response::HTTP_NOT_FOUND);
        }

        // Get current user (can be null for public files)
        $user = $this->getUser();
        $member = $user instanceof Member ? $user : null;

        if (!$this->fileUploadService->canUserAccessFile($file, $member)) {
            return new JsonResponse(['message' => 'Brak uprawnień do pobrania pliku'], Response::HTTP_FORBIDDEN);
        }

        $filePath = $this->fileUploadService->getFullFilePath($file);
        if (!file_exists($filePath)) {
            return new JsonResponse(['message' => 'Plik nie istnieje na serwerze'], Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition('attachment', $file->getOriginalName());

        return $response;
    }

    #[Route('/api/file/{id}', name: 'app_file_delete', methods: ['DELETE'])]
    public function deleteFile(int $id, UserInterface $user): JsonResponse
    {
        /** @var Member $member */
        $member = $user;

        $file = $this->entityManager->getRepository(File::class)->find($id);
        if (!$file) {
            return new JsonResponse(['message' => 'Plik nie został znaleziony'], Response::HTTP_NOT_FOUND);
        }

        // Only allow deletion of general files or files uploaded by the user (if admin/moderator)
        if ($file->getCategory() !== FileUploadService::CATEGORY_GENERAL) {
            return new JsonResponse(['message' => 'Nie można usunąć tego typu pliku'], Response::HTTP_FORBIDDEN);
        }

        // Admins can delete any file, moderators can delete their own files
        if ($member->getRole() !== Member::ROLE_ADMIN &&
            $file->getUploader()->getId() !== $member->getId()) {
            return new JsonResponse(['message' => 'Brak uprawnień do usunięcia pliku'], Response::HTTP_FORBIDDEN);
        }

        $result = $this->fileUploadService->deleteFile($file);
        if (!$result['success']) {
            return new JsonResponse(['message' => $result['message']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['message' => 'Plik został usunięty']);
    }
}

<?php

namespace App\Controller;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post as ApiPost;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model;
use App\Entity\Post;
use App\Entity\Member;
use App\Entity\File;
use App\Service\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

#[ApiResource(
    operations: [
        new ApiPost(
            uriTemplate: '/api/post/create',
            controller: self::class . '::createPost',
            openapi: new Model\Operation(
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'title' => ['type' => 'string', 'example' => 'Tytuł posta'],
                                    'content' => ['type' => 'string', 'example' => 'Treść posta'],
                                    'superEvent' => ['type' => 'boolean', 'example' => false],
                                    'file' => ['type' => 'string', 'format' => 'binary', 'description' => 'Single file (backward compatibility)'],
                                    'files' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'string', 'format' => 'binary'],
                                        'description' => 'Multiple files'
                                    ],
                                ],
                                'required' => ['title', 'content']
                            ]
                        ]
                    ])
                )
            ),
            security: "is_granted('ROLE_USER')",
            name: 'app_post_create'
        ),
        new Put(
            uriTemplate: '/api/post/{id}/edit',
            controller: self::class . '::editPost',
            openapi: new Model\Operation(
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'title' => ['type' => 'string', 'example' => 'Nowy tytuł'],
                                    'content' => ['type' => 'string', 'example' => 'Nowa treść'],
                                    'superEvent' => ['type' => 'boolean', 'example' => false],
                                    'file' => ['type' => 'string', 'format' => 'binary', 'description' => 'Single file (backward compatibility)'],
                                    'files' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'string', 'format' => 'binary'],
                                        'description' => 'Multiple files'
                                    ],
                                    'replaceFiles' => ['type' => 'boolean', 'example' => false, 'description' => 'Replace all existing files'],
                                ]
                            ]
                        ]
                    ])
                )
            ),
            security: "is_granted('ROLE_USER')",
            name: 'app_post_edit'
        ),
        new Put(
            uriTemplate: '/api/admin/post/{id}/visibility',
            controller: self::class . '::toggleVisibility',
            openapi: new Model\Operation(
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'visible' => ['type' => 'boolean', 'example' => true],
                                ],
                                'required' => ['visible']
                            ]
                        ]
                    ])
                )
            ),
            security: "is_granted('ROLE_ADMIN')",
            name: 'app_admin_post_visibility'
        ),
        new Get(
            uriTemplate: '/api/posts/visible',
            controller: self::class . '::getVisiblePosts',
            name: 'app_posts_visible'
        ),
        new Get(
            uriTemplate: '/api/posts/all',
            controller: self::class . '::getAllPosts',
            security: "is_granted('ROLE_MODERATOR')",
            name: 'app_posts_all'
        ),
        new Get(
            uriTemplate: '/api/post/{id}',
            controller: self::class . '::getPost',
            security: "is_granted('ROLE_USER')",
            name: 'app_post_get'
        )
    ]
)]
final class PostController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FileUploadService $fileUploadService
    ) {}

    #[Route('/api/post/create', name: 'app_post_create', methods: ['POST'])]
    public function createPost(Request $request, UserInterface $user): JsonResponse
    {
        /** @var Member $member */
        $member = $user;

        $title = $request->request->get('title');
        $content = $request->request->get('content');
        $superEvent = $request->request->getBoolean('superEvent', false);

        if (!$title || !$content) {
            return new JsonResponse(['message' => 'Tytuł i treść są wymagane'], Response::HTTP_BAD_REQUEST);
        }

        $post = new Post();
        $post->setTitle($title);
        $post->setContent($content);
        $post->setSuperEvent($superEvent);
        $post->setVisible(false); // Hidden by default
        $post->setAuthor($member);
        $post->setEditHistory([]);

        // Handle multiple file uploads if provided
        $uploadedFiles = $request->files->get('files', []);
        if (!is_array($uploadedFiles)) {
            $uploadedFiles = [$uploadedFiles]; // Single file uploaded as 'files'
        }

        // Also check for single file upload (backward compatibility)
        $singleFile = $request->files->get('file');
        if ($singleFile) {
            $uploadedFiles[] = $singleFile;
        }

        foreach ($uploadedFiles as $uploadedFile) {
            if ($uploadedFile) {
                $result = $this->fileUploadService->uploadFile(
                    $uploadedFile,
                    FileUploadService::CATEGORY_EVENT_PHOTO,
                    FileUploadService::PERMISSION_PUBLIC,
                    $member
                );

                if (!$result['success']) {
                    return new JsonResponse(['message' => $result['message']], Response::HTTP_BAD_REQUEST);
                }

                $post->addFile($result['file']);
            }
        }

        $this->entityManager->persist($post);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Post został utworzony pomyślnie',
            'post' => [
                'id' => $post->getId(),
                'title' => $post->getTitle(),
                'content' => $post->getContent(),
                'superEvent' => $post->isSuperEvent(),
                'visible' => $post->isVisible(),
                'author' => $post->getAuthor()->getFullName(),
                'fileCount' => $post->getFiles()->count(),
                'files' => array_map(function(File $file) {
                    return [
                        'id' => $file->getId(),
                        'originalName' => $file->getOriginalName(),
                        'url' => $this->fileUploadService->getFileUrl($file)
                    ];
                }, $post->getFiles()->toArray())
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/post/{id}/edit', name: 'app_post_edit', methods: ['PUT'])]
    public function editPost(int $id, Request $request, UserInterface $user): JsonResponse
    {
        /** @var Member $member */
        $member = $user;

        $post = $this->entityManager->getRepository(Post::class)->find($id);
        if (!$post) {
            return new JsonResponse(['message' => 'Post nie został znaleziony'], Response::HTTP_NOT_FOUND);
        }

        // Check permissions - author or admin/moderator can edit
        if ($post->getAuthor()->getId() !== $member->getId() &&
            !in_array($member->getRole(), [Member::ROLE_ADMIN, Member::ROLE_MODERATOR])) {
            return new JsonResponse(['message' => 'Brak uprawnień do edycji tego posta'], Response::HTTP_FORBIDDEN);
        }

        // Save current state to edit history
        $editHistory = $post->getEditHistory() ?? [];
        $editHistory[] = [
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            'editedBy' => $member->getFullName(),
            'changes' => [
                'title' => $post->getTitle(),
                'content' => $post->getContent(),
                'superEvent' => $post->isSuperEvent()
            ]
        ];

        // Update post
        $title = $request->request->get('title');
        $content = $request->request->get('content');
        $superEvent = $request->request->get('superEvent');

        if ($title) $post->setTitle($title);
        if ($content) $post->setContent($content);
        if ($superEvent !== null) $post->setSuperEvent((bool)$superEvent);

        // Hide post when edited (unless admin is editing)
        if ($member->getRole() !== Member::ROLE_ADMIN) {
            $post->setVisible(false);
        }

        // Handle new file uploads
        $uploadedFiles = $request->files->get('files', []);
        if (!is_array($uploadedFiles)) {
            $uploadedFiles = [$uploadedFiles];
        }

        // Also check for single file upload (backward compatibility)
        $singleFile = $request->files->get('file');
        if ($singleFile) {
            $uploadedFiles[] = $singleFile;
        }

        // Check if we should replace all files or add to existing
        $replaceFiles = $request->request->getBoolean('replaceFiles', false);

        if ($replaceFiles || count($uploadedFiles) > 0) {
            // Remove old files if replacing or if new files uploaded
            foreach ($post->getFiles() as $oldFile) {
                $this->fileUploadService->deleteFile($oldFile);
            }
            $post->clearFiles();
        }

        foreach ($uploadedFiles as $uploadedFile) {
            if ($uploadedFile) {
                $result = $this->fileUploadService->uploadFile(
                    $uploadedFile,
                    FileUploadService::CATEGORY_EVENT_PHOTO,
                    FileUploadService::PERMISSION_PUBLIC,
                    $member
                );

                if (!$result['success']) {
                    return new JsonResponse(['message' => $result['message']], Response::HTTP_BAD_REQUEST);
                }

                $post->addFile($result['file']);
            }
        }

        $post->setEditHistory($editHistory);
        $this->entityManager->persist($post);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Post został zaktualizowany',
            'post' => [
                'id' => $post->getId(),
                'title' => $post->getTitle(),
                'content' => $post->getContent(),
                'superEvent' => $post->isSuperEvent(),
                'visible' => $post->isVisible(),
                'author' => $post->getAuthor()->getFullName(),
                'fileCount' => $post->getFiles()->count(),
                'editCount' => count($post->getEditHistory())
            ]
        ]);
    }

    #[Route('/api/admin/post/{id}/visibility', name: 'app_admin_post_visibility', methods: ['PUT'])]
    public function toggleVisibility(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['visible']) || !is_bool($data['visible'])) {
            return new JsonResponse(['message' => 'Pole visible jest wymagane'], Response::HTTP_BAD_REQUEST);
        }

        $post = $this->entityManager->getRepository(Post::class)->find($id);
        if (!$post) {
            return new JsonResponse(['message' => 'Post nie został znaleziony'], Response::HTTP_NOT_FOUND);
        }

        $post->setVisible($data['visible']);
        $this->entityManager->persist($post);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Widoczność posta została zmieniona',
            'post' => [
                'id' => $post->getId(),
                'title' => $post->getTitle(),
                'visible' => $post->isVisible()
            ]
        ]);
    }

    #[Route('/api/posts/visible', name: 'app_posts_visible', methods: ['GET'])]
    public function getVisiblePosts(): JsonResponse
    {
        $posts = $this->entityManager->getRepository(Post::class)
            ->findBy(['visible' => true], ['id' => 'DESC']);

        $postsData = array_map(function(Post $post) {
            return [
                'id' => $post->getId(),
                'title' => $post->getTitle(),
                'content' => $post->getContent(),
                'superEvent' => $post->isSuperEvent(),
                'author' => $post->getAuthor()->getFullName(),
                'fileCount' => $post->getFiles()->count(),
                'files' => array_map(function(File $file) {
                    return [
                        'id' => $file->getId(),
                        'originalName' => $file->getOriginalName(),
                        'url' => $this->fileUploadService->getFileUrl($file)
                    ];
                }, $post->getFiles()->toArray())
            ];
        }, $posts);

        return new JsonResponse($postsData);
    }

    #[Route('/api/posts/all', name: 'app_posts_all', methods: ['GET'])]
    public function getAllPosts(): JsonResponse
    {
        $posts = $this->entityManager->getRepository(Post::class)
            ->findBy([], ['id' => 'DESC']);

        $postsData = array_map(function(Post $post) {
            return [
                'id' => $post->getId(),
                'title' => $post->getTitle(),
                'content' => $post->getContent(),
                'superEvent' => $post->isSuperEvent(),
                'visible' => $post->isVisible(),
                'author' => $post->getAuthor()->getFullName(),
                'fileCount' => $post->getFiles()->count(),
                'editCount' => count($post->getEditHistory() ?? [])
            ];
        }, $posts);

        return new JsonResponse($postsData);
    }

    #[Route('/api/post/{id}', name: 'app_post_get', methods: ['GET'])]
    public function getPost(int $id): JsonResponse
    {
        $post = $this->entityManager->getRepository(Post::class)->find($id);
        if (!$post) {
            return new JsonResponse(['message' => 'Post nie został znaleziony'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id' => $post->getId(),
            'title' => $post->getTitle(),
            'content' => $post->getContent(),
            'superEvent' => $post->isSuperEvent(),
            'visible' => $post->isVisible(),
            'author' => $post->getAuthor()->getFullName(),
            'fileCount' => $post->getFiles()->count(),
            'files' => array_map(function(File $file) {
                return [
                    'id' => $file->getId(),
                    'originalName' => $file->getOriginalName(),
                    'url' => $this->fileUploadService->getFileUrl($file)
                ];
            }, $post->getFiles()->toArray()),
            'editHistory' => $post->getEditHistory()
        ]);
    }
}

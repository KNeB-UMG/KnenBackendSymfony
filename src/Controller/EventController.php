<?php

namespace App\Controller;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post as ApiPost;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model;
use App\Entity\Event;
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
            uriTemplate: '/api/event/create',
            controller: self::class . '::createEvent',
            openapi: new Model\Operation(
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'title' => ['type' => 'string', 'example' => 'Tytuł wydarzenia'],
                                    'content' => ['type' => 'string', 'example' => 'Opis wydarzenia'],
                                    'description' => ['type' => 'string', 'example' => 'Krótki opis'],
                                    'eventDate' => ['type' => 'string', 'format' => 'date-time', 'example' => '2024-12-31 18:00:00'],
                                    'file' => ['type' => 'string', 'format' => 'binary', 'description' => 'Single file (backward compatibility)'],
                                    'files' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'string', 'format' => 'binary'],
                                        'description' => 'Multiple files'
                                    ],
                                ],
                                'required' => ['title', 'content', 'eventDate']
                            ]
                        ]
                    ])
                )
            ),
            security: "is_granted('ROLE_USER')",
            name: 'app_event_create'
        ),
        new Put(
            uriTemplate: '/api/event/{id}/edit',
            controller: self::class . '::editEvent',
            openapi: new Model\Operation(
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'title' => ['type' => 'string', 'example' => 'Nowy tytuł'],
                                    'content' => ['type' => 'string', 'example' => 'Nowa treść'],
                                    'description' => ['type' => 'string', 'example' => 'Nowy opis'],
                                    'eventDate' => ['type' => 'string', 'format' => 'date-time'],
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
            name: 'app_event_edit'
        ),
        new Put(
            uriTemplate: '/api/admin/event/{id}/visibility',
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
            name: 'app_admin_event_visibility'
        ),
        new Get(
            uriTemplate: '/api/events/visible',
            controller: self::class . '::getVisibleEvents',
            name: 'app_events_visible'
        ),
        new Get(
            uriTemplate: '/api/events/all',
            controller: self::class . '::getAllEvents',
            security: "is_granted('ROLE_MODERATOR')",
            name: 'app_events_all'
        ),
        new Get(
            uriTemplate: '/api/event/{eventPath}',
            controller: self::class . '::getEventByPath',
            name: 'app_event_get_by_path'
        )
    ]
)]
final class EventController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FileUploadService $fileUploadService
    ) {}

    #[Route('/api/event/create', name: 'app_event_create', methods: ['POST'])]
    public function createEvent(Request $request, UserInterface $user): JsonResponse
    {
        /** @var Member $member */
        $member = $user;

        $title = $request->request->get('title');
        $content = $request->request->get('content');
        $description = $request->request->get('description');
        $eventDateStr = $request->request->get('eventDate');

        if (!$title || !$content || !$eventDateStr) {
            return new JsonResponse(['message' => 'Tytuł, treść i data wydarzenia są wymagane'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $eventDate = new \DateTime($eventDateStr);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => 'Nieprawidłowy format daty'], Response::HTTP_BAD_REQUEST);
        }

        // Generate unique path
        $basePath = Event::generatePath($title);
        $eventPath = $basePath;
        $counter = 1;

        while ($this->entityManager->getRepository(Event::class)->findOneBy(['eventPath' => $eventPath])) {
            $eventPath = $basePath . '-' . $counter;
            $counter++;
        }

        $event = new Event();
        $event->setTitle($title);
        $event->setContent($content);
        $event->setDescription($description);
        $event->setEventDate($eventDate);
        $event->setEventPath($eventPath);
        $event->setVisible(false); // Hidden by default
        $event->setAuthor($member);
        $event->setEditHistory([]);

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

                $event->addFile($result['file']);
            }
        }

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Wydarzenie zostało utworzone pomyślnie',
            'event' => [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'content' => $event->getContent(),
                'description' => $event->getDescription(),
                'eventDate' => $event->getEventDate()->format('Y-m-d H:i:s'),
                'eventPath' => $event->getEventPath(),
                'visible' => $event->isVisible(),
                'author' => $event->getAuthor()->getFullName(),
                'fileCount' => $event->getFiles()->count(),
                'files' => array_map(function(File $file) {
                    return [
                        'id' => $file->getId(),
                        'originalName' => $file->getOriginalName(),
                        'url' => $this->fileUploadService->getFileUrl($file)
                    ];
                }, $event->getFiles()->toArray())
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/event/{id}/edit', name: 'app_event_edit', methods: ['PUT'])]
    public function editEvent(int $id, Request $request, UserInterface $user): JsonResponse
    {
        /** @var Member $member */
        $member = $user;

        $event = $this->entityManager->getRepository(Event::class)->find($id);
        if (!$event) {
            return new JsonResponse(['message' => 'Wydarzenie nie zostało znalezione'], Response::HTTP_NOT_FOUND);
        }

        // Check permissions
        if ($event->getAuthor()->getId() !== $member->getId() &&
            !in_array($member->getRole(), [Member::ROLE_ADMIN, Member::ROLE_MODERATOR])) {
            return new JsonResponse(['message' => 'Brak uprawnień do edycji tego wydarzenia'], Response::HTTP_FORBIDDEN);
        }

        // Save current state to edit history
        $editHistory = $event->getEditHistory() ?? [];
        $editHistory[] = [
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            'editedBy' => $member->getFullName(),
            'changes' => [
                'title' => $event->getTitle(),
                'content' => $event->getContent(),
                'description' => $event->getDescription(),
                'eventDate' => $event->getEventDate()->format('Y-m-d H:i:s')
            ]
        ];

        // Update event
        $title = $request->request->get('title');
        $content = $request->request->get('content');
        $description = $request->request->get('description');
        $eventDateStr = $request->request->get('eventDate');

        if ($title) {
            $event->setTitle($title);
            // Update path if title changed
            $newPath = Event::generatePath($title);
            if ($newPath !== $event->getEventPath()) {
                $basePath = $newPath;
                $counter = 1;
                while ($this->entityManager->getRepository(Event::class)->findOneBy(['eventPath' => $newPath])) {
                    $newPath = $basePath . '-' . $counter;
                    $counter++;
                }
                $event->setEventPath($newPath);
            }
        }

        if ($content) $event->setContent($content);
        if ($description !== null) $event->setDescription($description);

        if ($eventDateStr) {
            try {
                $eventDate = new \DateTime($eventDateStr);
                $event->setEventDate($eventDate);
            } catch (\Exception $e) {
                return new JsonResponse(['message' => 'Nieprawidłowy format daty'], Response::HTTP_BAD_REQUEST);
            }
        }

        // Hide event when edited (unless admin is editing)
        if ($member->getRole() !== Member::ROLE_ADMIN) {
            $event->setVisible(false);
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
            foreach ($event->getFiles() as $oldFile) {
                $this->fileUploadService->deleteFile($oldFile);
            }
            $event->clearFiles();
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

                $event->addFile($result['file']);
            }
        }

        $event->setEditHistory($editHistory);
        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Wydarzenie zostało zaktualizowane',
            'event' => [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'content' => $event->getContent(),
                'description' => $event->getDescription(),
                'eventDate' => $event->getEventDate()->format('Y-m-d H:i:s'),
                'eventPath' => $event->getEventPath(),
                'visible' => $event->isVisible(),
                'author' => $event->getAuthor()->getFullName(),
                'fileCount' => $event->getFiles()->count(),
                'editCount' => count($event->getEditHistory())
            ]
        ]);
    }

    #[Route('/api/admin/event/{id}/visibility', name: 'app_admin_event_visibility', methods: ['PUT'])]
    public function toggleVisibility(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['visible']) || !is_bool($data['visible'])) {
            return new JsonResponse(['message' => 'Pole visible jest wymagane'], Response::HTTP_BAD_REQUEST);
        }

        $event = $this->entityManager->getRepository(Event::class)->find($id);
        if (!$event) {
            return new JsonResponse(['message' => 'Wydarzenie nie zostało znalezione'], Response::HTTP_NOT_FOUND);
        }

        $event->setVisible($data['visible']);
        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Widoczność wydarzenia została zmieniona',
            'event' => [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'eventPath' => $event->getEventPath(),
                'visible' => $event->isVisible()
            ]
        ]);
    }

    #[Route('/api/events/visible', name: 'app_events_visible', methods: ['GET'])]
    public function getVisibleEvents(): JsonResponse
    {
        $events = $this->entityManager->getRepository(Event::class)
            ->findBy(['visible' => true], ['eventDate' => 'ASC']);

        $eventsData = array_map(function(Event $event) {
            return [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'content' => $event->getContent(),
                'description' => $event->getDescription(),
                'eventDate' => $event->getEventDate()->format('Y-m-d H:i:s'),
                'eventPath' => $event->getEventPath(),
                'author' => $event->getAuthor()->getFullName(),
                'fileCount' => $event->getFiles()->count(),
                'files' => array_map(function(File $file) {
                    return [
                        'id' => $file->getId(),
                        'originalName' => $file->getOriginalName(),
                        'url' => $this->fileUploadService->getFileUrl($file)
                    ];
                }, $event->getFiles()->toArray())
            ];
        }, $events);

        return new JsonResponse($eventsData);
    }

    #[Route('/api/events/all', name: 'app_events_all', methods: ['GET'])]
    public function getAllEvents(): JsonResponse
    {
        $events = $this->entityManager->getRepository(Event::class)
            ->findBy([], ['eventDate' => 'DESC']);

        $eventsData = array_map(function(Event $event) {
            return [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'content' => $event->getContent(),
                'description' => $event->getDescription(),
                'eventDate' => $event->getEventDate()->format('Y-m-d H:i:s'),
                'eventPath' => $event->getEventPath(),
                'visible' => $event->isVisible(),
                'author' => $event->getAuthor()->getFullName(),
                'fileCount' => $event->getFiles()->count(),
                'editCount' => count($event->getEditHistory() ?? [])
            ];
        }, $events);

        return new JsonResponse($eventsData);
    }

    #[Route('/api/event/{eventPath}', name: 'app_event_get_by_path', methods: ['GET'])]
    public function getEventByPath(string $eventPath): JsonResponse
    {
        $event = $this->entityManager->getRepository(Event::class)
            ->findOneBy(['eventPath' => $eventPath]);

        if (!$event) {
            return new JsonResponse(['message' => 'Wydarzenie nie zostało znalezione'], Response::HTTP_NOT_FOUND);
        }

        // Only show visible events to public
        if (!$event->isVisible()) {
            return new JsonResponse(['message' => 'Wydarzenie nie jest dostępne'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id' => $event->getId(),
            'title' => $event->getTitle(),
            'content' => $event->getContent(),
            'description' => $event->getDescription(),
            'eventDate' => $event->getEventDate()->format('Y-m-d H:i:s'),
            'eventPath' => $event->getEventPath(),
            'author' => $event->getAuthor()->getFullName(),
            'fileCount' => $event->getFiles()->count(),
            'files' => array_map(function(File $file) {
                return [
                    'id' => $file->getId(),
                    'originalName' => $file->getOriginalName(),
                    'url' => $this->fileUploadService->getFileUrl($file)
                ];
            }, $event->getFiles()->toArray()),
            'editHistory' => $event->getEditHistory()
        ]);
    }
}

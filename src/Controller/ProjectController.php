<?php

namespace App\Controller;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post as ApiPost;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model;
use App\Entity\Project;
use App\Entity\Member;
use App\Entity\Technology;
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
            uriTemplate: '/api/project/create',
            controller: self::class . '::createProject',
            openapi: new Model\Operation(
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string', 'example' => 'Nazwa projektu'],
                                    'description' => ['type' => 'string', 'example' => 'Opis projektu'],
                                    'participants' => ['type' => 'string', 'example' => '["Jan Kowalski", "Anna Nowak"]'],
                                    'startDate' => ['type' => 'string', 'format' => 'date', 'example' => '2024-01-01'],
                                    'endDate' => ['type' => 'string', 'format' => 'date', 'example' => '2024-06-01'],
                                    'technologies' => ['type' => 'string', 'example' => '["PHP", "JavaScript"]'],
                                    'technologyIds' => ['type' => 'string', 'example' => '[1, 2, 3]'],
                                    'projectLink' => ['type' => 'string', 'example' => 'https://example.com'],
                                    'repoLink' => ['type' => 'string', 'example' => 'https://github.com/user/repo'],
                                    'future' => ['type' => 'boolean', 'example' => false],
                                    'file' => ['type' => 'string', 'format' => 'binary'],
                                ],
                                'required' => ['name', 'description']
                            ]
                        ]
                    ])
                )
            ),
            security: "is_granted('ROLE_USER')",
            name: 'app_project_create'
        ),
        new Put(
            uriTemplate: '/api/project/{id}/edit',
            controller: self::class . '::editProject',
            openapi: new Model\Operation(
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'description' => ['type' => 'string'],
                                    'participants' => ['type' => 'string'],
                                    'startDate' => ['type' => 'string', 'format' => 'date'],
                                    'endDate' => ['type' => 'string', 'format' => 'date'],
                                    'technologies' => ['type' => 'string'],
                                    'technologyIds' => ['type' => 'string'],
                                    'projectLink' => ['type' => 'string'],
                                    'repoLink' => ['type' => 'string'],
                                    'future' => ['type' => 'boolean'],
                                    'file' => ['type' => 'string', 'format' => 'binary'],
                                ]
                            ]
                        ]
                    ])
                )
            ),
            security: "is_granted('ROLE_USER')",
            name: 'app_project_edit'
        ),
        new Put(
            uriTemplate: '/api/admin/project/{id}/visibility',
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
            name: 'app_admin_project_visibility'
        ),
        new Get(
            uriTemplate: '/api/projects/visible',
            controller: self::class . '::getVisibleProjects',
            name: 'app_projects_visible'
        ),
        new Get(
            uriTemplate: '/api/projects/all',
            controller: self::class . '::getAllProjects',
            security: "is_granted('ROLE_MODERATOR')",
            name: 'app_projects_all'
        ),
        new Get(
            uriTemplate: '/api/project/{id}',
            controller: self::class . '::getProject',
            security: "is_granted('ROLE_USER')",
            name: 'app_project_get'
        )
    ]
)]
final class ProjectController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FileUploadService $fileUploadService
    ) {}

    #[Route('/api/project/create', name: 'app_project_create', methods: ['POST'])]
    public function createProject(Request $request, UserInterface $user): JsonResponse
    {
        /** @var Member $member */
        $member = $user;

        $name = $request->request->get('name');
        $description = $request->request->get('description');

        if (!$name || !$description) {
            return new JsonResponse(['message' => 'Nazwa i opis są wymagane'], Response::HTTP_BAD_REQUEST);
        }

        $project = new Project();
        $project->setName($name);
        $project->setDescription($description);
        $project->setVisible(false); // Hidden by default
        $project->setFuture($request->request->getBoolean('future', false));

        // Handle participants
        $participantsJson = $request->request->get('participants');
        if ($participantsJson) {
            $participants = json_decode($participantsJson, true);
            if (is_array($participants)) {
                $project->setParticipants($participants);
            }
        }

        // Handle technologies
        $technologiesJson = $request->request->get('technologies');
        if ($technologiesJson) {
            $technologies = json_decode($technologiesJson, true);
            if (is_array($technologies)) {
                $project->setTechnologies($technologies);
            }
        }

        // Handle technology relations
        $technologyIdsJson = $request->request->get('technologyIds');
        if ($technologyIdsJson) {
            $technologyIds = json_decode($technologyIdsJson, true);
            if (is_array($technologyIds)) {
                foreach ($technologyIds as $techId) {
                    $technology = $this->entityManager->getRepository(Technology::class)->find($techId);
                    if ($technology) {
                        $project->addTechnologyRelation($technology);
                    }
                }
            }
        }

        // Handle dates
        $startDateStr = $request->request->get('startDate');
        if ($startDateStr) {
            try {
                $project->setStartDate(new \DateTime($startDateStr));
            } catch (\Exception $e) {
                return new JsonResponse(['message' => 'Nieprawidłowy format daty rozpoczęcia'], Response::HTTP_BAD_REQUEST);
            }
        }

        $endDateStr = $request->request->get('endDate');
        if ($endDateStr) {
            try {
                $project->setEndDate(new \DateTime($endDateStr));
            } catch (\Exception $e) {
                return new JsonResponse(['message' => 'Nieprawidłowy format daty zakończenia'], Response::HTTP_BAD_REQUEST);
            }
        }

        // Handle links
        $projectLink = $request->request->get('projectLink');
        if ($projectLink) $project->setProjectLink($projectLink);

        $repoLink = $request->request->get('repoLink');
        if ($repoLink) $project->setRepoLink($repoLink);

        // Handle file upload
        $uploadedFile = $request->files->get('file');
        if ($uploadedFile) {
            $result = $this->fileUploadService->uploadFile(
                $uploadedFile,
                FileUploadService::CATEGORY_PROJECT_PHOTO,
                FileUploadService::PERMISSION_PUBLIC,
                $member
            );

            if (!$result['success']) {
                return new JsonResponse(['message' => $result['message']], Response::HTTP_BAD_REQUEST);
            }

            $project->setFile($result['file']);
        }

        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Projekt został utworzony pomyślnie',
            'project' => [
                'id' => $project->getId(),
                'name' => $project->getName(),
                'description' => $project->getDescription(),
                'participants' => $project->getParticipants(),
                'technologies' => $project->getTechnologies(),
                'startDate' => $project->getStartDate()?->format('Y-m-d'),
                'endDate' => $project->getEndDate()?->format('Y-m-d'),
                'projectLink' => $project->getProjectLink(),
                'repoLink' => $project->getRepoLink(),
                'future' => $project->isFuture(),
                'visible' => $project->isVisible(),
                'hasFile' => $project->getFile() !== null
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/project/{id}/edit', name: 'app_project_edit', methods: ['PUT'])]
    public function editProject(int $id, Request $request, UserInterface $user): JsonResponse
    {
        /** @var Member $member */
        $member = $user;

        $project = $this->entityManager->getRepository(Project::class)->find($id);
        if (!$project) {
            return new JsonResponse(['message' => 'Projekt nie został znaleziony'], Response::HTTP_NOT_FOUND);
        }

        // Check permissions - admins and moderators can edit any project
        if (!in_array($member->getRole(), [Member::ROLE_ADMIN, Member::ROLE_MODERATOR])) {
            return new JsonResponse(['message' => 'Brak uprawnień do edycji projektu'], Response::HTTP_FORBIDDEN);
        }

        // Update project fields
        $name = $request->request->get('name');
        if ($name) $project->setName($name);

        $description = $request->request->get('description');
        if ($description) $project->setDescription($description);

        $future = $request->request->get('future');
        if ($future !== null) $project->setFuture((bool)$future);

        // Handle participants
        $participantsJson = $request->request->get('participants');
        if ($participantsJson) {
            $participants = json_decode($participantsJson, true);
            if (is_array($participants)) {
                $project->setParticipants($participants);
            }
        }

        // Handle technologies
        $technologiesJson = $request->request->get('technologies');
        if ($technologiesJson) {
            $technologies = json_decode($technologiesJson, true);
            if (is_array($technologies)) {
                $project->setTechnologies($technologies);
            }
        }

        // Handle technology relations
        $technologyIdsJson = $request->request->get('technologyIds');
        if ($technologyIdsJson) {
            // Clear existing relations
            foreach ($project->getTechnologyRelations() as $tech) {
                $project->removeTechnologyRelation($tech);
            }

            $technologyIds = json_decode($technologyIdsJson, true);
            if (is_array($technologyIds)) {
                foreach ($technologyIds as $techId) {
                    $technology = $this->entityManager->getRepository(Technology::class)->find($techId);
                    if ($technology) {
                        $project->addTechnologyRelation($technology);
                    }
                }
            }
        }

        // Handle dates
        $startDateStr = $request->request->get('startDate');
        if ($startDateStr) {
            try {
                $project->setStartDate(new \DateTime($startDateStr));
            } catch (\Exception $e) {
                return new JsonResponse(['message' => 'Nieprawidłowy format daty rozpoczęcia'], Response::HTTP_BAD_REQUEST);
            }
        }

        $endDateStr = $request->request->get('endDate');
        if ($endDateStr) {
            try {
                $project->setEndDate(new \DateTime($endDateStr));
            } catch (\Exception $e) {
                return new JsonResponse(['message' => 'Nieprawidłowy format daty zakończenia'], Response::HTTP_BAD_REQUEST);
            }
        }

        // Handle links
        $projectLink = $request->request->get('projectLink');
        if ($projectLink !== null) $project->setProjectLink($projectLink ?: null);

        $repoLink = $request->request->get('repoLink');
        if ($repoLink !== null) $project->setRepoLink($repoLink ?: null);

        // Hide project when edited (unless admin is editing)
        if ($member->getRole() !== Member::ROLE_ADMIN) {
            $project->setVisible(false);
        }

        // Handle new file upload
        $uploadedFile = $request->files->get('file');
        if ($uploadedFile) {
            // Remove old file if exists
            if ($project->getFile()) {
                $this->fileUploadService->deleteFile($project->getFile());
            }

            $result = $this->fileUploadService->uploadFile(
                $uploadedFile,
                FileUploadService::CATEGORY_PROJECT_PHOTO,
                FileUploadService::PERMISSION_PUBLIC,
                $member
            );

            if (!$result['success']) {
                return new JsonResponse(['message' => $result['message']], Response::HTTP_BAD_REQUEST);
            }

            $project->setFile($result['file']);
        }

        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Projekt został zaktualizowany',
            'project' => [
                'id' => $project->getId(),
                'name' => $project->getName(),
                'description' => $project->getDescription(),
                'participants' => $project->getParticipants(),
                'technologies' => $project->getTechnologies(),
                'startDate' => $project->getStartDate()?->format('Y-m-d'),
                'endDate' => $project->getEndDate()?->format('Y-m-d'),
                'projectLink' => $project->getProjectLink(),
                'repoLink' => $project->getRepoLink(),
                'future' => $project->isFuture(),
                'visible' => $project->isVisible()
            ]
        ]);
    }

    #[Route('/api/admin/project/{id}/visibility', name: 'app_admin_project_visibility', methods: ['PUT'])]
    public function toggleVisibility(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['visible']) || !is_bool($data['visible'])) {
            return new JsonResponse(['message' => 'Pole visible jest wymagane'], Response::HTTP_BAD_REQUEST);
        }

        $project = $this->entityManager->getRepository(Project::class)->find($id);
        if (!$project) {
            return new JsonResponse(['message' => 'Projekt nie został znaleziony'], Response::HTTP_NOT_FOUND);
        }

        $project->setVisible($data['visible']);
        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Widoczność projektu została zmieniona',
            'project' => [
                'id' => $project->getId(),
                'name' => $project->getName(),
                'visible' => $project->isVisible()
            ]
        ]);
    }

    #[Route('/api/projects/visible', name: 'app_projects_visible', methods: ['GET'])]
    public function getVisibleProjects(): JsonResponse
    {
        $projects = $this->entityManager->getRepository(Project::class)
            ->findBy(['visible' => true], ['startDate' => 'DESC']);

        $projectsData = array_map(function(Project $project) {
            return [
                'id' => $project->getId(),
                'name' => $project->getName(),
                'description' => $project->getDescription(),
                'participants' => $project->getParticipants(),
                'technologies' => $project->getTechnologies(),
                'startDate' => $project->getStartDate()?->format('Y-m-d'),
                'endDate' => $project->getEndDate()?->format('Y-m-d'),
                'projectLink' => $project->getProjectLink(),
                'repoLink' => $project->getRepoLink(),
                'future' => $project->isFuture(),
                'hasFile' => $project->getFile() !== null,
                'fileUrl' => $project->getFile() ? $this->fileUploadService->getFileUrl($project->getFile()) : null
            ];
        }, $projects);

        return new JsonResponse($projectsData);
    }

    #[Route('/api/projects/all', name: 'app_projects_all', methods: ['GET'])]
    public function getAllProjects(): JsonResponse
    {
        $projects = $this->entityManager->getRepository(Project::class)
            ->findBy([], ['startDate' => 'DESC']);

        $projectsData = array_map(function(Project $project) {
            return [
                'id' => $project->getId(),
                'name' => $project->getName(),
                'description' => $project->getDescription(),
                'participants' => $project->getParticipants(),
                'technologies' => $project->getTechnologies(),
                'startDate' => $project->getStartDate()?->format('Y-m-d'),
                'endDate' => $project->getEndDate()?->format('Y-m-d'),
                'projectLink' => $project->getProjectLink(),
                'repoLink' => $project->getRepoLink(),
                'future' => $project->isFuture(),
                'visible' => $project->isVisible(),
                'hasFile' => $project->getFile() !== null
            ];
        }, $projects);

        return new JsonResponse($projectsData);
    }

    #[Route('/api/project/{id}', name: 'app_project_get', methods: ['GET'])]
    public function getProject(int $id): JsonResponse
    {
        $project = $this->entityManager->getRepository(Project::class)->find($id);
        if (!$project) {
            return new JsonResponse(['message' => 'Projekt nie został znaleziony'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id' => $project->getId(),
            'name' => $project->getName(),
            'description' => $project->getDescription(),
            'participants' => $project->getParticipants(),
            'technologies' => $project->getTechnologies(),
            'startDate' => $project->getStartDate()?->format('Y-m-d'),
            'endDate' => $project->getEndDate()?->format('Y-m-d'),
            'projectLink' => $project->getProjectLink(),
            'repoLink' => $project->getRepoLink(),
            'future' => $project->isFuture(),
            'visible' => $project->isVisible(),
            'hasFile' => $project->getFile() !== null,
            'fileUrl' => $project->getFile() ? $this->fileUploadService->getFileUrl($project->getFile()) : null,
            'technologyRelations' => array_map(function(Technology $tech) {
                return [
                    'id' => $tech->getId(),
                    'name' => $tech->getName(),
                    'icon' => $tech->getIcon()
                ];
            }, $project->getTechnologyRelations()->toArray())
        ]);
    }
}

<?php

namespace App\Controller;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\OpenApi\Model;
use App\Entity\Technology;
use App\Entity\Member;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/api/technology/create',
            controller: self::class . '::createTechnology',
            openapi: new Model\Operation(
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string', 'example' => 'PHP'],
                                    'description' => ['type' => 'string', 'example' => 'Język programowania'],
                                ],
                                'required' => ['name']
                            ]
                        ]
                    ])
                )
            ),
            security: "is_granted('ROLE_ADMIN')",
            name: 'app_technology_create'
        ),
        new Put(
            uriTemplate: '/api/technology/{id}/edit',
            controller: self::class . '::editTechnology',
            openapi: new Model\Operation(
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string', 'example' => 'PHP'],
                                    'description' => ['type' => 'string', 'example' => 'Język programowania'],
                                ]
                            ]
                        ]
                    ])
                )
            ),
            security: "is_granted('ROLE_ADMIN')",
            name: 'app_technology_edit'
        ),
        new Delete(
            uriTemplate: '/api/technology/{id}',
            controller: self::class . '::deleteTechnology',
            security: "is_granted('ROLE_ADMIN')",
            name: 'app_technology_delete'
        ),
        new Get(
            uriTemplate: '/api/technologies',
            controller: self::class . '::getAllTechnologies',
            name: 'app_technologies_all'
        ),
        new Get(
            uriTemplate: '/api/technology/{id}',
            controller: self::class . '::getTechnology',
            name: 'app_technology_get'
        )
    ]
)]
final class TechnologyController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/api/technology/create', name: 'app_technology_create', methods: ['POST'])]
    public function createTechnology(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['name']) || empty($data['name'])) {
            return new JsonResponse(['message' => 'Nazwa technologii jest wymagana'], Response::HTTP_BAD_REQUEST);
        }

        $name = $data['name'];
        $description = $data['description'] ?? null;

        // Check if technology with this name already exists
        $existingTech = $this->entityManager->getRepository(Technology::class)
            ->findOneBy(['name' => $name]);

        if ($existingTech) {
            return new JsonResponse(['message' => 'Technologia o tej nazwie już istnieje'], Response::HTTP_BAD_REQUEST);
        }

        $technology = new Technology();
        $technology->setName($name);
        $technology->setDescription($description);

        $this->entityManager->persist($technology);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Technologia została utworzona pomyślnie',
            'technology' => [
                'id' => $technology->getId(),
                'name' => $technology->getName(),
                'description' => $technology->getDescription(),
                'icon' => $technology->getIcon()
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/technology/{id}/edit', name: 'app_technology_edit', methods: ['PUT'])]
    public function editTechnology(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $technology = $this->entityManager->getRepository(Technology::class)->find($id);
        if (!$technology) {
            return new JsonResponse(['message' => 'Technologia nie została znaleziona'], Response::HTTP_NOT_FOUND);
        }

        if (isset($data['name']) && !empty($data['name'])) {
            // Check if another technology with this name exists
            $existingTech = $this->entityManager->getRepository(Technology::class)
                ->findOneBy(['name' => $data['name']]);

            if ($existingTech && $existingTech->getId() !== $technology->getId()) {
                return new JsonResponse(['message' => 'Technologia o tej nazwie już istnieje'], Response::HTTP_BAD_REQUEST);
            }

            $technology->setName($data['name']);
        }

        if (isset($data['description'])) {
            $technology->setDescription($data['description']);
        }

        $this->entityManager->persist($technology);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Technologia została zaktualizowana',
            'technology' => [
                'id' => $technology->getId(),
                'name' => $technology->getName(),
                'description' => $technology->getDescription(),
                'icon' => $technology->getIcon()
            ]
        ]);
    }

    #[Route('/api/technology/{id}', name: 'app_technology_delete', methods: ['DELETE'])]
    public function deleteTechnology(int $id): JsonResponse
    {
        $technology = $this->entityManager->getRepository(Technology::class)->find($id);
        if (!$technology) {
            return new JsonResponse(['message' => 'Technologia nie została znaleziona'], Response::HTTP_NOT_FOUND);
        }

        // Check if technology is used in any projects
        if ($technology->getProjects()->count() > 0) {
            return new JsonResponse([
                'message' => 'Nie można usunąć technologii używanej w projektach'
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->remove($technology);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Technologia została usunięta']);
    }

    #[Route('/api/technologies', name: 'app_technologies_all', methods: ['GET'])]
    public function getAllTechnologies(): JsonResponse
    {
        $technologies = $this->entityManager->getRepository(Technology::class)
            ->findBy([], ['name' => 'ASC']);

        $technologiesData = array_map(function(Technology $tech) {
            return [
                'id' => $tech->getId(),
                'name' => $tech->getName(),
                'description' => $tech->getDescription(),
                'icon' => $tech->getIcon(),
                'projectCount' => $tech->getProjects()->count()
            ];
        }, $technologies);

        return new JsonResponse($technologiesData);
    }

    #[Route('/api/technology/{id}', name: 'app_technology_get', methods: ['GET'])]
    public function getTechnology(int $id): JsonResponse
    {
        $technology = $this->entityManager->getRepository(Technology::class)->find($id);
        if (!$technology) {
            return new JsonResponse(['message' => 'Technologia nie została znaleziona'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id' => $technology->getId(),
            'name' => $technology->getName(),
            'description' => $technology->getDescription(),
            'icon' => $technology->getIcon(),
            'projects' => array_map(function($project) {
                return [
                    'id' => $project->getId(),
                    'name' => $project->getName(),
                    'visible' => $project->isVisible()
                ];
            }, $technology->getProjects()->toArray())
        ]);
    }
}

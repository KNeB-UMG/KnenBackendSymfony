<?php

namespace App\Controller;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model;
use App\Entity\Member;
use App\Service\FileUploadService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/api/member/register',
            controller: self::class . '::registerMember',
            openapi: new Model\Operation(
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'email' => ['type' => 'string', 'example' => 'user@example.com'],
                                    'password' => ['type' => 'string', 'example' => 'Password123'],
                                    'firstName' => ['type' => 'string', 'example' => 'John'],
                                    'lastName' => ['type' => 'string', 'example' => 'Doe'],
                                ],
                                'required' => ['email', 'password', 'firstName', 'lastName']
                            ]
                        ]
                    ])
                )
            ),
            name: 'app_member_register'
        ),
        new Post(
            uriTemplate: '/api/admin/member/create',
            controller: self::class . '::createMember',
            openapi: new Model\Operation(
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'email' => ['type' => 'string', 'example' => 'user@example.com'],
                                    'firstName' => ['type' => 'string', 'example' => 'John'],
                                    'lastName' => ['type' => 'string', 'example' => 'Doe'],
                                    'visible' => ['type' => 'boolean', 'example' => false],
                                ],
                                'required' => ['email', 'firstName', 'lastName']
                            ]
                        ]
                    ])
                )
            ),
            security: "is_granted('ROLE_ADMIN')",
            name: 'app_admin_member_create'
        ),
        new Post(
            uriTemplate: '/api/member/login',
            controller: self::class . '::loginMember',
            openapi: new Model\Operation(
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'email' => ['type' => 'string', 'example' => 'user@example.com'],
                                    'password' => ['type' => 'string', 'example' => 'Password123'],
                                ],
                                'required' => ['email', 'password']
                            ]
                        ]
                    ])
                )
            ),
            name: 'app_member_login'
        ),
        new Post(
            uriTemplate: '/api/member/deactivate',
            controller: self::class . '::deactivateOwnAccount',
            security: "is_granted('ROLE_USER')",
            name: 'app_member_deactivate'
        ),
        new Post(
            uriTemplate: '/api/admin/member/{id}/deactivate',
            controller: self::class . '::deactivateAccount',
            security: "is_granted('ROLE_ADMIN')",
            name: 'app_admin_member_deactivate'
        ),
        new Get(
            uriTemplate: '/api/admin/member/{id}',
            controller: self::class . '::getMember',
            security: "is_granted('ROLE_ADMIN')",
            name: 'app_admin_member_get'
        ),
        new Post(
            uriTemplate: '/api/member/activate/{id}',
            controller: self::class . '::activateAccount',
            name: 'app_member_activate'
        ),
        new Put(
            uriTemplate: '/api/admin/member/{id}/role',
            controller: self::class . '::changeRole',
            openapi: new Model\Operation(
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'role' => ['type' => 'string', 'example' => 'ROLE_USER'],

                                ],
                                'required' => ['role']
                            ]
                        ]
                    ])
                )
            ),
            security: "is_granted('ROLE_ADMIN')",
            name: 'app_admin_member_change_role'
        ),
        new Put(
            uriTemplate: '/api/admin/member/{id}/visibility',
            controller: self::class . '::changeVisibility',
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
            name: 'app_admin_member_change_visibility'
        ),
        new Put(
            uriTemplate: '/api/admin/member/{id}/position',
            controller: self::class . '::updatePosition',
            openapi: new Model\Operation(
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'position' => ['type' => 'string', 'example' => 'Przewodniczący'],
                                ],
                                'required' => ['position']
                            ]
                        ]
                    ])
                )
            ),
            security: "is_granted('ROLE_ADMIN')",
            name: 'app_admin_member_update_position'
        ),
        new Post(
            uriTemplate: '/api/member/profile-picture',
            controller: self::class . '::uploadProfilePicture',
            security: "is_granted('ROLE_USER')",
            name: 'app_member_upload_profile_picture'
        ),
        new Get(
            uriTemplate: '/api/members/all',
            controller: self::class . '::getAllMembers',
            security: "is_granted('ROLE_USER')",
            name: 'app_members_all'
        ),
        new Get(
            uriTemplate: '/api/members/visible',
            controller: self::class . '::getAllPublicMembers',
            name: 'app_visible_members_all'

        ),
        new Put(
            uriTemplate: '/api/member/edit',
            controller: self::class . '::editMember',
            openapi: new Model\Operation(
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'description' => ['type' => 'string', 'example' => 'User description'],
                                ]
                            ]
                        ]
                    ])
                )
            ),
            security: "is_granted('ROLE_USER')",
            name: 'app_member_edit'
        ),
        new Put(
            uriTemplate: '/api/admin/member/{id}/edit',
            controller: self::class . '::adminEditMember',
            openapi: new Model\Operation(
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'description' => ['type' => 'string', 'example' => 'User description'],
                                    'firstName'=> ['type' => 'string', 'example' => 'John'],
                                    'lastName'=> ['type' => 'string', 'example' => 'Doe'],
                                    'email'=> ['type' => 'string', 'example' => 'johndoe@example.com'],
                                ]
                            ]
                        ]
                    ])
                )
            ),
            security: "is_granted('ROLE_ADMIN')",
            name: 'app_admin_member_edit'
        ),
        new Post(
            uriTemplate: '/api/member/request-password-reset',
            controller: self::class . '::requestPasswordReset',
            openapi: new Model\Operation(
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'email' => ['type' => 'string', 'example' => 'user@example.com'],
                                ],
                                'required' => ['email']
                            ]
                        ]
                    ])
                )
            ),
            name: 'app_member_request_password_reset'
        ),
        new Post(
            uriTemplate: '/api/member/reset-password',
            controller: self::class . '::resetPassword',
            openapi: new Model\Operation(
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'resetCode' => ['type' => 'string', 'example' => '12345678-1234-1234-1234-123456789012'],
                                    'password' => ['type' => 'string', 'example' => 'NewPassword123'],
                                ],
                                'required' => ['resetCode', 'password', 'password_confirmation']
                            ]
                        ]
                    ])
                )
            ),
            name: 'app_member_reset_password'
        )
    ]
)]
final class MemberController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
    )
    {
    }

    #[Route('/api/member/register ', name: 'app_member_register', methods: 'POST')]
    public function registerMember(Request $request):JsonResponse{
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'], $data['password'], $data['firstName'], $data['lastName'])) {
            return $this->json(['message' => 'Brak wymaganych danych'], Response::HTTP_BAD_REQUEST);
        }

        $email = strtolower($data['email']);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'Niepoprawny format adresu email'], Response::HTTP_BAD_REQUEST);
        }

        $existingMember = $this->entityManager->getRepository(Member::class)->findOneBy(['email' => $email]);
        if ($existingMember) {
            return $this->json(['message' => 'Konto z podanym adresem email już istnieje'], Response::HTTP_BAD_REQUEST);
        }

        $password = $data['password'];
        if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return $this->json([
                'message' => 'Hasło musi składać się z conajmniej 8 znaków, posiadać jedną wielką litere, oraz 1 numer'
            ], Response::HTTP_BAD_REQUEST);
        }

        $firstName = ucfirst(strtolower($data['firstName']));
        $lastName = ucfirst(strtolower($data['lastName']));

        $member = new Member();
        $member->setEmail($email);
        $member->setPassword($this->passwordHasher->hashPassword($member, $password));
        $member->setFirstName($firstName);
        $member->setLastName($lastName);
        $member->setPosition("Członek Koła");
        $member->setDescription(null);
        $member->setRole("ROLE_NONE");
        $member->setIsActive(false);
        $member->setVisible(false);
        $member->setActivationCode(bin2hex(random_bytes(16)));

        $this->entityManager->persist($member);
        $this->entityManager->flush();

        // TODO: Send activation email with the activation link

        return $this->json([
            'message' => 'Użytkownik został pomyślnie zarejestrowany. Sprawdź swoją skrzynkę e-mail, aby aktywować konto.',
        ], Response::HTTP_CREATED);
    }


    #[Route('/api/admin/member/create', name: 'app_admin_member_create', methods: 'POST')]
    public function createMember(Request $request):JsonResponse{
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'], $data['password'], $data['firstName'], $data['lastName'])) {
            return $this->json(['message' => 'Brak wymaganych danych'], Response::HTTP_BAD_REQUEST);
        }

        $email = strtolower($data['email']);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'Niepoprawny format adresu email'], Response::HTTP_BAD_REQUEST);
        }

        $existingMember = $this->entityManager->getRepository(Member::class)->findOneBy(['email' => $email]);
        if ($existingMember) {
            return $this->json(['message' => 'Konto z podanym adresem email już istnieje'], Response::HTTP_BAD_REQUEST);
        }

        $password = $data['password'];
        if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return $this->json([
                'message' => 'Hasło musi składać się z conajmniej 8 znaków, posiadać jedną wielką litere, oraz 1 numer'
            ], Response::HTTP_BAD_REQUEST);
        }

        $firstName = ucfirst(strtolower($data['firstName']));
        $lastName = ucfirst(strtolower($data['lastName']));

        $member = new Member();
        $member->setEmail($email);
        $member->setPassword($this->passwordHasher->hashPassword($member, $password));
        $member->setFirstName($firstName);
        $member->setLastName($lastName);
        $member->setPosition("Członek Koła");
        $member->setDescription(null);
        $member->setRole(Member::ROLE_USER);
        $member->setIsActive(true);
        $member->setVisible(false);
        $member->setActivationCode(null);

        $this->entityManager->persist($member);
        $this->entityManager->flush();

        // TODO: Send email with saying account created

        return $this->json([
            'message' => 'Użytkownik został pomyślnie zarejestrowany. Sprawdź swoją skrzynkę e-mail, aby aktywować konto.',
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/member/login', name: 'app_member_login', methods: 'POST')]
    public function loginMember(Request $request):JsonResponse{
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'], $data['password'])) {
            return new JsonResponse([
                'message' => 'Brak wymaganych danych'
            ], Response::HTTP_BAD_REQUEST);
        }

        $email = strtolower($data['email']);

        $member = $this->entityManager->getRepository(Member::class)->findOneBy(['email' => $email]);

        if (!$member) {
            return new JsonResponse([
                'message' => 'Błąd logowania'
            ], Response::HTTP_FORBIDDEN);
        }
        if($member->getDeactivationDate()!=null){
            if($member->getActivationCode()!=null){
                return new JsonResponse([
                    'message' => 'Aktywuj konto ponownie'
                ], Response::HTTP_UNAUTHORIZED);
            }
            //wysłac maila że możesz aktywować konto ponownie
            return new JsonResponse([
                'message' => 'konto zablokowane permamentnie'
            ], Response::HTTP_UNAUTHORIZED);
        }

        if(!$member->isActive()){
            return new JsonResponse([
                'message' => 'Konto nie zostało aktywowane'
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->passwordHasher->isPasswordValid($member, $data['password'])) {
            return new JsonResponse([
                'message' => 'Nieprawidłowe dane logowania'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $token = $this->jwtManager->create($member);

        return new JsonResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $member->getId(),
                'email' => $member->getEmail(),
                'full_name' => $member->getFullName(),
                'role' => $member->getRole(),
            ],
            'message' => 'Logowanie zakończone pomyślnie'
        ]);

    }

    #[Route('/api/member/deactivate', name: 'app_member_deactivate', methods: 'POST')]
    public function deactivateAccount(UserInterface $user):JsonResponse{

        /** @var Member $member */
        $member = $user;

        if($member->getRole() == Member::ROLE_ADMIN || $member->getRole() == Member::ROLE_MODERATOR) {
            return new JsonResponse([
                'message' => 'Administratorzy i moderatorzy nie mogą dezaktywować swoich kont ze względu na posiadane uprawnienia.'
            ], Response::HTTP_FORBIDDEN);
        }

        $member->setIsActive(false);
        $member->setDeactivationDate(new \DateTime());
        $member->setRole(Member::ROLE_NONE);
        $member->setActivationCode(null);
        $member->setVisible(false);
        $member->setDescription(null);
        $member->setPhoto(null);
        $member->setActivationCode(bin2hex(random_bytes(16)));

        $this->entityManager->persist($member);
        $this->entityManager->flush();

        // TODO: Send deactivation email with reactivation instructions

        return new JsonResponse([
            'message' => 'Twoje konto zostało pomyślnie dezaktywowane. Instrukcje dotyczące reaktywacji zostały wysłane na Twój adres e-mail.'
        ]);
    }

    #[Route('/api/admin/member/{id}/deactivate', name: 'app_admin_member_deactivate', methods: 'POST')]
    public function activateAccount(int $id,UserInterface $user):JsonResponse{
        $member = $this->entityManager->getRepository(Member::class)->findOneBy(['email' => $id]);
        /** @var Member $memberToCheck */
        $memberToCheck=$user;
        if($member->getId()==$memberToCheck->getId()){
            return new JsonResponse([
                'message' => 'Nie możesz dezaktywować swojego konta.'
            ]);
        }
        if($member->getRole()==Member::ROLE_ADMIN){
            return new JsonResponse([
                'message' => 'Nie możesz dezaktywować konta administratora.'
            ]);
        }
        $member->setIsActive(false);
        $member->setDeactivationDate(new \DateTime());
        $member->setRole(Member::ROLE_NONE);
        $member->setActivationCode(null);
        $member->setVisible(false);
        $member->setDescription(null);
        $member->setPhoto(null);
        $member->setActivationCode(bin2hex(random_bytes(16)));
        $this->entityManager->persist($member);

        $this->entityManager->flush();
        // TODO mail że konto dezaktywowane
        return new JsonResponse([
            'message' => 'pomyślnie dezaktywowano konto użytkownika.'
        ]);
    }

    #[Route('/api/admin/member/{id}/role', name: 'app_admin_member_change_role', methods: 'POST')]
    public function changeRole(int $id,UserInterface $user,Request $request):JsonResponse{

        $data = json_decode($request->getContent(), true);

        if (!isset($data['role'])) {
            return new JsonResponse([
                'message' => 'Brak wymaganych danych'
            ]);
        }
        $rolesArray = [Member::ROLE_ADMIN, Member::ROLE_MODERATOR, Member::ROLE_NONE,Member::ROLE_NONE];
        if(!in_array($data['role'], $rolesArray)){
            return new JsonResponse([
                'message' => 'Niepoprawna rola'
            ]);
        }
        $member = $this->entityManager->getRepository(Member::class)->findOneBy(['email' => $id]);
        /** @var Member $memberToCheck */
        $memberToCheck=$user;

        if($member->getRole()==$data['role']){
            return new JsonResponse([
                'message' => 'Użytkownik już posiada wybraną rolę'
            ]);
        }

        if($data['role']==Member::ROLE_ADMIN){
            $members=$this->entityManager->getRepository(Member::class)->findBy(['role'=>Member::ROLE_ADMIN]);
            if(in_array($member, $members) && (count($members)<=1)){
                return new JsonResponse([
                    'message' => 'Nie możesz pozbawić roli jedynego administratora.'
                ]);
            }
        }
        $member->setRole($data['role']);
        $this->entityManager->persist($member);
        return new JsonResponse([
            'message' => 'Pomyślnie zmieniono rolę użytkownika',
            'userId'=>$member->getId(),
            'role'=>$data['role']
        ]);
    }

    #[Route('/api/admin/member/{id}/visibility', name: 'app_admin_member_change_visibility', methods: 'POST')]
    public function changeVisibility(Request $request,int $id):JsonResponse{
        $data = json_decode($request->getContent(), true);
        if (!isset($data['visibility']) || !is_bool($data['visibility'])) {
            return new JsonResponse([
                'message' => 'Brak wymaganych danych'
            ]);
        }

        $member = $this->entityManager->getRepository(Member::class)->findOneBy(['email' => $id]);
        $member->setVisible($data['visibility']);
        $this->entityManager->persist($member);
        return new JsonResponse([
            'message' => 'Pomyślnie zmieniono widoczność użytkownika',
            'userId'=>$member->getId(),
            'visibility'=>$data['visibility']
        ]);
    }

    #[Route('/api/admin/member/{id}/position', name: 'app_admin_member_update_position', methods: 'POST')]
    public function updateMemberPosition(string $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['position']) || !is_string($data['position'])) {
            return new JsonResponse([
                'message' => 'Brak wymaganych danych lub nieprawidłowy format pozycji'
            ], Response::HTTP_BAD_REQUEST);
        }

        $newPosition = $data['position'];

        $validPositions = [
            Member::POSITION_MEMBER,
            Member::POSITION_GUARDIAN,
            Member::POSITION_CHAIRMAN,
            Member::POSITION_VICE_CHAIRMAN,
            Member::POSITION_TREASURER,
            Member::POSITION_EX_MEMBER
        ];

        if (!in_array($newPosition, $validPositions)) {
            return new JsonResponse([
                'message' => 'Nieprawidłowa pozycja'
            ], Response::HTTP_BAD_REQUEST);
        }

        $member = $this->entityManager->getRepository(Member::class)->find($id);

        if (!$member) {
            return new JsonResponse([
                'message' => 'Nie znaleziono użytkownika'
            ], Response::HTTP_NOT_FOUND);
        }

        $uniquePositions = [
            Member::POSITION_GUARDIAN,
            Member::POSITION_CHAIRMAN,
            Member::POSITION_VICE_CHAIRMAN,
            Member::POSITION_TREASURER
        ];

        if (in_array($newPosition, $uniquePositions)) {
            $existingMember = $this->entityManager->getRepository(Member::class)
                ->findOneBy(['position' => $newPosition]);

            if ($existingMember && $existingMember->getId() !== $member->getId()) {
                $existingMember->setPosition(Member::POSITION_MEMBER);
                $this->entityManager->persist($existingMember);
            }

            $member->setRole(Member::ROLE_ADMIN);
        }

        $member->setPosition($newPosition);
        $this->entityManager->persist($member);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Pozycja użytkownika została zaktualizowana',
            'userId' => $member->getId(),
            'position' => $newPosition,
            'role' => $member->getRole()
        ]);
    }

    #[Route('/api/member/profile-picture', name: 'app_member_upload_profile_picture', methods: ['POST'])]
    public function uploadProfilePicture(Request $request, UserInterface $user): JsonResponse
    {
        /** @var Member $member */
        $member = $user;

        // Get FileUploadService from container if not injected
        $fileUploadService = $this->container->get(FileUploadService::class);

        $uploadedFile = $request->files->get('file');
        if (!$uploadedFile) {
            return new JsonResponse(['message' => 'Brak pliku'], Response::HTTP_BAD_REQUEST);
        }

        // Remove old profile picture if exists
        if ($member->getPhoto()) {
            $oldFile = $member->getPhoto();
            $member->setPhoto(null);
            $this->entityManager->persist($member);
            $this->entityManager->flush();

            $fileUploadService->deleteFile($oldFile);
        }

        $result = $fileUploadService->uploadFile(
            $uploadedFile,
            FileUploadService::CATEGORY_PROFILE_PICTURE,
            FileUploadService::PERMISSION_PUBLIC,
            $member,
            512, // Max width for profile pictures
            512  // Max height for profile pictures
        );

        if (!$result['success']) {
            return new JsonResponse(['message' => $result['message']], Response::HTTP_BAD_REQUEST);
        }

        $file = $result['file'];

        // Update member's photo
        $member->setPhoto($file);
        $this->entityManager->persist($member);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Zdjęcie profilowe zostało zaktualizowane',
            'user' => [
                'id' => $member->getId(),
                'full_name' => $member->getFullName(),
                'photo' => [
                    'id' => $file->getId(),
                    'defaultPhoto' => false,
                    'url' => $fileUploadService->getFileUrl($file)
                ]
            ]
        ]);
    }

    #[Route('/api/members/visible', name: 'app_visible_members_all', methods: 'POST')]
    public function getAllPublicMembers(): JsonResponse
    {
        $members = $this->entityManager->getRepository(Member::class)->findBy(['visible' => true, 'isActive' => true]);

        $sortedMembers = $this->sortMembersByPosition($members);

        $formattedMembers = $this->formatMembersResponse($sortedMembers);

        return new JsonResponse($formattedMembers);
    }

    #[Route('/api/members/all', name: 'app_members_all', methods: 'POST')]
    public function getAllMembers(): JsonResponse
    {
        $members = $this->entityManager->getRepository(Member::class)->findAll();

        $sortedMembers = $this->sortMembersByPosition($members);

        $formattedMembers = $this->formatMembersResponse($sortedMembers, true);

        return new JsonResponse($formattedMembers);
    }

    private function sortMembersByPosition(array $members): array
    {
        $positionPriority = [
            Member::POSITION_GUARDIAN => 5,
            Member::POSITION_CHAIRMAN => 4,
            Member::POSITION_VICE_CHAIRMAN => 3,
            Member::POSITION_TREASURER => 2,
            Member::POSITION_MEMBER => 1,
            Member::POSITION_EX_MEMBER => 0
        ];

        usort($members, function (Member $a, Member $b) use ($positionPriority) {
            $priorityA = $positionPriority[$a->getPosition()] ?? 0;
            $priorityB = $positionPriority[$b->getPosition()] ?? 0;

            if ($priorityA === $priorityB) {
                return $a->getLastName() <=> $b->getLastName();
            }

            return $priorityB <=> $priorityA;
        });

        return $members;
    }

    private function formatMembersResponse(array $members, bool $includePrivateData = false): array
    {
        $result = [];

        foreach ($members as $member) {
            $memberData = [
                'id' => $member->getId(),
                'firstName' => $member->getFirstName(),
                'lastName' => $member->getLastName(),
                'position' => $member->getPosition(),
                'photo' => [
                    'defaultPhoto' => $member->getPhoto() === null,
                ],
                'visible' => $member->isVisible()
            ];

            if ($member->getDescription()) {
                $memberData['description'] = $member->getDescription();
            }

            if ($includePrivateData) {
                $memberData['email'] = $member->getEmail();
                $memberData['role'] = $member->getRole();
                $memberData['isActive'] = $member->isActive();

                if ($member->getDeactivationDate()) {
                    $memberData['deactivationDate'] = $member->getDeactivationDate()->format('Y-m-d H:i:s');
                }
            }

            $result[] = $memberData;
        }

        return $result;
    }

    #[Route('/api/member/edit', name: 'app_member_edit', methods: 'POST')]
    public function editMember():JsonResponse{

    }

    #[Route('/api/admin/member/{id}/edit', name: 'app_admin_member_edit', methods: 'POST')]
    public function adminEditMember(Request $request,int $id,EntityManagerInterface $entityManager):JsonResponse{
        $data = json_decode($request->getContent(), true);

        $user = $this->entityManager->getRepository(Member::class)->find($id);

        if (!$user) {
            return new JsonResponse([
                'message' => 'Nie znaleziono użytkownika'
            ], Response::HTTP_NOT_FOUND);
        }
        if (!isset($data['email'])) {
            $email = strtolower($data['email']);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return new JsonResponse(['message' => 'Niepoprawny format adresu email'], Response::HTTP_BAD_REQUEST);
            }

            if($email !== $user->getEmail()) {
                $user->setEmail($email);
                $entityManager->persist($user);
            }
        }

        if (!isset($data['firstName'])) {
          if($data['firstName'] !== $user->getFirstName()) {
              $firstName = ucfirst(strtolower($data['firstName']));
              $user->setFirstName($firstName);
              $entityManager->persist($user);
          }
        }

        if (!isset($data['lastName'])) {
            if($data['lastName'] !== $user->getLastName()) {
                $lastName = ucfirst(strtolower($data['lastName']));
                $user->setLastName($lastName);
                $entityManager->persist($user);
            }
        }
        return new JsonResponse([
            'message' => 'Dane użytkownika pomyślnie zmienione',
            'userId' => $user->getId(),
        ],Response::HTTP_OK);

    }

    #[Route('/api/member/request-password-reset', name: 'app_member_request_password_reset', methods: 'POST')]
    public function requestPasswordReset():JsonResponse{

    }

    #[Route('/api/member/reset-password', name: 'app_member_reset_password', methods: 'POST')]
    public function resetPassword():JsonResponse{

    }


    #[Route('/api/admin/member/{id}', name: 'app_admin_member_get', methods: 'GET')]
    public function getMember(EntityManagerInterface $entityManager,int $id):JsonResponse{
        $user = $this->entityManager->getRepository(Member::class)->find($id);
        if (!$user) {
            return new JsonResponse([
                'message' => 'Nie znaleziono użytkownika'
            ], Response::HTTP_NOT_FOUND);
        }

    }




}

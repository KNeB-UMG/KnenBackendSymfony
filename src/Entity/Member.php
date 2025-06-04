<?php

namespace App\Entity;

use App\Repository\MemberRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: MemberRepository::class)]
class Member implements UserInterface, PasswordAuthenticatedUserInterface
{
    // Role constants
    public const string ROLE_ADMIN = 'ROLE_ADMIN';
    public const string ROLE_MODERATOR = 'ROLE_MODERATOR';
    public const string ROLE_USER = 'ROLE_USER';
    public const string ROLE_NONE = 'ROLE_NONE';

    // Position constants
    public const string POSITION_MEMBER = 'członek koła';
    public const string POSITION_GUARDIAN = 'opiekun';
    public const string POSITION_CHAIRMAN = 'przewodniczący';
    public const string POSITION_VICE_CHAIRMAN = 'wiceprzewodniczący';
    public const string POSITION_TREASURER = 'skarbnik';
    public const string POSITION_EX_MEMBER = 'były członek koła';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $firstName = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $lastName = null;

    #[ORM\Column(type: "string", length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(type: "string")]
    private ?string $password = null;

    #[ORM\Column(type: "string", length: 50)]
    private string $role = self::ROLE_USER;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $position = self::POSITION_MEMBER;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTimeInterface $deactivationDate = null;

    #[ORM\Column(type: "boolean")]
    private bool $isActive = true;

    #[ORM\Column(type: "boolean")]
    private bool $visible = true;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $activationCode = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $passwordResetCode = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(inversedBy: 'profileOf')]
    private ?File $photo = null;

    #[ORM\OneToMany(mappedBy: 'author', targetEntity: Post::class)]
    private Collection $posts;

    #[ORM\OneToMany(mappedBy: 'author', targetEntity: Event::class)]
    private Collection $events;

    #[ORM\OneToMany(mappedBy: 'uploader', targetEntity: File::class)]
    private Collection $uploadedFiles;

    public function __construct()
    {
        $this->posts = new ArrayCollection();
        $this->events = new ArrayCollection();
        $this->uploadedFiles = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;
        return $this;
    }

    public function getPosition(): ?string
    {
        return $this->position;
    }

    public function setPosition(string $position): self
    {
        $this->position = $position;
        return $this;
    }

    public function getDeactivationDate(): ?\DateTimeInterface
    {
        return $this->deactivationDate;
    }

    public function setDeactivationDate(?\DateTimeInterface $deactivationDate): self
    {
        $this->deactivationDate = $deactivationDate;
        return $this;
    }

    public function isIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): self
    {
        $this->visible = $visible;
        return $this;
    }

    public function getActivationCode(): ?string
    {
        return $this->activationCode;
    }

    public function setActivationCode(?string $activationCode): self
    {
        $this->activationCode = $activationCode;
        return $this;
    }

    public function getPasswordResetCode(): ?string
    {
        return $this->passwordResetCode;
    }

    public function setPasswordResetCode(?string $passwordResetCode): self
    {
        $this->passwordResetCode = $passwordResetCode;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getPhoto(): ?File
    {
        return $this->photo;
    }

    public function setPhoto(?File $photo): self
    {
        $this->photo = $photo;
        return $this;
    }

    /**
     * @return Collection<int, Post>
     */
    public function getPosts(): Collection
    {
        return $this->posts;
    }

    public function addPost(Post $post): self
    {
        if (!$this->posts->contains($post)) {
            $this->posts->add($post);
            $post->setAuthor($this);
        }

        return $this;
    }

    public function removePost(Post $post): self
    {
        if ($this->posts->removeElement($post)) {
            // Set the owning side to null (unless already changed)
            if ($post->getAuthor() === $this) {
                $post->setAuthor(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Event>
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function addEvent(Event $event): self
    {
        if (!$this->events->contains($event)) {
            $this->events->add($event);
            $event->setAuthor($this);
        }

        return $this;
    }

    public function removeEvent(Event $event): self
    {
        if ($this->events->removeElement($event)) {
            // Set the owning side to null (unless already changed)
            if ($event->getAuthor() === $this) {
                $event->setAuthor(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, File>
     */
    public function getUploadedFiles(): Collection
    {
        return $this->uploadedFiles;
    }

    public function addUploadedFile(File $file): self
    {
        if (!$this->uploadedFiles->contains($file)) {
            $this->uploadedFiles->add($file);
            $file->setUploader($this);
        }

        return $this;
    }

    public function removeUploadedFile(File $file): self
    {
        if ($this->uploadedFiles->removeElement($file)) {
            // Set the owning side to null (unless already changed)
            if ($file->getUploader() === $this) {
                $file->setUploader(null);
            }
        }

        return $this;
    }

    /**
     * Get all available roles
     *
     * @return array<string>
     */
    public static function getAvailableRoles(): array
    {
        return [
            self::ROLE_ADMIN,
            self::ROLE_MODERATOR,
            self::ROLE_USER,
            self::ROLE_NONE
        ];
    }

    /**
     * Get all available positions
     *
     * @return array<string>
     */
    public static function getAvailablePositions(): array
    {
        return [
            self::POSITION_MEMBER,
            self::POSITION_GUARDIAN,
            self::POSITION_CHAIRMAN,
            self::POSITION_VICE_CHAIRMAN,
            self::POSITION_TREASURER,
            self::POSITION_EX_MEMBER,
        ];
    }



    /**
     * Check if user is active
     *
     * @return bool
     */
    public function isActiveUser(): bool
    {
        return $this->isActive && $this->role !== self::ROLE_NONE;
    }

    /**
     * Get full name
     *
     * @return string
     */
    public function getFullName(): string
    {
        return "{$this->firstName} {$this->lastName}";
    }

    /**
     * Make profile visible
     *
     * @return self
     */
    public function showProfile(): self
    {
        $this->visible = true;
        return $this;
    }

    /**
     * Hide profile
     *
     * @return self
     */
    public function hideProfile(): self
    {
        $this->visible = false;
        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        // Symfony expects an array of roles, so we convert our single role
        return [$this->role];
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    /**
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }
    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Check if user is moderator
     */
    public function isModerator(): bool
    {
        return $this->role === self::ROLE_MODERATOR;
    }

    /**
     * Check if user has moderator or admin role
     */
    public function isModeratorOrAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_MODERATOR, self::ROLE_ADMIN]);
    }

    /**
     * Check if user is active member (not ROLE_NONE)
     */
    public function isActive(): bool
    {
        return $this->isActive && $this->role !== self::ROLE_NONE;
    }
}

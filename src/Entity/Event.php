<?php

namespace App\Entity;

use App\Repository\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[ORM\Entity(repositoryClass: EventRepository::class)]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: "text")]
    private ?string $content = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: "string", length: 255, unique: true)]
    private ?string $eventPath = null;

    #[ORM\Column(type: "boolean")]
    private bool $visible = false;

    #[ORM\Column(type: "datetime")]
    private ?\DateTimeInterface $eventDate = null;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $editHistory = null;

    #[ORM\ManyToMany(targetEntity: File::class, inversedBy: 'events')]
    #[ORM\JoinTable(name: 'event_files')]
    private Collection $files;

    #[ORM\ManyToOne(inversedBy: 'events')]
    #[ORM\JoinColumn(name: "author_id", referencedColumnName: "id")]
    private ?Member $author = null;

    public function __construct()
    {
        $this->files = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
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

    public function getEventPath(): ?string
    {
        return $this->eventPath;
    }

    public function setEventPath(string $eventPath): self
    {
        $this->eventPath = $eventPath;
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

    public function getEventDate(): ?\DateTimeInterface
    {
        return $this->eventDate;
    }

    public function setEventDate(\DateTimeInterface $eventDate): self
    {
        $this->eventDate = $eventDate;
        return $this;
    }

    public function getEditHistory(): ?array
    {
        return $this->editHistory;
    }

    public function setEditHistory(?array $editHistory): self
    {
        $this->editHistory = $editHistory;
        return $this;
    }

    /**
     * @return Collection<int, File>
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function addFile(File $file): self
    {
        if (!$this->files->contains($file)) {
            $this->files->add($file);
        }
        return $this;
    }

    public function removeFile(File $file): self
    {
        $this->files->removeElement($file);
        return $this;
    }

    public function clearFiles(): self
    {
        $this->files->clear();
        return $this;
    }

    // Keep the old getFile() method for backward compatibility
    public function getFile(): ?File
    {
        return $this->files->first() ?: null;
    }

    public function setFile(?File $file): self
    {
        $this->clearFiles();
        if ($file) {
            $this->addFile($file);
        }
        return $this;
    }

    public function getAuthor(): ?Member
    {
        return $this->author;
    }

    public function setAuthor(?Member $author): self
    {
        $this->author = $author;
        return $this;
    }

    /**
     * Generate a unique event path from the title
     */
    public static function generatePath(string $title): string
    {
        // Create a slugger that handles Polish characters
        $slugger = new AsciiSlugger('pl');

        // Convert to lowercase and create slug
        $path = $slugger->slug(strtolower($title))->toString();

        return $path;
    }

    /**
     * Check if the event can be edited by the given member
     */
    public function canBeEditedBy(Member $member): bool
    {
        // Admin and moderator can edit any post
        if ($member->isAdmin() || $member->isModerator()) {
            return true;
        }

        // Author can edit their own post if it's not visible yet
        if ($this->author && $this->author->getId() === $member->getId() && !$this->visible) {
            return true;
        }

        return false;
    }

    /**
     * Check if the event can be deleted by the given member
     */
    public function canBeDeletedBy(Member $member): bool
    {
        // Only admin and moderator can delete posts
        return $member->isAdmin() || $member->isModerator();
    }

    /**
     * Check if the event visibility can be changed by the given member
     */
    public function visibilityCanBeChangedBy(Member $member): bool
    {
        // Only admin can change visibility
        return $member->isAdmin();
    }
}

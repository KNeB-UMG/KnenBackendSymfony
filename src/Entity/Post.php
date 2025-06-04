<?php

namespace App\Entity;

use App\Repository\PostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PostRepository::class)]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: "text")]
    private ?string $content = null;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $editHistory = null;

    #[ORM\Column(type: "boolean")]
    private bool $superEvent = false;

    #[ORM\Column(type: "boolean")]
    private bool $visible = false;

    #[ORM\ManyToMany(targetEntity: File::class, inversedBy: 'posts')]
    #[ORM\JoinTable(name: 'post_files')]
    private Collection $files;

    #[ORM\ManyToOne(inversedBy: 'posts')]
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

    public function getEditHistory(): ?array
    {
        return $this->editHistory;
    }

    public function setEditHistory(?array $editHistory): self
    {
        $this->editHistory = $editHistory;
        return $this;
    }

    public function isSuperEvent(): bool
    {
        return $this->superEvent;
    }

    public function setSuperEvent(bool $superEvent): self
    {
        $this->superEvent = $superEvent;
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
}

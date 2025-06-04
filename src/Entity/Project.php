<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $participants = null;

    #[ORM\Column(type: "date", nullable: true)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: "date", nullable: true)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $technologies = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $projectLink = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $repoLink = null;

    #[ORM\Column(type: "boolean")]
    private bool $visible = false;

    #[ORM\Column(type: "boolean")]
    private bool $future = false;

    #[ORM\ManyToMany(targetEntity: File::class, inversedBy: 'projects')]
    #[ORM\JoinTable(name: 'project_files')]
    private Collection $files;

    #[ORM\ManyToMany(targetEntity: Technology::class, inversedBy: 'projects')]
    #[ORM\JoinTable(name: 'project_technology')]
    private Collection $technologyRelations;

    public function __construct()
    {
        $this->technologyRelations = new ArrayCollection();
        $this->files = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
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

    public function getParticipants(): ?array
    {
        return $this->participants;
    }

    public function setParticipants(?array $participants): self
    {
        $this->participants = $participants;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getTechnologies(): ?array
    {
        return $this->technologies;
    }

    public function setTechnologies(?array $technologies): self
    {
        $this->technologies = $technologies;
        return $this;
    }

    public function getProjectLink(): ?string
    {
        return $this->projectLink;
    }

    public function setProjectLink(?string $projectLink): self
    {
        $this->projectLink = $projectLink;
        return $this;
    }

    public function getRepoLink(): ?string
    {
        return $this->repoLink;
    }

    public function setRepoLink(?string $repoLink): self
    {
        $this->repoLink = $repoLink;
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

    public function isFuture(): bool
    {
        return $this->future;
    }

    public function setFuture(bool $future): self
    {
        $this->future = $future;
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

    /**
     * @return Collection<int, Technology>
     */
    public function getTechnologyRelations(): Collection
    {
        return $this->technologyRelations;
    }

    public function addTechnologyRelation(Technology $technology): self
    {
        if (!$this->technologyRelations->contains($technology)) {
            $this->technologyRelations->add($technology);
        }

        return $this;
    }

    public function removeTechnologyRelation(Technology $technology): self
    {
        $this->technologyRelations->removeElement($technology);
        return $this;
    }
}

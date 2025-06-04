<?php

namespace App\Entity;

use App\Repository\FileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FileRepository::class)]
class File
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $originalName = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $filePath = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $mimeType = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $fileType = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $permissions = null;

    #[ORM\Column]
    private ?int $size = null;

    #[ORM\ManyToOne(inversedBy: 'uploadedFiles')]
    #[ORM\JoinColumn(name: "uploaded_by_id", referencedColumnName: "id")]
    private ?Member $uploader = null;

    #[ORM\OneToMany(mappedBy: 'photo', targetEntity: Member::class)]
    private Collection $profileOf;

    #[ORM\ManyToMany(targetEntity: Project::class, mappedBy: 'files')]
    private Collection $projects;

    #[ORM\ManyToMany(targetEntity: Post::class, mappedBy: 'files')]
    private Collection $posts;

    #[ORM\ManyToMany(targetEntity: Event::class, mappedBy: 'files')]
    private Collection $events;

    public function __construct()
    {
        $this->profileOf = new ArrayCollection();
        $this->projects = new ArrayCollection();
        $this->posts = new ArrayCollection();
        $this->events = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): self
    {
        $this->originalName = $originalName;
        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): self
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getFileType(): ?string
    {
        return $this->fileType;
    }

    public function setFileType(?string $fileType): self
    {
        $this->fileType = $fileType;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getPermissions(): ?string
    {
        return $this->permissions;
    }

    public function setPermissions(?string $permissions): self
    {
        $this->permissions = $permissions;
        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;
        return $this;
    }

    public function getUploader(): ?Member
    {
        return $this->uploader;
    }

    public function setUploader(?Member $uploader): self
    {
        $this->uploader = $uploader;
        return $this;
    }

    /**
     * @return Collection<int, Member>
     */
    public function getProfileOf(): Collection
    {
        return $this->profileOf;
    }

    public function addProfileOf(Member $member): self
    {
        if (!$this->profileOf->contains($member)) {
            $this->profileOf->add($member);
            $member->setPhoto($this);
        }

        return $this;
    }

    public function removeProfileOf(Member $member): self
    {
        if ($this->profileOf->removeElement($member)) {
            // Set the owning side to null (unless already changed)
            if ($member->getPhoto() === $this) {
                $member->setPhoto(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): self
    {
        if (!$this->projects->contains($project)) {
            $this->projects->add($project);
            $project->addFile($this);
        }
        return $this;
    }

    public function removeProject(Project $project): self
    {
        if ($this->projects->removeElement($project)) {
            $project->removeFile($this);
        }
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
            $post->addFile($this);
        }
        return $this;
    }

    public function removePost(Post $post): self
    {
        if ($this->posts->removeElement($post)) {
            $post->removeFile($this);
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
            $event->addFile($this);
        }
        return $this;
    }

    public function removeEvent(Event $event): self
    {
        if ($this->events->removeElement($event)) {
            $event->removeFile($this);
        }
        return $this;
    }
}

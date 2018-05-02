<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @UniqueEntity("keyword")
 * @ORM\Entity(repositoryClass="App\Repository\KeywordRepository")
 */
class Keyword
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $keyword;

    /**
     * @var
     * @ORM\OneToMany(targetEntity="App\Entity\SearchResult", mappedBy="keyword")
     */
    private $searchResults;

    /**
     * @return mixed
     */
    public function getSearchResults()
    {
        return $this->searchResults;
    }

    /**
     * @param mixed $searchResults
     */
    public function setSearchResults($searchResults)
    {
        $this->searchResults = $searchResults;
    }

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $volume;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $cpc;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $competition;

    public function getId()
    {
        return $this->id;
    }

    public function getKeyword(): ?string
    {
        return $this->keyword;
    }

    public function setKeyword(string $keyword): self
    {
        $this->keyword = $keyword;

        return $this;
    }

    public function getVolume(): ?int
    {
        return $this->volume;
    }

    public function setVolume(?int $volume): self
    {
        $this->volume = $volume;

        return $this;
    }

    public function getCpc(): ?int
    {
        return $this->cpc;
    }

    public function setCpc(?int $cpc): self
    {
        $this->cpc = $cpc;

        return $this;
    }

    public function getCompetition(): ?float
    {
        return $this->competition;
    }

    public function setCompetition(?float $competition): self
    {
        $this->competition = $competition;

        return $this;
    }
}

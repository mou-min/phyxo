<?php
/*
 * This file is part of Phyxo package
 *
 * Copyright(c) Nicolas Roudaire  https://www.phyxo.net/
 * Licensed under the GPL version 2.0 license.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Entity;

use App\Repository\UserCacheRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=UserCacheRepository::class)
 */
class UserCache
{
    const ACCESS_IN = 'IN';
    const ACCESS_NOT_IN = 'NOT IN';

    /**
     * @ORM\Id
     * @ORM\OneToOne(targetEntity=User::class, inversedBy="userCache", cascade={"persist", "remove"})
     */
    private $user;

    /**
     * @ORM\Column(type="boolean")
     */
    private $need_update = true;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $cache_update_time;

    /**
     * @ORM\Column(type="text")
     */
    private $forbidden_categories = '[]';

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $nb_total_images = 0;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $last_photo_date;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $nb_available_tags = 0;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $nb_available_comments = 0;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $image_access_type = self::ACCESS_NOT_IN;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $image_access_list = '[]';

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function isNeedUpdate(): ?bool
    {
        return $this->need_update;
    }

    public function setNeedUpdate(bool $need_update): self
    {
        $this->need_update = $need_update;

        return $this;
    }

    public function getCacheUpdateTime(): ?int
    {
        return $this->cache_update_time;
    }

    public function setCacheUpdateTime(?int $cache_update_time): self
    {
        $this->cache_update_time = $cache_update_time;

        return $this;
    }

    public function getForbiddenCategories(): array
    {
        return json_decode($this->forbidden_categories, true);
    }

    public function setForbiddenCategories(array $forbidden_categories = []): self
    {
        $this->forbidden_categories = json_encode($forbidden_categories);

        return $this;
    }

    public function getNbTotalImages(): ?int
    {
        return $this->nb_total_images;
    }

    public function setNbTotalImages(?int $nb_total_images): self
    {
        $this->nb_total_images = $nb_total_images;

        return $this;
    }

    public function getLastPhotoDate(): ?\DateTimeInterface
    {
        return $this->last_photo_date;
    }

    public function setLastPhotoDate(?\DateTimeInterface $last_photo_date): self
    {
        $this->last_photo_date = $last_photo_date;

        return $this;
    }

    public function getNbAvailableTags(): ?int
    {
        return $this->nb_available_tags;
    }

    public function setNbAvailableTags(?int $nb_available_tags): self
    {
        $this->nb_available_tags = $nb_available_tags;

        return $this;
    }

    public function getNbAvailableComments(): ?int
    {
        return $this->nb_available_comments;
    }

    public function setNbAvailableComments(?int $nb_available_comments): self
    {
        $this->nb_available_comments = $nb_available_comments;

        return $this;
    }

    public function getImageAccessType(): ?string
    {
        return $this->image_access_type;
    }

    public function setImageAccessType(string $image_access_type): self
    {
        $this->image_access_type = $image_access_type;

        return $this;
    }

    public function getImageAccessList(): array
    {
        return json_decode($this->image_access_list, true);
    }

    public function setImageAccessList(array $image_access_list = []): self
    {
        $this->image_access_list = json_encode($image_access_list);

        return $this;
    }
}

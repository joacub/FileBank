<?php

namespace FileBank\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use FileBank\Entity\Keyword;
use FileBank\Entity\Version;

/**
 * File entity.
 *
 * @ORM\Entity
 * @ORM\Table(name="filebank")
 * @property int $id
 * @property string $name
 * @property int $size
 * @property string $mimetype
 * @property string $isactive
 * @property string $savepath
 * @property ArrayCollection $keywords
 * @property int $versionid
 */
class FileInS3 
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer");
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\OneToOne(targetEntity="FileBank\Entity\File")
     */
    protected $file;
    
	/**
     * @return the $id
     */
    public function getId()
    {
        return $this->id;
    }

	/**
     * @param number $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

	/**
     * @return the $file
     */
    public function getFile()
    {
        return $this->file;
    }

	/**
     * @param field_type $file
     */
    public function setFile($file)
    {
        $this->file = $file;
    }

    
    
}


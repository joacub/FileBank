<?php

namespace FileBank\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use FileBank\Entity\Keyword;
use FileBank\Entity\Version;
use Gedmo\Mapping\Annotation as Gedmo;

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
class File 
{
    /**
     * Default constructor, initializes collections
     */
    public function __construct() 
    {
        $this->keywords = new ArrayCollection();
    }

    /**
     * @ORM\Id
     * @ORM\Column(type="integer");
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\Column(type="string")
     */
    private $name;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $title;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $caption;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $description;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $alt;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $height;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $width;
    /**
     * @ORM\Column(type="array", nullable=true)
     */
    private $exif = [
        'aperture' => '0',
        'credit' => '',
        'camera' => '',
        'caption' => '',
        'created_timestamp' => '0',
        'copyright' => '',
        'focal_length' => '0',
        'iso' => '0',
        'shutter_speed' => '0',
        'title' => '',
        'orientation' => '0',
        'keywords' => [],
    ];

    /**
     * @ORM\Column(type="integer")
     */
    private $size;

    /**
     * @ORM\Column(type="string")
     */
    private $mimetype;

    /**
     * @ORM\Column(type="integer")
     */
    private $isactive;

    /**
     * @ORM\Column(type="string")
     */
    private $savepath;

    /**
     * @ORM\OneToMany(targetEntity="FileBank\Entity\Keyword", mappedBy="file")
     * @ORM\OrderBy({"id" = "ASC"})
     * @var \Doctrine\Common\Collections\ArrayCollection
     */
    private $keywords;
    
    /**
     * @ORM\OneToMany(targetEntity="FileBank\Entity\Version", mappedBy="file")
     * @ORM\OrderBy({"id" = "ASC"})
     * @var \Doctrine\Common\Collections\ArrayCollection
     */
    private $versions;
    
    /**
     * @ORM\OneToOne(targetEntity="FileBank\Entity\Version", inversedBy="versionfile", cascade={"persist", "remove"})
     * @ORM\JoinColumn(name="version_id", referencedColumnName="id")
     * @var \FileBank\Entity\Version
     */
    private $version;
    
    /**
     * @var string $downloadUrl 
     */
    private $url;
    
    /**
     * @var string $downloadUrl
     */
    private $downloadUrl;
    
    /**
     * @var string $absolutePath
     */
    private $absolutePath;

    /**
     *
     * @ORM\Column(type="datetime")
     * @Gedmo\Timestampable(on="create")
     */
    private $dateCreated;
    
    /**
     * Getter for the file id
     * 
     * @return int 
     */
    public function getId() 
    {
        return $this->id;
    }

    /**
     * Setter for the file id
     * 
     * @param int $value 
     */
    public function setId($value) 
    {
        $this->id = $value;
    }

    /**
     * Getter for the file name
     * 
     * @return string 
     */
    public function getName() 
    {
        return $this->name;
    }

    /**
     * Setter for the file name
     * 
     * @param string $value 
     */
    public function setName($value) 
    {
        $this->name = $value;
    }

    /**
     * Getter for the file size
     * 
     * @return int
     */
    public function getSize() 
    {
        return $this->size;
    }

    /**
     * Setter for the file size
     * 
     * @param int $value 
     */
    public function setSize($value) 
    {
        $this->size = $value;
    }

    /**
     * Getter for the file mimetype
     * 
     * @return string 
     */
    public function getMimetype() 
    {
        return $this->mimetype;
    }

    /**
     * Setter for the file mimetype
     * 
     * @param int $value 
     */
    public function setMimetype($value) 
    {
        $this->mimetype = $value;
    }

    /**
     * Getter for the file's active status
     * 
     * @return int 
     */
    public function getIsActive() 
    {
        return $this->isactive;
    }

    /**
     * Setter for the file's active status
     * 
     * @param int $value 
     */
    public function setIsActive($value) 
    {
        $this->isactive = $value;
    }

    /**
     * Getter for the file's save path
     * 
     * @return string 
     */
    public function getSavePath() 
    {
        return $this->savepath;
    }

    /**
     * Setter for the file's save path
     * 
     * @param string $value 
     */
    public function setSavePath($value) 
    {
        $this->savepath = $value;
    }
    
    /**
     * 
     * @return \FileBank\Entity\Version
     */
    public function getVersion()
    {
        return $this->version;
    }
    
    public function setVersion(Version $version = null)
    {
        $this->version = $version;
        
        return $this;
    }

    /**
     * Getter for the file's keywords
     * 
     * @return \Doctrine\Common\Collections\ArrayCollection 
     */
    public function getKeywords() 
    {
        return $this->keywords;
    }
    
    /**
     * Setter for the file's keywords
     */
    public function setKeywords(Array $keywords) 
    {
        $this->keywords->clear();
        foreach ($keywords as $keyword) {
            if ($keyword instanceof Keyword) {
                $this->keywords->add($keyword);
            }
        }
    }
    
    public function addKeywords(Array $keywords)
    {
    	foreach ($keywords as $keyword) {
    		if ($keyword instanceof Keyword) {
    			$this->keywords->add($keyword);
    		}
    	}
    }
    
    /**
     * Getter for the file's versions
     *
     * @return \Doctrine\Common\Collections\ArrayCollection
     */
    public function getVersions()
    {
        return $this->versions;
    }
    
    public function setVersions(Array $versions)
    {
        $this->versions->clear();
        foreach ($versions as $version) {
            if ($version instanceof FileBank\Entity\Version) {
                $this->versions->add($version);
            }
        }
    }

    /**
     * Getter for the file's download URL
     * 
     * @return string 
     */
    public function getUrl() 
    {
        return $this->url;
    }

    /**
     * Setter for the file's download URL
     * 
     * @param string $value
     */
    public function setUrl($value) 
    {
        $this->url = $value;
    }
    
    /**
     * Getter for the file's download URL
     *
     * @return string
     */
    public function getDownloadUrl()
    {
    	return $this->downloadUrl;
    }
    
    /**
     * Setter for the file's download URL
     *
     * @param string $value
     */
    public function setDownloadUrl($value)
    {
    	$this->downloadUrl = $value;
    }
    
    /**
     * Getter for the file's download URL
     *
     * @return string
     */
    public function getAbsolutePath()
    {
        return $this->absolutePath;
    }
    
    /**
     * Setter for the file's download URL
     *
     * @param string $value
     */
    public function setAbsolutePath($value)
    {
        $this->absolutePath = $value;
    }

    /**
     * Convert the object to an array.
     *
     * @return array
     */
    public function getArrayCopy() 
    {
        return get_object_vars($this);
    }

    /**
     * Populate from an array.
     *
     * @param array $data
     */
    public function populate($data = array()) 
    {
        $this->setName($data['name']);
        $this->setSize($data['size']);
        $this->setMimetype($data['mimetype']);
        $this->setIsActive($data['isactive']);
        $this->setSavePath($data['savepath']);
    }

    /**
     * @return mixed
     */
    public function getDateCreated()
    {
        return $this->dateCreated;
    }

    /**
     * @param mixed $dateCreated
     */
    public function setDateCreated($dateCreated)
    {
        $this->dateCreated = $dateCreated;
    }

    /**
     * @return int
     */
    public function getVersionid(): int
    {
        return $this->versionid;
    }

    /**
     * @param int $versionid
     */
    public function setVersionid(int $versionid)
    {
        $this->versionid = $versionid;
    }

    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param mixed $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @return mixed
     */
    public function getCaption()
    {
        return $this->caption;
    }

    /**
     * @param mixed $caption
     */
    public function setCaption($caption)
    {
        $this->caption = $caption;
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param mixed $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return mixed
     */
    public function getAlt()
    {
        return $this->alt;
    }

    /**
     * @param mixed $alt
     */
    public function setAlt($alt)
    {
        $this->alt = $alt;
    }

    /**
     * @return mixed
     */
    public function getHeight()
    {
        return $this->height;
    }

    /**
     * @param mixed $height
     */
    public function setHeight($height)
    {
        $this->height = $height;
    }

    /**
     * @return mixed
     */
    public function getWidth()
    {
        return $this->width;
    }

    /**
     * @param mixed $width
     */
    public function setWidth($width)
    {
        $this->width = $width;
    }

    /**
     * @return mixed
     */
    public function getExif()
    {
        return $this->exif;
    }

    /**
     * @param mixed $exif
     */
    public function setExif($exif)
    {
        $this->exif = $exif;
    }


}


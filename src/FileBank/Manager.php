<?php

namespace FileBank;

use FileBank\Entity\File;
use FileBank\Entity\Keyword;
use Doctrine\ORM\Tools\SchemaValidator;
use Zend\ServiceManager\ServiceLocatorInterface;
use Doctrine\ORM\EntityManager;
use Zend\View\Helper\Url;
use Zend\Json\Json;
use Nette\Diagnostics\Debugger;
use Doctrine\Common\Collections\Expr\Comparison;
use FileBank\Entity\Version;
use Zend\Debug\Debug;

class Manager
{
    /**
     * @var Array 
     */
    protected $params;

    /**            
     * @var \Doctrine\ORM\EntityManager
     */                
    protected $em;
    
    /**
     * @var array
     */
    protected $cache;
    
    /**
     * @var FileBank\Entity\File
     */
    protected $file;
    
    /**
     * 
     * @var ServiceLocatorInterface
     */
    protected $sl;
    
    /**
     * 
     * @var \WebinoImageThumb\Module
     */
    protected $thumbnailer;
    
    /**
     * true = obligatorio
     * null = opcional con valor por defecto
     * false = opcional
     * @var unknown
     */
    protected $versionOptions = array(
        'resize' => array('maxWidth' => 0, 'maxHeight' => 0), 
        'adaptiveResize' => array('width' => true, 'height' => true),
        'resizePercent' => array('percent' => 0),
        'cropFromCenter' => array('cropWidth' => true, 'cropHeight' => null),
        'crop' => array('startX' => true, 'startY' => true, 'cropWidth' => true, 'cropHeight' => true, ),
        'rotateImage' => array('direction' => 'CW'),
        'rotateImageNDegrees' => array('degrees' => true),
        'setFormat' => array('format' => null)
    );
    
    protected $thumbnailerDefaultOptions = array(
        'resizeUp'				=> false,
        'jpegQuality'			=> 100,
        'correctPermissions'	=> false,
        'preserveAlpha'			=> true,
        'alphaMaskColor'		=> array (255, 255, 255),
        'preserveTransparency'	=> true,
        'transparencyMaskColor'	=> array (0, 0, 0)
    );
    
    protected $filesPreparedToRemove;
    
    protected $versionsPreparedToRemove;
    
    /**
     * Set the Module specific configuration parameters
     * 
     * @param Array $params
     * @param \Doctrine\ORM\EntityManager $em 
     */
    public function __construct($params, EntityManager $em, ServiceLocatorInterface $sl) {
        $this->params = $params;
        $this->em = $em;
        $this->cache = array();
        $this->sl = $sl;
        $this->thumbnailer = $this->sl->get('WebinoImageThumb');
    }
    
    /**
     * Get the FileBank's root folder
     * 
     * @return string 
     */
    public function getRoot() 
    {
        return realpath($this->params['filebank_folder']);
    }
    
    /**
     * Get the file entity based on ID
     * 
     * @param integer $fileId
     * @return \FileBank\Entity\File 
     * @throws \Exception 
     */
    
    public function getFileById($fileId)
    {
        // Get the entity from cache if available
        if (isset($this->cache[$fileId])) {
            $entity = $this->cache[$fileId];
        } else {
            $pluginUrl = $this->sl->get('viewrenderer')->getEngine()->plugin('url');
            $pluginUrl instanceof Url;
            $entity = $this->em->find('FileBank\Entity\File', $fileId);
            if($entity)
                $this->generateDynamicParameters($entity);
        }
        
        if (!$entity) {
            throw new \Exception('File does not exist.', 404);
        }
        
        // Cache the file entity so we don't have to access db on each call
        // Enables to get multiple entity's properties at different times
        $this->cache[$fileId] = $entity;
        return $entity;
    }
    
    /**
     * Get array of file entities based on given keyword
     * 
     * @param Array $keywords
     * @return Array
     * @throws \Exception 
     */
    public function getFilesByKeywords($keywords)
    {
        // Create unique ID of the array for cache
        $id = md5(serialize($keywords));
        
        // Change all given keywords to lowercase
        $keywords = array_map('strtolower', $keywords );
        
        // Get the entity from cache if available
        if (isset($this->cache[$id])) {
            $entities = $this->cache[$id];
        } else {
            $pluginUrl = $this->sl->get('viewrenderer')->getEngine()->plugin('url');
            $pluginUrl instanceof Url;
            $list = "'" . implode("','", $keywords) . "'";
            
            $q = $this->em->createQuery(
                    "select f from FileBank\Entity\File f, FileBank\Entity\Keyword k
                     where k.file = f
                     and k.value in (" . $list . ")"
                    );
            
            $entities = $q->getResult();
            
            foreach ($entities as $e) {
                $e instanceof \FileBank\Entity\File;
                if($e)
                    $this->generateDynamicParameters($e);
            }
            
            // Cache the file entity so we don't have to access db on each call
            // Enables to get multiple entity's properties at different times
            $this->cache[$id] = $entities;
            
            return $entities;
        }
        
        
        return $entities;
    }
    
    /**
     * Save file to FileBank database
     * 
     * @param string $sourceFilePath
     * @return File
     * @throws \Exception 
     */
    public function save($sourceFilePath, Array $keywords = null)
    {
        if(is_array($sourceFilePath)) {
            $_sourceFilePath = array_shift($sourceFilePath);
            $fileName = array_shift($sourceFilePath);
            $sourceFilePath = $_sourceFilePath;
        } else {
            $fileName = basename($sourceFilePath);
        }
        
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimetype = $finfo->buffer(file_get_contents($sourceFilePath));
        $hash     = md5(microtime(true) . $fileName);
        $savePath = substr($hash,0,1).'/'.substr($hash,1,1).'/';

        $this->file = new File();
        $this->file->setName($fileName);
        $this->file->setMimetype($mimetype);
        $this->file->setSize($this->fixIntegerOverflow(filesize($sourceFilePath)));
        $this->file->setIsActive($this->params['default_is_active']);
        $this->file->setSavepath($savePath . $hash);
        
        if($keywords !== null)
            $this->addKeywordsToFile($keywords);
        
        $this->em->persist($this->file);
        $this->em->flush();
        
        $this->generateDynamicParameters($this->file);
        
        $absolutePath = $this->getRoot() . DIRECTORY_SEPARATOR . $savePath . $hash;
        
        try {
            $this->createPath($absolutePath, $this->params['chmod'], true);
            copy($sourceFilePath, $absolutePath);
        } catch (\Exception $e) {
            throw new \Exception('File cannot be saved.');
        }

        return $this->file;
    }
    
    /**
     * 
     * @param unknown $fileId
     * @return boolean
     */
    public function removeById($fileId)
    {
        $e = $this->getFileById($fileId);
        $this->remove($e);
        
        
        return ($e->getId() === null);
    }
    
    /**
     * 
     * @param File $e
     * @throws Exception
     * @return \FileBank\Manager
     */
    public function remove(File $e)
    {
        $this->_remove($e);
        try {
            $this->em->flush();
            $this->_removeCurrentFiles();
            $this->_removeCurrentVersions();
        } catch (\Exception $exception) {
            /*Debug::dump($exception->getMessage());
             exit;*/
            throw $exception;
            //algo fue mal y no se borro nada
        }
        
        return $this;
    }
    
    /**
     * 
     * @param File $e
     * @return \FileBank\Manager
     */
    protected function _remove(File $e)
    {
        $versions = $e->getVersions();
        
        if($versions->count() > 0) {
            foreach($versions as $version) {
                $this->versionsPreparedToRemove[] = $version;
                if($version->getVersionFile()) {
                    $version->setFile(null);
                    $this->_remove($version->getVersionFile());
                }
            }
        }
        
        $this->filesPreparedToRemove[] = $e->getAbsolutePath();
        $this->em->remove($e);
        $this->removeKeywordsToFile($e);
        
        return $this;
        
    }
    
    protected function _removeCurrentFiles()
    {
        if($this->filesPreparedToRemove) {
            foreach($this->filesPreparedToRemove as $file) {
                if(file_exists($file))
                    unset($file);
            }
        }
        
        $this->filesPreparedToRemove = array();
    }
    
    protected function _removeCurrentVersions()
    {
        if($this->versionsPreparedToRemove) {
            foreach($this->versionsPreparedToRemove as $version) {
                $this->em->remove($version);
            }
        }
    
        $this->em->flush();
        $this->filesPreparedToRemove = array();
    }
    
    /**
     * Attach keywords to file entity
     * 
     * @param array $keywords
     * @param FileBank\Entity\File $fileEntity
     * @return FileBank\Entity\File 
     */
    protected function addKeywordsToFile($keywords) 
    {
        $keywordEntities = array();
        
        foreach ($keywords as $word) {
            $keyword = new Keyword();
            $keyword->setValue(strtolower($word));
            $keyword->setFile($this->file);
            $this->em->persist($keyword);
            
            $keywordEntities[] = $keyword;
        }
        $this->file->setKeywords($keywordEntities);
    }
    
    /**
     * Attach keywords to file entity
     *
     * @param \FileBank\Entity\File $fileEntity
     * @return \FileBank\Manager
     */
    protected function removeKeywordsToFile($fileEntity)
    {
        $keywords = $fileEntity->getKeywords();
        
        if($keywords->count() > 0) {
            foreach($keywords as $keyword) {
                $this->em->remove($keyword);
            }
        }
        
        return $this;
    }
    
    /**
     * 
     * @param File $file
     * @param array $version
     * @return Ambigous <\FileBank\Ambigous, \FileBank\Entity\File, \FileBank\FileBank\Entity\File>
     */
    public function getVersion(File $file, Array $version)
    {
        //dejamos solo los que nos sirven
        $options = $this->filterOptions($version, $this->versionOptions);
        
        $verionEncode = $this->getVersionEncode($options);
        
        $versions = $file->getVersions();
        $versions instanceof \Doctrine\ORM\PersistentCollection;
        if($versions !== null && $versions->count() > 0) {
            
            $criteria = new \Doctrine\Common\Collections\Criteria();
            
            $collection = $versions->matching($criteria->where(new Comparison('value', Comparison::EQ, $verionEncode))->where(new Comparison('file', Comparison::EQ, $file)));
            
            if($collection->count() > 0) {
                return $this->generateDynamicParameters($collection->current()->getVersionFile());
            }
        }
        
        return $this->createVersion($file, $options);
    }
    
    /**
     * 
     * @param File $file
     * @param array $version
     * @return Ambigous <\FileBank\Entity\File, \FileBank\FileBank\Entity\File>
     */
    public function createVersion(File $file, Array $versionOptions)
    {
        $version = $this->save(array($file->getAbsolutePath(), $file->getName()));
        
        $thumb = $this->thumbnailer->create($version->getAbsolutePath());
        
        foreach($versionOptions as $methods) {
            foreach($methods as $method => $values) {
                call_user_func_array(array($thumb, $method), $values);
            }
        }
        
        $thumb->save($version->getAbsolutePath());
        
        $version->setSize(filesize($version->getAbsolutePath()));
        
        $versionEntity = new Version();
        
        $versionEntity->setFile($file)->setValue($this->getVersionEncode($versionOptions));
        
        $this->em->persist($versionEntity);
        $this->em->persist($version->setVersion($versionEntity));
        $this->em->flush();
        
        return $version;
    }
    
    public function getVersionEncode($version)
    {
        return md5(Json::encode($version));
    }
    
    public function filterOptions($options, $defaultOptions)
    {
        $_options = array();
        foreach($options as $values) {
            $_options[] = $this->array_intersect_key_recursive($values, $defaultOptions);
        }
        
        return $_options;
    }
    
    /**
     * calculates intersection of two arrays like array_intersect_key but recursive
     *
     * @param  array/mixed  master array
     * @param  array        array that has the keys which should be kept in the master array
     * @return array/mixed  cleand master array
     */
    function array_intersect_key_recursive($master, $mask) 
    {
        if (!is_array($master)) { return $master; }
        foreach ($master as $k=>$v) {
            if (!isset($mask[$k])) { unset ($master[$k]); continue; } // remove value from $master if the key is not present in $mask
            if (is_array($mask[$k])) { $master[$k] = $this->array_intersect_key_recursive($master[$k], $mask[$k]); } // recurse when mask is an array
            // else simply keep value
        }
        return $master;
    }
    
    /**
     * Add dynamic data into the entity
     * 
     * @param FileBank\Entity\File $file
     * @param Array $linkOptions
     * @return FileBank\Entity\File
     */
    private function generateDynamicParameters(File $file)
    {
        $urlHelper = $this->sl->get('viewrenderer')->getEngine()->plugin('url');
        $file->setUrl(
            $urlHelper('FileBank/View', array('id' => $file->getId(), 'name' => $file->getName()))
        );
        
        $file->setAbsolutePath(
            $this->getRoot() . DIRECTORY_SEPARATOR . $file->getSavePath()
        );
        
        return $file;
    }
    
    /**
     * Create path recursively
     * 
     * @param string $path
     * @param string $mode
     * @param boolean $isFileIncluded 
     * @return boolean
     */
    protected function createPath($path, $mode, $isFileIncluded)
    {
        if (!is_dir(dirname($path))) {
            if ($isFileIncluded) {
                mkdir(dirname($path), $mode, true);
            } else {
                mkdir($path, $mode, true);
            }
        }
    }
    
    protected function fixIntegerOverflow($size) {
        if ($size < 0) {
            $size += 2.0 * (PHP_INT_MAX + 1);
        }
        return $size;
    }
}

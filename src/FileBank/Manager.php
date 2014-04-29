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
use FileBank\Entity\FileInS3;

class Manager
{

    /**
     *
     * @var Array
     */
    protected $params;

    /**
     *
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     *
     * @var array
     */
    protected $cache;

    /**
     *
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
     * 
     * @var unknown
     */
    protected $versionOptions = array(
        'resize' => array(
            'maxWidth' => 0,
            'maxHeight' => 0
        ),
        'adaptiveResize' => array(
            'width' => true,
            'height' => true
        ),
        'resizePercent' => array(
            'percent' => 0
        ),
        'cropFromCenter' => array(
            'cropWidth' => true,
            'cropHeight' => null
        ),
        'crop' => array(
            'startX' => true,
            'startY' => true,
            'cropWidth' => true,
            'cropHeight' => true
        ),
        'rotateImage' => array(
            'direction' => 'CW'
        ),
        'rotateImageNDegrees' => array(
            'degrees' => true
        ),
        'setFormat' => array(
            'format' => null
        )
    );

    protected $thumbnailerDefaultOptions = array(
        'resizeUp' => false,
        'jpegQuality' => 100,
        'correctPermissions' => false,
        'preserveAlpha' => true,
        'alphaMaskColor' => array(
            255,
            255,
            255
        ),
        'preserveTransparency' => true,
        'transparencyMaskColor' => array(
            0,
            0,
            0
        )
    );

    protected $filesPreparedToRemove;

    protected $versionsPreparedToRemove;

    /**
     * Set the Module specific configuration parameters
     *
     * @param Array $params            
     * @param \Doctrine\ORM\EntityManager $em            
     */
    public function __construct($params, EntityManager $em, ServiceLocatorInterface $sl)
    {
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

    public function getFilesNotInAwsS3($limit = 1000)
    {
        $repo = $this->em->getRepository('FileBank\Entity\FileInS3');
        $qb = $repo->createQueryBuilder('a');
        
        $repoFileInS3 = $this->em->getRepository('FileBank\Entity\FileInS3');
        $qbfs3 = $repoFileInS3->createQueryBuilder('s3');
        $qbfs3->select('IDENTITY(s3.file)');
        
        $qb->where($qb->expr()
            ->notIn('m.id', $qbfs3->getDQL()));
        $files = $qb->setMaxResults($limit)
            ->getQuery()
            ->getResult();
        
        return $files;
    }
    
    public function fileExistInS3(File $file)
    {
        $repo = $this->em->getRepository('FileBank\Entity\FileInS3');
        
        $result = $repo->findOneBy(array('file' => $file));
        
         if(!$result instanceof FileInS3) {
         	if(file_exists($file->getAbsolutePath())) {
         	    $fileInS3Entity = new FileInS3();
         	    $fileInS3Entity->setFile($file);
         	    $this->em->persist($fileInS3Entity);
         	    $this->em->flush($fileInS3Entity);
         		return true;
         	} 
         }
         
         return false;
    
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
            $pluginUrl = $this->sl->get('viewrenderer')
                ->getEngine()
                ->plugin('url');
            $pluginUrl instanceof Url;
            $entity = $this->em->find('FileBank\Entity\File', $fileId);
            if ($entity)
                $this->generateDynamicParameters($entity);
        }
        
        if (! $entity) {
            throw new \Exception('File does not exist.', 404);
        }
        
        // Cache the file entity so we don't have to access db on each call
        // Enables to get multiple entity's properties at different times
        $this->cache[$fileId] = $entity;
        return $entity;
    }

    /**
     * Get the file entity based on ID
     *
     * @param string $path            
     * @return \FileBank\Entity\File
     * @throws \Exception
     */
    public function getFileBySavePath($savePath)
    {
        // Get the entity from cache if available
        if (isset($this->cache[$savePath])) {
            $entity = $this->cache[$savePath];
        } else {
            $pluginUrl = $this->sl->get('viewrenderer')
                ->getEngine()
                ->plugin('url');
            $pluginUrl instanceof Url;
            $repository = $this->em->getRepository('FileBank\Entity\File');
            $entity = $repository->findOneBy(array(
                'savepath' => $savePath
            ));
            if ($entity)
                $this->generateDynamicParameters($entity);
        }
        
        // Cache the file entity so we don't have to access db on each call
        // Enables to get multiple entity's properties at different times
        $this->cache[$savePath] = $entity;
        return $entity;
    }

    /**
     * Get array of file entities based on given keyword
     *
     * @param Array $keywords            
     * @return Array
     * @throws \Exception
     */
    public function getFilesByKeywords($keywords, $strict = false, $limit = null, $orderBy = '')
    {
        // Create unique ID of the array for cache
        $id = md5(serialize($keywords) . $strict);
        $keywordsId = serialize($keywords);
        
        // Change all given keywords to lowercase
        $keywords = array_map('strtolower', $keywords);
        
        if ($orderBy) {
            $orderBy = ' ORDER BY ' . $orderBy;
        }
        
        // Get the entity from cache if available
        if (isset($this->cache[$id])) {
            $entities = $this->cache[$id];
        } else {
            if ($strict) {
                
                $q = $this->em->createQueryBuilder()
                    ->select('f')
                    ->from('FileBank\Entity\File', 'f');
                
                foreach ($keywords as $k => $keyword) {
                    $alias = 'k' . $k;
                    $q->innerJoin('f.keywords', $alias, 'WITH', $alias . '.value = \'' . $keyword . '\'');
                }
                
                $q->setMaxResults($limit);
                
                // $entities = $q->getQuery()->useResultCache(true, 180, $keywordsId)->getResult();
                $entities = $q->getQuery()->getResult();
            } else {
                $list = "'" . implode("','", $keywords) . "'";
                
                $q = $this->em->createQuery("select f from FileBank\Entity\File f, FileBank\Entity\Keyword k
                     where k.file = f
                     and k.value in (" . $list . ")" . $orderBy);
                $q->setMaxResults($limit);
                // $entities = $q->useResultCache(true, 180, $keywordsId)->getResult();
                $entities = $q->getResult();
            }
            
            foreach ($entities as $e) {
                $e instanceof \FileBank\Entity\File;
                if ($e)
                    $this->generateDynamicParameters($e);
            }
            
            // Cache the file entity so we don't have to access db on each call
            // Enables to get multiple entity's properties at different times
            $this->cache[$id] = $entities;
            
            return $entities;
        }
        
        return $entities;
    }

    public function saveEntity($fileEntity)
    {
        $this->em->persist($fileEntity);
        $this->em->flush();
        
        $this->generateDynamicParameters($fileEntity);
    }

    /**
     * Save file to FileBank database
     *
     * @param string $sourceFilePath            
     * @return File
     * @throws \Exception
     */
    public function save($sourceFilePath, Array $keywords = null, $createFile = true)
    {
        if (is_array($sourceFilePath)) {
            $_sourceFilePath = array_shift($sourceFilePath);
            $fileName = array_shift($sourceFilePath);
            $sourceFilePath = $_sourceFilePath;
        } else {
            $fileName = basename($sourceFilePath);
        }
        
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        
        $contents = @file_get_contents($sourceFilePath);
        
        if (! $contents)
            throw new \Exception('No se ha podido obtener el fichero o url:' . $sourceFilePath);
        
        $mimetype = $finfo->buffer(file_get_contents($sourceFilePath));
        $hash = md5(microtime(true) . $fileName);
        $savePath = substr($hash, 0, 1) . '/' . substr($hash, 1, 1) . '/';
        
        $absolutePath = $this->getRoot() . DIRECTORY_SEPARATOR . $savePath . $hash;
        
        if ($createFile) {
            try {
                $this->createPath($absolutePath, $this->params['chmod'], true);
                copy($sourceFilePath, $absolutePath);
                
                $this->file = new File();
                $this->file->setName($fileName);
                $this->file->setMimetype($mimetype);
                $this->file->setSize($this->fixIntegerOverflow(filesize($sourceFilePath)));
                $this->file->setIsActive($this->params['default_is_active']);
                $this->file->setSavepath($savePath . $hash);
                
                if ($keywords !== null)
                    $this->setKeywordsToFile($keywords, $this->file);
                
                $this->saveEntity($this->file);
            } catch (\Exception $e) {
                throw new \Exception('File cannot be saved.');
            }
        } else {
            $this->file = new File();
            $this->file->setName($fileName);
            $this->file->setMimetype($mimetype);
            $this->file->setSize($this->fixIntegerOverflow(filesize($sourceFilePath)));
            $this->file->setIsActive($this->params['default_is_active']);
            $this->file->setSavepath($savePath . $hash);
            
            if ($keywords !== null)
                $this->setKeywordsToFile($keywords, $this->file);
            
            $this->saveEntity($this->file);
        }
        
        // if($keywords !== null) {
        // $this->em->getConnection()->getConfiguration()->getResultCacheImpl()->delete(md5(serialize($keywords)));
        // }
        
        return $this->file;
    }

    public function saveForFileManager($sourceFilePath, Array $keywords = null)
    {
        $fileName = basename($sourceFilePath);
        
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimetype = $finfo->buffer(file_get_contents($sourceFilePath));
        
        $this->file = new File();
        $this->file->setName($fileName);
        $this->file->setMimetype($mimetype);
        $this->file->setSize($this->fixIntegerOverflow(filesize($sourceFilePath)));
        $this->file->setIsActive($this->params['default_is_active']);
        $this->file->setSavepath($sourceFilePath);
        
        if ($keywords !== null)
            $this->setKeywordsToFile($keywords, $this->file);
        
        $this->saveEntity($this->file);
        
        return $this->file;
    }

    public function saveFromLink($link, Array $keywords = null)
    {
        if (is_array($link)) {
            $_link = array_shift($link);
            $filename = array_shift($link);
            $link = $_link;
        } else {
            $filename = basename($link);
        }
        $filename = 'data/' . $filename;
        $contents = @file_get_contents($link);
        
        if (! $contents) {
            throw new \Exception('ÇNo se ha podido obtener la url:' . $link);
        }
        
        file_put_contents($filename, $contents);
        $file = $this->save($filename, $keywords);
        unlink($filename);
        return $file;
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
            /*
             * Debug::dump($exception->getMessage()); exit;
             */
            throw $exception;
            // algo fue mal y no se borro nada
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
        
        if ($versions->count() > 0) {
            foreach ($versions as $version) {
                $this->versionsPreparedToRemove[] = $version;
                if ($version->getVersionFile()) {
                    $version->setFile(null);
                    $this->_remove($version->getVersionFile());
                }
            }
        }
        
        $this->filesPreparedToRemove[] = $e->getAbsolutePath();
        
        $repo = $this->em->getRepository('FileBank\Entity\FileInS3');
        $resultInS3 = $repo->findOneBy(array('file' => $e));
        if($resultInS3) {
            $this->em->remove($resultInS3);
        }
        $this->em->remove($e);
        $this->removeKeywordsToFile($e);
        
        return $this;
    }

    protected function _removeCurrentFiles()
    {
        if ($this->filesPreparedToRemove) {
            foreach ($this->filesPreparedToRemove as $file) {
                if (file_exists($file))
                    unset($file);
            }
        }
        
        $this->filesPreparedToRemove = array();
    }

    protected function _removeCurrentVersions()
    {
        if ($this->versionsPreparedToRemove) {
            foreach ($this->versionsPreparedToRemove as $version) {
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
    public function addKeywordsToFile($keywords, $fileEntity)
    {
        $keywordEntities = array();
        
        foreach ($keywords as $word) {
            $keyword = new Keyword();
            $keyword->setValue(strtolower($word));
            $keyword->setFile($fileEntity);
            $this->em->persist($keyword);
            
            $keywordEntities[] = $keyword;
        }
        $fileEntity->addKeywords($keywordEntities);
    }

    /**
     * Attach keywords to file entity
     *
     * @param array $keywords            
     * @param FileBank\Entity\File $fileEntity            
     * @return FileBank\Entity\File
     */
    public function setKeywordsToFile($keywords, File $fileEntity)
    {
        $keywordEntities = array();
        
        foreach ($keywords as $word) {
            $keyword = new Keyword();
            $keyword->setValue(strtolower($word));
            $keyword->setFile($fileEntity);
            $this->em->persist($keyword);
            
            $keywordEntities[] = $keyword;
        }
        $this->removeKeywordsToFile($fileEntity);
        $fileEntity->setKeywords($keywordEntities);
    }

    /**
     * Attach keywords to file entity
     *
     * @param \FileBank\Entity\File $fileEntity            
     * @return \FileBank\Manager
     */
    public function removeKeywordsToFile($fileEntity)
    {
        $keywords = $fileEntity->getKeywords();
        
        if ($keywords->count() > 0) {
            foreach ($keywords as $keyword) {
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
    public function getVersion(File $file, Array $version, $options = array())
    {
        // dejamos solo los que nos sirven
        $version = $this->filterOptions($version, $this->versionOptions);
        
        // dejamos solo los que nos sirven
        $options = current($this->filterOptions(array(
            $options
        ), $this->thumbnailerDefaultOptions));
        
        $verionEncode = array(
            $version,
            $options
        );
        $verionEncode = $this->getVersionEncode($verionEncode);
        
        $versions = $file->getVersions();
        $versions instanceof \Doctrine\ORM\PersistentCollection;
        if ($versions !== null && $versions->count() > 0) {
            $criteria = new \Doctrine\Common\Collections\Criteria();
            $criteria->andWhere(new Comparison('value', Comparison::EQ, $verionEncode))->andWhere(new Comparison('file', Comparison::EQ, $file));
            $collection = $versions->matching($criteria);
            
            if ($collection->count() > 0) {
                return $this->generateDynamicParameters($collection->current()
                    ->getVersionFile());
            }
        }
        
        return $this->createVersion($file, $version, $options);
    }

    /**
     *
     * @param File $file            
     * @param array $version            
     * @return Ambigous <\FileBank\Entity\File, \FileBank\FileBank\Entity\File>
     */
    public function createVersion(File $file, Array $versionOptions, $options = array())
    {
        try {
            $version = $this->save(array(
                $file->getAbsolutePath(),
                $file->getName()
            ), null, false);
        } catch (\Exception $e) {
            return new File();
        }
        
        $versionEntity = new Version();
        
        $versionEntity->setFile($file)->setValue($this->getVersionEncode(array(
            $versionOptions,
            $options
        )));
        
        $this->em->persist($versionEntity);
        $this->em->persist($version->setVersion($versionEntity));
        $this->em->flush();
        
        return $version;
    }

    public function createFileVersion(File $file)
    {
        $version = $file->getVersion();
        if (! $version || file_exists($file->getAbsolutePath())) {
            return;
        }
        
        if(!file_exists($version->getFile()->getAbsolutePath())) {
        	return;
        }
        
        $allOptions = Json::decode($version->getValue(), Json::TYPE_ARRAY);
        $versionOptions = array_shift($allOptions);
        $options = array_shift($allOptions);
        
        try {
            $this->generateDynamicParameters($version->getFile());
            $this->createPath($file->getAbsolutePath(), $this->params['chmod'], true);
            copy($version->getFile()->getAbsolutePath(), $file->getAbsolutePath());
        } catch (\Exception $e) {
            throw new \Exception('File cannot be saved.');
        }
        
//         $thumb = $this->thumbnailer->create($file->getAbsolutePath(), $options);
        
//         foreach ($versionOptions as $methods) {
//             foreach ($methods as $method => $values) {
//                 call_user_func_array(array(
//                     $thumb,
//                     $method
//                 ), $values);
//             }
//         }
        
        $thumb->save($file->getAbsolutePath());
        
        $file->setSize(filesize($file->getAbsolutePath()));
        
        $this->em->persist($file);
        $this->em->flush();
        
        return $this;
    }

    public function getVersionEncode($version)
    {
        return Json::encode($version);
    }

    public function filterOptions($options, $defaultOptions)
    {
        $_options = array();
        foreach ($options as $values) {
            $_options[] = $this->array_intersect_key_recursive($values, $defaultOptions);
        }
        
        return $_options;
    }

    /**
     * calculates intersection of two arrays like array_intersect_key but recursive
     *
     * @param
     *            array/mixed master array
     * @param
     *            array array that has the keys which should be kept in the master array
     * @return array/mixed cleand master array
     */
    function array_intersect_key_recursive($master, $mask)
    {
        if (! is_array($master)) {
            return $master;
        }
        foreach ($master as $k => $v) {
            if (! isset($mask[$k])) {
                unset($master[$k]);
                continue;
            } // remove value from $master if the key is not present in $mask
            if (is_array($mask[$k])) {
                $master[$k] = $this->array_intersect_key_recursive($master[$k], $mask[$k]);
            } // recurse when mask is an array
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
    public function generateDynamicParameters(File $file, $options = array())
    {
        if (file_exists($file->getSavePath())) {
            $file->setAbsolutePath($file->getSavePath());
        } else {
            $file->setAbsolutePath($this->getRoot() . DIRECTORY_SEPARATOR . $file->getSavePath());
        }
        
        if ($this->params['use_aws_s3']) {
            if(!$this->fileExistInS3($file)) {
                $this->createFileVersion($file);
            }
            
            $file->setUrl($this->params['s3_base_url'] . $this->params['filebank_folder_aws_s3'] . $file->getSavePath());
            
            $file->setDownloadUrl($this->params['s3_base_url'] . $this->params['filebank_folder_aws_s3'] . $file->getSavePath());
            
        } else {
            $urlHelper = $this->sl->get('viewrenderer')
                ->getEngine()
                ->plugin('url');
            $file->setUrl($urlHelper('FileBank/View', array(
                'id' => $file->getId(),
                'name' => $file->getName()
            ), $options));
            
            $file->setDownloadUrl($urlHelper('FileBank/Download', array(
                'id' => $file->getId(),
                'name' => $file->getName()
            ), $options));
            
            if (file_exists($file->getSavePath())) {
                $file->setAbsolutePath($file->getSavePath());
            } else {
                $file->setAbsolutePath($this->getRoot() . DIRECTORY_SEPARATOR . $file->getSavePath());
            }
        }
        
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
        if (! is_dir(dirname($path))) {
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

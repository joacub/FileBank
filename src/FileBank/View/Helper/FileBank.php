<?php

namespace FileBank\View\Helper;

use Zend\View\Helper\AbstractHelper;
use FileBank\Manager;
use FileBank\Entity\File;

class FileBank extends AbstractHelper 
{
    /**
     * @var Manager Service
     */
    protected $service;

    /**
     * @var array $params
     */
    protected $params;

    /**
     * Called upon invoke
     * 
     * @param integer $id
     * @return FileBank\Entity\File
     */
    public function __invoke() {
        return $this;
    }
    
    public function getFileById($id)
    {
        $file = $this->service->getFileById($id);
        return $file;
    }
    
    public function getFilesByKeywords($keywords, $strict = false, $limit = null, $orderBy = '')
    {
        $files = $this->service->getFilesByKeywords($keywords, $strict, $limit, $orderBy);
        return $files;
    }
    
    public function getVersion(File $file, Array $version, $options = array())
    {
        $version = $this->service->getVersion($file, $version, $options);
        return $version;
    }

    /**
     * Add dynamic data into the entity
     * 
     * @param FileBank\Entity\File $file
     * @param Array $linkOptions
     * @return FileBank\Entity\File
     */
    public function generateDynamicParameters(File $file) {
        $urlHelper = $this->getView()->plugin('url');

        $file->setUrl(
                $urlHelper('FileBank') . '/' . $file->getId()
        );

        return $file;
    }

    /**
     * Get FileBank service.
     *
     * @return $this->service
     */
    public function getService() {
        return $this->service;
    }

    /**
     * Set FileBank service.
     *
     * @param $service
     */
    public function setService($service) {
        $this->service = $service;
        return $this;
    }

    /**
     * Get FileBank params.
     *
     * @return $this->params
     */
    public function getParams() {
        return $this->params;
    }

    /**
     * Set FileBank params.
     *
     * @param array $params
     */
    public function setParams(Array $params) {
        $this->params = $params;
        return $this;
    }
}
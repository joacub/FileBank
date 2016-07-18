<?php

namespace FileBank\Service;

use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Zend\ServiceManager\Exception\ServiceNotCreatedException;
use Zend\ServiceManager\Exception\ServiceNotFoundException;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use FileBank\Manager;

/**
 * FileBank service manager factory
 */
class Factory implements FactoryInterface 
{
    /**
     * Factory method for FileBank Manager service
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @return \FileBank\Manager
     */
    public function createService(ServiceLocatorInterface $serviceLocator) {
        $config = $serviceLocator->get('Configuration');
        $params = $config['FileBank']['params'];
        $em = $serviceLocator->get('doctrine.entitymanager.orm_default');

        $manager = new Manager($params, $em, $serviceLocator);
        return $manager;
    }

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return $this->createService($container);
    }


}
<?php

namespace FileBank;

use FileBank\View\Helper\FileBank;
use Tracy\Debugger;
use Zend\View\HelperPluginManager;

class Module
{
    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\ClassMapAutoloader' => array(
                __DIR__ . '/autoload_classmap.php',
            ),
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }
    
    public function getServiceConfig()
    {
        return array(
            'factories' => array(
                'FileBank' => 'FileBank\Service\Factory',
            )
        ); 
    }
    
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }
    
    public function getViewHelperConfig()
    {
        return array(
            'factories' => array(
                'fileBank' => function ($sm) {
                    $locator = $sm;
                    if($locator instanceof HelperPluginManager) {
                        $locator = $locator->getServiceLocator();
                    }
                    $config = $locator->get('Configuration');
                    $params = $config['FileBank']['params'];
                    
                    $viewHelper = new View\Helper\FileBank();
                    $viewHelper->setService($locator->get('FileBank'));
                    $viewHelper->setParams($params);
                    
                    return $viewHelper;
                },
            ),
        );

    }
}
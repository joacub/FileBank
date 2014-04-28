<?php

namespace FileBank;

return array(
    __NAMESPACE__ => array(
        'params' => array(
            'use_aws_s3' => false,
            'filebank_folder_aws_s3'  => 'data/filebank/',
            's3_base_url' => 'http://s3.com/',
            'filebank_folder'  => 'data/filebank/', 
            'default_is_active' => true,
            'chmod'           => 0755,
        ),
    ),
    'controllers' => array(
        'invokables' => array(
            __NAMESPACE__ . '\Controller\File' => __NAMESPACE__ . '\Controller\FileController',
        ),
    ),
    'router' => array(
        'routes' => array(
            __NAMESPACE__ => array(
                'type' => 'segment', 
                'options' => array(
                    'route' => '/files', 
                    'defaults' => array(
                        'controller' => __NAMESPACE__ . '\Controller\File', 
                        'action' => 'index'
                    )
                ), 
                'may_terminate' => true, 
                'child_routes' => array(
                    'Download' => array(
                        'type' => 'segment', 
                        'options' => array(
                            'route' => '/d/:id{-/}[-:name]', 
                            'constraints' => array(
                                'id' => '[0-9]+',
                                //'name' => '[a-zA-Z0-9_-]+.[a-zA-Z0-9]+'
                            ), 
                            'defaults' => array(
                                'controller' => __NAMESPACE__ .
                                 '\Controller\File', 
                                'action' => 'download'
                            )
                        )
                    ), 
                    'View' => array(
                        'type' => 'segment', 
                        'options' => array(
                            'route' => '/:id{-/}[-:name]', 
                            'constraints' => array(
                                'id' => '[0-9]+', 
                                //'name' => '[a-zA-Z0-9_-]+.[a-zA-Z0-9_-]+'
                            ), 
                            'defaults' => array(
                                'controller' => __NAMESPACE__ .
                                 '\Controller\File', 
                                'action' => 'view'
                            )
                        )
                    ),
                )
            )
        )
    ),
    'doctrine' => array(
        'connection' => array(
            'orm_default' => array(
                'driverClass' => 'Doctrine\DBAL\Driver\PDOMySql\Driver',
                'params' => array(
                    'host'     => 'localhost',
                    'port'     => '3306',
                    'user'     => 'username',
                    'password' => 'password',
                    'dbname'   => 'database_name',
                )
            )
        ),
        'driver' => array(
            __NAMESPACE__ . '_driver' => array(
                'class' => 'Doctrine\ORM\Mapping\Driver\AnnotationDriver',
                'cache' => 'array',
                'paths' => array(__DIR__ . '/../src/' . __NAMESPACE__ . '/Entity')
            ),
            'orm_default' => array(
                'drivers' => array(
                    __NAMESPACE__ . '\Entity' => __NAMESPACE__ . '_driver'
                ),
            ),
        ),
    ),
);
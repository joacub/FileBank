<?php

namespace FileBank\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class FileController extends AbstractActionController 
{
    /**
     * Get the file from FileBank and offer it for download 
     */
    public function downloadAction() 
    {
        $filelib = $this->getServiceLocator()->get('FileBank');
        $id = (int) $this->getEvent()->getRouteMatch()->getParam('id');

        $file = $filelib->getFileById($id);
        $filePath = $file->getAbsolutePath();
        
        $response = $this->getResponse();
        $response->getHeaders()
        ->addHeaderLine('Content-Description', 'File Transfer')
        ->addHeaderLine('Content-Type',   $file->getMimetype())
        ->addHeaderLine('Content-Disposition', 'attachment; filename="' . $file->getName() . '"')
        ->addHeaderLine('Content-Transfer-Encoding', 'binary')
        ->addHeaderLine('Expires', '0')
        //1 semana
        ->addHeaderLine('Cache-Control', 'max-age=604800')
        ->addHeaderLine('Pragma', 'public')
        ->addHeaderLine('Content-Length', $file->getSize());
        
        $content = file_get_contents($filePath);
        
        $response->setContent($content);
        
        return $response;
        
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $file->getMimetype());
        header('Content-Disposition: attachment; filename=' . $file->getName());
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . $file->getSize());
    }
    
    
    public function viewAction()
    {
        $filelib = $this->getServiceLocator()->get('FileBank');
        $id = (int) $this->getEvent()->getRouteMatch()->getParam('id');
    
        $file = $filelib->getFileById($id);
        $filePath = $file->getAbsolutePath();
        
        $response = $this->getResponse();
        $response->getHeaders()
        ->addHeaderLine('Content-Transfer-Encoding',   'binary')
        ->addHeaderLine('Content-Type',                $file->getMimetype())
        ->addHeaderLine('Content-Length',              $file->getSize())
        ->addHeaderLine('Expires', '0')
        //1 semana
        ->addHeaderLine('Cache-Control', 'max-age=604800');
    
        $response->setContent(file_get_contents($filePath));
        
        return $response;
    }
    
}

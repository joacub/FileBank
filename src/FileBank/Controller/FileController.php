<?php

namespace FileBank\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Nette\Diagnostics\Debugger;

class FileController extends AbstractActionController 
{
    /**
     * Get the file from FileBank and offer it for download 
     */
    public function downloadAction() 
    {
        $filelib = $this->getServiceLocator()->get('FileBank');
        $id = (int) $this->getEvent()->getRouteMatch()->getParam('id');

        try {
        	$file = $filelib->getFileById($id);
        } catch (\Exception $e) {
        	return $this->notFoundAction();
        }
        
        $filePath = $file->getAbsolutePath();
        
        $filelib->createFileVersion($file);
        
        $response = $this->getResponse();
        $response->getHeaders()
        ->addHeaderLine('Content-Description', 'File Transfer')
        ->addHeaderLine('Content-Type',   $file->getMimetype())
        ->addHeaderLine('Content-Disposition', 'attachment; filename=' . $file->getName() . '')
        ->addHeaderLine('Content-Transfer-Encoding', 'binary')
        ->addHeaderLine('Expires', date(DATE_RFC822, (time() + (60*60*24*7))))
        //1 semana
        ->addHeaderLine('Cache-Control', 'must-revalidate')
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
    
    	try {
        	$file = $filelib->getFileById($id);
        } catch (\Exception $e) {
        	return $this->notFoundAction();
        }
        
        $filePath = $file->getAbsolutePath();
        
        $filelib->createFileVersion($file);
        
        $response = $this->getResponse();
        
        $response->getHeaders()
        ->addHeaderLine('Content-Transfer-Encoding',   'binary')
        ->addHeaderLine('Content-Type',                $file->getMimetype())
        ->addHeaderLine('Content-Length',              $file->getSize())
        ->addHeaderLine('Pragma', 'public')
        ->addHeaderLine('Expires', date(DATE_RFC822, (time() + (60*60*24*7))))
        ->addHeaderLine('Last-Modified', gmdate('D, d M Y H:i:s', filemtime($filePath)) . ' GMT')
        //1 semana
        ->addHeaderLine('Cache-Control', 'must-revalidate');
    
        $response->setContent(file_get_contents($filePath));
        
        return $response;
    }
    
}

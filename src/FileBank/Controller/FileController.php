<?php

namespace FileBank\Controller;

use Zend\Json\Json;
use FileBank\Manager;
use FileBank\Entity\File;

class FileController extends AbstractActionController
{
    /**
     * Get the file from FileBank and offer it for download
     */
    public function downloadAction()
    {
        $filelib = $this->getServiceLocator()->get('FileBank');
        $filelib instanceof Manager;
        $id = (int)$this->getEvent()->getRouteMatch()->getParam('id');

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
            ->addHeaderLine('Content-Type', $file->getMimetype())
            ->addHeaderLine('Content-Disposition', 'attachment; filename=' . $file->getName() . '')
            ->addHeaderLine('Content-Transfer-Encoding', 'binary')
            ->addHeaderLine('Expires', date(DATE_RFC822, (time() + (60 * 60 * 24 * 7))))
            //1 semana
            ->addHeaderLine('Cache-Control', 's-max-age=604800')
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
        $filelib instanceof Manager;
        $id = (int)$this->getEvent()->getRouteMatch()->getParam('id');

        $file = $this->getFileAndGenerete($id);
        $filePath = $file->getAbsolutePath();

        $params = $filelib->getParams();
        if ($params['use_aws_s3'] && $params['redirect_to_s3']) {
            return $this->redirect()->toUrl($file->getUrl());
        }

        $response = $this->getResponse();

        $response->getHeaders()
            ->addHeaderLine('Content-Transfer-Encoding', 'binary')
            ->addHeaderLine('Content-Type', $file->getMimetype())
            ->addHeaderLine('Content-Length', $file->getSize())
            ->addHeaderLine('Pragma', 'public')
            ->addHeaderLine('Expires', date(DATE_RFC822, (time() + (60 * 60 * 24 * 7))))
            ->addHeaderLine('Last-Modified', gmdate('D, d M Y H:i:s', filemtime($filePath)) . ' GMT')
            //1 semana
            ->addHeaderLine('Cache-Control', 's-max-age=604800');

        $response->setContent(file_get_contents($filePath));

        return $response;
    }

    /**
     *
     * @param unknown $id
     * @return File
     */
    protected function getFileAndGenerete($id)
    {
        $filelib = $this->getServiceLocator()->get('FileBank');
        try {
            $file = $filelib->getFileById($id);
        } catch (\Exception $e) {
            return $this->notFoundAction();
        }

        $filelib->createFileVersion($file);

        return $file;
    }

    public function genereteVersionsAction()
    {
        $filelib = $this->getServiceLocator()->get('FileBank');
        $filelib instanceof Manager;

        $files = $filelib->getFilesNotInAwsS3();
        foreach ($files as $file) {
            $this->getFileAndGenerete($file->getId());
        }
    }

    public function createVersionInAjaxAction()
    {

        $data = $this->params('data');
        try {
            $data = Json::decode($data, Json::TYPE_ARRAY);
        } catch(\Exception $e) {
            return $this->notFoundAction();
        }
        $filelib = $this->getServiceLocator()->get('FileBank');
        /**
         * @var Manager $filelib
         */
        try {
            $file = $filelib->getFileById($data['fileId']);
        } catch (\Exception $e) {
            return $this->notFoundAction();
        }

        $filelib->disableCreateInAjax();

        $version = $data['version'];
        $options = $data['options'];
        $fileEmpty = $data['fileEmpty'] ? $filelib->getFileById($data['fileEmpty']) : null;

        $version = $filelib->getVersion($file, $version, $options, $fileEmpty);

        $viewAction = $this->forward()->dispatch('FileBank\Controller\File', array(
            'action' => 'view',
            'id' => $version->getId(),
            'name' => $version->getName()
        ));
        return $viewAction;
    }

}

<?php

namespace Nosto\Tagging\Controller\Monitoring;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use ZipArchive;

class Logs implements ActionInterface
{
    private const string LOG_LOCATION = BP . '/var/log/';

    private const string ARCHIVE_NAME = 'nosto-logs.zip';

    /** @var ManagerInterface $messageManager */
    private ManagerInterface $messageManager;

    /** @var RedirectFactory $redirectFactory */
    private RedirectFactory $redirectFactory;

    /** @var RedirectInterface $redirect */
    private RedirectInterface $redirect;

    public function __construct(
        ManagerInterface $messageManager,
        RedirectFactory $redirectFactory,
        RedirectInterface $redirect
    ) {
        $this->messageManager = $messageManager;
        $this->redirectFactory = $redirectFactory;
        $this->redirect = $redirect;
    }

    public function execute()
    {
        $fileNames = [];

        $logFiles = scandir(self::LOG_LOCATION);
        foreach ($logFiles as $logFile) {
            if (str_starts_with($logFile, 'nosto')) {
                $fileNames[] = $logFile;
            }
        }

        if (!is_readable(self::LOG_LOCATION)) {
            $this->messageManager->addErrorMessage('Permission denied!');

            return $this->redirectFactory->create()->setUrl($this->redirect->getRefererUrl());
        }

        $this->compressAndDownloadLogFiles($fileNames);
    }

    private function compressAndDownloadLogFiles(array $files)
    {
        $zip = new ZipArchive();
        if ($zip->open(self::ARCHIVE_NAME, ZipArchive::CREATE) !== true) {
            exit('Cannot open ' . self::ARCHIVE_NAME);
        }

        foreach ($files as $file) {
            $zip->addFile(self::LOG_LOCATION . $file, $file);
        }
        $zip->close();

        header("Content-type: application/zip");
        header("Content-Disposition: attachment; filename = " . (self::ARCHIVE_NAME));
        header("Pragma: no-cache");
        header("Expires: 0");
        readfile(self::ARCHIVE_NAME);
        exit;
    }
}

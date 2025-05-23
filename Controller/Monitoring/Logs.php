<?php
/**
 * Copyright (c) 2020, Nosto Solutions Ltd
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the names of its contributors
 * may be used to endorse or promote products derived from this software without
 * specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Nosto Solutions Ltd <contact@nosto.com>
 * @copyright 2020 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Controller\Monitoring;

use Exception;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Message\ManagerInterface;
use ZipArchive;

class Logs implements ActionInterface
{
    private const LOG_LOCATION = BP . '/var/log/';

    private const ARCHIVE_NAME = 'nosto-logs.zip';

    /** @var ManagerInterface $messageManager */
    private ManagerInterface $messageManager;

    /** @var RedirectFactory $redirectFactory */
    private RedirectFactory $redirectFactory;

    /** @var RedirectInterface $redirect */
    private RedirectInterface $redirect;

    /** @var FileFactory $fileFactory */
    private FileFactory $fileFactory;

    /** @var File $file */
    private File $fileDriver;

    /**
     * Logs constructor
     *
     * @param ManagerInterface $messageManager
     * @param RedirectFactory $redirectFactory
     * @param RedirectInterface $redirect
     * @param FileFactory $fileFactory
     * @param File $fileDriver
     */
    public function __construct(
        ManagerInterface $messageManager,
        RedirectFactory $redirectFactory,
        RedirectInterface $redirect,
        FileFactory $fileFactory,
        File $fileDriver
    ) {
        $this->messageManager = $messageManager;
        $this->redirectFactory = $redirectFactory;
        $this->redirect = $redirect;
        $this->fileFactory = $fileFactory;
        $this->fileDriver = $fileDriver;
    }

    /**
     * Check if user have permission for log folder and download log files
     * @throws Exception
     */
    public function execute(): ResponseInterface|Redirect
    {
        if (false === $this->checkPermissionsForLogsFolder()) {
            $this->messageManager->addErrorMessage(__('Permission denied!'));

            return $this->redirectFactory->create()->setUrl($this->redirect->getRefererUrl());
        }

        $zipFilePath = self::LOG_LOCATION . self::ARCHIVE_NAME;

        $zip = new ZipArchive();
        if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception(__('Cannot create ZIP file.'));
        }

        foreach ($this->getNostoLogFiles() as $file) {
            if ($this->fileDriver->isExists($file)) {
                $zip->addFile($file, basename($file)); // Add file and set name inside ZIP
            }
        }
        $zip->close();

        // Serve ZIP file for download
        return $this->fileFactory->create(
            self::ARCHIVE_NAME,
            [
                'type'  => 'filename',
                'value' => $zipFilePath,
                'rm'    => true  // Delete after download
            ],
            DirectoryList::VAR_DIR,
            'application/zip'
        );
    }

    /**
     * Get log files with nosto prefix
     *
     * @return array
     */
    private function getNostoLogFiles(): array
    {
        $fileNames = [];

        $logFiles = scandir(self::LOG_LOCATION);
        foreach ($logFiles as $logFile) {
            if (str_starts_with($logFile, 'nosto')) {
                $fileNames[] = self::LOG_LOCATION . $logFile;
            }
        }

        return $fileNames;
    }

    /**
     * Check permissions for logs folder
     *
     * @return bool
     * @throws FileSystemException
     */
    private function checkPermissionsForLogsFolder(): bool
    {
        if (!$this->fileDriver->isReadable(self::LOG_LOCATION)) {
            return false;
        }

        return true;
    }
}

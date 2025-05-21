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

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Controller\Result\Redirect;
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

    /**
     * Logs constructor
     *
     * @param ManagerInterface $messageManager
     * @param RedirectFactory $redirectFactory
     * @param RedirectInterface $redirect
     */
    public function __construct(
        ManagerInterface $messageManager,
        RedirectFactory $redirectFactory,
        RedirectInterface $redirect
    ) {
        $this->messageManager = $messageManager;
        $this->redirectFactory = $redirectFactory;
        $this->redirect = $redirect;
    }

    /**
     * Check if user have permission for log folder and download log files
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        if (false === $this->checkPermissionsForLogsFolder()) {
            $this->messageManager->addErrorMessage(__('Permission denied!'));
        } else {
            $this->compressAndDownloadLogFiles($this->getNostoLogFiles());
        }

        return $this->redirectFactory->create()->setUrl($this->redirect->getRefererUrl());
    }

    /**
     * Compress and download Nosto log files
     *
     * @param array $files
     * @return void
     */
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
                $fileNames[] = $logFile;
            }
        }

        return $fileNames;
    }

    /**
     * Check permissions for logs folder
     *
     * @return bool
     */
    private function checkPermissionsForLogsFolder(): bool
    {
        if (!is_readable(self::LOG_LOCATION)) {
            return false;
        }

        return true;
    }
}

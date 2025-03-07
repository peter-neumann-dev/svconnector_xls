<?php

declare(strict_types = 1);

namespace Pagemachine\SvconnectorXls\Service;

use Cobweb\Svconnector\Exception\SourceErrorException;
use Cobweb\Svconnector\Utility\FileUtility;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class XlsConnector extends AbstractConnector
{
    /**
     * @param array $parameters
     */
    public function checkConfiguration($parameters): array
    {
        $result = parent::checkConfiguration($parameters);

        // The "filename" parameter is mandatory
        if (empty($parameters['filename'])) {
            $result[AbstractMessage::ERROR][] = 'The "filename" parameter is mandatory.';
        }

        if (empty(GeneralUtility::getFileAbsFileName($parameters['filename']))) {
            $result[AbstractMessage::ERROR][] = 'The "filename" does not exist.';
        }

        return $result;
    }

    /**
     * @param array $parameters
     * @return mixed
     */
    protected function query($parameters)
    {
        $this->logger->info('Call parameters', $parameters);
        $this->validateConfiguration($parameters);

        $fileUtility = GeneralUtility::makeInstance(FileUtility::class);
        $filename =  $fileUtility->getFileAsTemporaryFile($parameters['filename']);

        if ($filename === false) {
            $this->raiseError($fileUtility->getError(), 1605278290, $parameters, SourceErrorException::class);

            return;
        }

        $reader = IOFactory::createReader('Xls');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filename);

        try {
            $worksheet = $spreadsheet->getActiveSheet();
        } catch (SpreadsheetException $e) {
            $this->raiseError($e->getMessage(), 1596554370, $parameters, SourceErrorException::class);

            return;
        }

        $data = [];

        foreach ($worksheet->getRowIterator() as $row) {
            $item = [];

            foreach ($row->getCellIterator() as $key => $cell) {
                $item[$key] = $cell->getValue();
            }

            $data[] = $item;
        }

        // Process the result if any hook is registered
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][$this->extensionKey]['processResponse'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][$this->extensionKey]['processResponse'] as $className) {
                $processor = GeneralUtility::makeInstance($className);
                $data = $processor->processResponse($data, $this);
            }
        }

        return $data;
    }
}

<?php

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Sypets\Brofix\Hooks;

use Sypets\Brofix\CheckLinks\ExcludeLinkTarget;
use Sypets\Brofix\Repository\BrokenLinkRepository;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Hook for changes to records in the database.
 *
 * @internal This class is a hook implementation and is for internal use inside this extension only.
 */
final class DataHandlerHook
{
    /**
     * @var BrokenLinkRepository
     */
    private $brokenLinkRepository;

    /**
     * @var ExcludeLinkTarget
     */
    private $excludeLinkTarget;

    public function __construct(
        BrokenLinkRepository $brokenLinkRepository = null,
        ExcludeLinkTarget $excludeLinkTarget = null
    ) {
        $this->brokenLinkRepository = $brokenLinkRepository ?: GeneralUtility::makeInstance(BrokenLinkRepository::class);
        $this->excludeLinkTarget = $excludeLinkTarget ?: GeneralUtility::makeInstance(ExcludeLinkTarget::class);
        $this->getLanguageService()->includeLLFile('EXT:brofix/Resources/Private/Language/Module/locallang.xlf');
    }

    /**
     * @param string $status
     * @param string $table
     * @param $id
     * @param array $changedValues contains changed values (not all values !!!)
     * @param DataHandler $dataHandler
     */
    public function processDatamap_afterDatabaseOperations(
        string $status,
        string $table,
        $id,
        array $changedValues,
        DataHandler $dataHandler
    ): void {
        if ($dataHandler->isImporting
            || $dataHandler->BE_USER->workspace > 0
        ) {
            return;
        }

        $uid = (int)$id;

        if (($status === 'new' || $status === 'update')
            && $table === ExcludeLinkTarget::TABLE
            && (isset($changedValues['link_type']) || isset($changedValues['linktarget']) || isset($changedValues['match']))
            ) {
            if ($status === 'update') {
                // because we are not sure we can get all record values from DataHandler, we do a new
                // query here (which is also what is customary in the core)
                $row = BackendUtility::getRecord($table, $uid) ?: [];
            } else {
                $row = $changedValues;
            }

            // remove broken links based on excluded URLs
            $this->removeBrokenLinkRecordsForExcludedLinkTarget($row);
        } elseif ($status === 'update'
            && ($table === 'pages' || $table === 'tt_content')
            && ($changedValues['hidden'] ?? false)
            && (($changedValues['t3ver_stage'] ?? 0) === 0)) {
            // page / content has changed state to hidden: delete broken link records
            $numDeleted = $this->brokenLinkRepository->removeBrokenLinksForRecord($table, $id);
            if ($numDeleted > 0) {
                $message = sprintf(
                    $this->getLanguageService()->getLL('broken_links_removed'),
                    $numDeleted,
                    $id
                );
                $title = '';
                $this->createFlashMessage($message, $title);
            }
        }
    }

    /*
     * cmd Datahandler hook
     */
    public function processCmdmap_deleteAction($table, $id, $recordToDelete, $recordWasDeleted, $dataHandler)
    {
        $id = (int)$id;
        $numDeleted = $this->brokenLinkRepository->removeBrokenLinksForRecord($table, $id);
        if ($numDeleted > 0) {
            $message =  sprintf(
                $this->getLanguageService()->getLL('broken_links_removed'),
                $numDeleted,
                $id
            );
            $title = '';
            $this->createFlashMessage($message, $title);
        }
    }

    private function removeBrokenLinkRecordsForExcludedLinkTarget(array $row): bool
    {
        if ($row === []
            || (($row['linktarget'] ?? '') === '')
            || (($row['link_type'] ?? '') === '')
            || (($row['match'] ?? '') === '')
            || (!isset($row['pid']))
        ) {
            return false;
        }

        $numDeleted = $this->brokenLinkRepository->removeBrokenLinksForLinkTarget(
            $row['linktarget'],
            $row['link_type'],
            $row['match'],
            (int)($row['pid'] ?? 0)
        );
        if ($numDeleted > 0) {
            $message =  sprintf(
                $this->getLanguageService()->getLL('broken_links_removed'),
                $numDeleted,
                $row['linktarget']
            );
            $title = $this->getLanguageService()->getLL('exclude_list');
            $this->createFlashMessage($message, $title);
        }
        return true;
    }

    private function createFlashMessage(string $message, $title, int $status = FlashMessage::OK): void
    {
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $message,
            $title,
            $status,
            false
        );

        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $defaultFlashMessageQueue->enqueue($flashMessage);
    }

    /**
     * @return LanguageService
     */
    private function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }
}

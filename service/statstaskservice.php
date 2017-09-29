<?php

/**
 * ownCloud - Dashboard
 *
 * @author Patrick Paysant <ppaysant@linagora.com>
 * @copyright 2014 CNRS DSI
 * @license This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Dashboard\Service;

use \OCA\Dashboard\Db\HistoryMapper;
use \OCA\Dashboard\Db\History;
use \OCA\Dashboard\Db\HistoryByGroupMapper;
use \OCA\Dashboard\Db\HistoryByGroup;

use OCA\Dashboard\Lib\Helper;

class StatsTaskService {
    protected $statService;
    protected $historyMapper;
    protected $historyByGroupMapper;
    protected $loggerService;

    public function __construct(\OCA\Dashboard\Service\StatService $statService,HistoryMapper $historyMapper,HistoryByGroupMapper $historyByGroupMapper, LoggerService $loggerService) {
        $this->statService = $statService;
        $this->historyMapper = $historyMapper;
        $this->historyByGroupMapper = $historyByGroupMapper;
        $this->loggerService = $loggerService;
    }

    /**
     * Run cron job and store some basic stats in DB
     */
    public function run() {
        $output = $this->loggerService->getOutput();

        $now = new \DateTime();
        $now->setTime(0, 0, 0);
        $datas = $this->historyMapper->countFrom($now);
        if (count($datas) <= 0) {
            $globalStorageInfo = $this->statService->getGlobalStorageInfo();
            $now = new \DateTime();
            $now = $now->format("Y-m-d H:i:s");

            $history = $this->getStats($globalStorageInfo, $now);
            $this->historyMapper->insert($history);

            // stats by group ?
            if (Helper::isDashboardGroupsEnabled() and !empty($globalStorageInfo['groups'])) {
                // One stat line per group for today
                foreach($globalStorageInfo['groups'] as $groupName => $groupInfo) {
                    $historyByGroup = $this->getStatsByGroup($groupName, $groupInfo, $now);
                    $this->historyByGroupMapper->insert($historyByGroup);
                }
            }
        }
    }

    /**
     * @return \OCA\Dashboard\Db\History
     */
    protected function getStats($globalStorageInfo, $when) {
        $history = new History;

        $history->setDate($when);
        $history->setNbUsers($this->statService->countUsers());
        $history->setDefaultQuota($globalStorageInfo['defaultQuota']);
        $history->setTotalUsedSpace($globalStorageInfo['totalSize']);
        $history->setSizePerUser($globalStorageInfo['sizePerUser']);

        return $history;
    }

    /**
     * @param string $groupName Group gid
     * @param array $groupInfo Group stats
     * @param string $when datetime
     * @return \OCA\Dashboard\Db\HistoryByGroup
     */
    protected function getStatsByGroup($groupName, $groupInfo, $when) {
        $historyByGroup = new HistoryByGroup;

        $historyByGroup->setGid($groupName);
        $historyByGroup->setDate($when);
        $historyByGroup->setTotalUsedSpace($groupInfo['filesize']);
        $historyByGroup->setNbUsers($groupInfo['nbUsers']);
        $historyByGroup->setSizePerUser($groupInfo['sizePerUser']);

        return $historyByGroup;
    }
}

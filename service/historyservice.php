<?php

/**
 * ownCloud - Dashboard
 *
 * @author Patrick Paysant <ppaysant@linagora.com>
 * @copyright 2014 CNRS DSI
 * @license This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Dashboard\Service;

use OCA\Dashboard\Lib\Helper;

class HistoryService {

    /**
     * @var \OCA\Dashboard\Db\HistoryMapper $historyMapper
     */
    protected $historyMapper;

    /**
     * @var \OCA\Dashboard\Db\HistoryByGroupMapper $historyByGroupMapper
     */
    protected $historyByGroupMapper;

    public function __construct(\OCA\Dashboard\Db\HistoryMapper $historyMapper, \OCA\Dashboard\Db\HistoryByGroupMapper $historyByGroupMapper) {
        $this->historyMapper = $historyMapper;
        $this->historyByGroupMapper = $historyByGroupMapper;
    }

    /**
     * Returns datas from history
     * @param string $gid Group id, if you want stats for only one group (this group MUST be conf-ed in admin page), 'none' (default) if you want global datas
     * @param string $dataType The type of data you want, 'all' (default) if you want all datas.
     * @param integer $range Number of days from today you want to get the datas (30 days by default)
     * @param integer $wanHumanReadable If you want to show humanreable values (1) or not (0)
     * @throws OCA\Dashboard\Service\HistoryStatsUnknownDatatypeException
     * @throws OCA\Dashboard\Service\HistoryStatsInvalidRangeException
     * @return array
     */
    public function getHistoryStats($gid='none', $dataType='all', $range=30, $wantHumanReadable = 1) {
        $statNameByGroup = array(
            'date',
            'totalUsedSpace',
            'nbUsers',
            'sizePerUser',
        );
        $statName = array_merge($statNameByGroup, array(
            'defaultQuota',
            'completeDate',
        ));
        $humanReadable = array(
            'totalUsedSpace',
            'sizePerUser',
        );

        $statsByGroup = false;
        // stat enabled groups list
        if ($gid !== 'none' and Helper::isDashboardGroupsEnabled()) {
            $statsByGroup = true;
        }

        if ($dataType !== 'all') {
            if ($gid !== 'none' and !in_array($dataType, $statNameByGroup)) {
                throw new HistoryStatsInvalidDatatypeException();
            }

            if (!in_array($dataType, $statName)) {
                throw new HistoryStatsUnknownDatatypeException();

            }
            $statName = array('date', $dataType);
        }

        $arrayDatas = array();
        foreach($statName as $name) {
            $arrayDatas[$name] = array();
        }

        $history = array();
        foreach($statName as $name) {
            $history[$name] = '';
        }

        // get $range last days stats

        // last is only usefull for stats not filtered by group
        if ($range == 'last') {
            $datas = $this->historyMapper->findLast();
        }
        else {
            if (intval($range) <= 0) {
                throw new HistoryStatsInvalidRangeException();
            }

            $datetime = new \DateTime();
            $datetime->sub(new \dateInterval('P' . (int)$range . 'D'));
            $datetime->setTime(23, 59, 59);
            if ($statsByGroup) {
                $datas = $this->historyByGroupMapper->findAllFrom($gid, $datetime, $dataType);
            }
            else {
                $datas = $this->historyMapper->findAllFrom($datetime, $dataType);
            }
        }

        // create a array struct
        foreach($datas as $data) {
            foreach($statName as $name) {
                // date need special processing as we only retain day number
                if ($name == "date") {
                    list($date, $time) = explode(' ', $data->getDate());
                    list($year, $month, $day) = explode('-', $date);
                    array_push($arrayDatas['date'], $date);
                    // array_push($arrayDatas['date'], (int)$day);
                }
                elseif ($name == "completeDate") {
                    array_push($arrayDatas['completeDate'], $data->getDate());
                }
                else {
                    $func = 'get' . ucfirst($name);
                    array_push($arrayDatas[$name], (float)$data->$func());
                }
            }
        }

        foreach($statName as $name) {
            if ((boolean)$wantHumanReadable and  in_array($name, $humanReadable)) {
                if (isset($arrayDatas[$name])) {
                    $hr = $this->humanreadable($arrayDatas[$name]);
                    $history[$name] = $hr['datas'];
                    $history['unit'][$name] = $hr['unit'];
                }
            }
            else {
                $history[$name] = $arrayDatas[$name];
            }
        }

        return $history;
    }

    /**
     * Adapt datas in array to be "human readable". Ex: 2147483647 => 'data':2, 'unit':'GB'
     * @param array $datas Array containing the datas
     * @return array ['datas' => array, 'unit'=> string]
     */
    protected function humanreadable($datas) {
        // No need for keys here, but may seems more explicit
        $units = array(
            0 => '',
            1 => 'B',
            2 => 'KB',
            3 => 'MB',
            4 => 'GB',
            5 => 'TB',
            6 => 'PB',
        );

        $result = array();

        // init
        $greater = 0; // greater nb of type of unit used
        $unitChoice = 0; // index of this unit (that has the greater nb of values)
        $unitUsed = array();
        foreach($units as $k => $v) {
            $unitUsed[$k] = 0;
        }

        // find the most used unit
        foreach ($datas as $data) {
            $i = 1;
            $tempo = $data;
            while ($tempo / 1024 >= 1 and $i < count($units)) {
                $tempo = $tempo / 1024;
                $i++;
            }

            $unitUsed[$i]++;
            if ($unitUsed[$i] > $greater) {
                $greater = $unitUsed[$i];
                $unitChoice = $i;
            }
        }

        // adapt each data to the previously selected unit
        foreach ($datas as $data) {
            $data *= pow(1024, 1 - $unitChoice);

            array_push($result, round($data, 2));
        }

        return array(
            'datas' => $result,
            'unit' => $units[$unitChoice],
        );
    }
}

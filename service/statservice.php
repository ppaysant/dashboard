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
use \OCP\IDBConnection;

class PseudoUser
{
    protected $uid;

    function __construct($uid)
    {
        $this->uid = $uid;
    }

    function getUID($uid)
    {
        return $this->uid;
    }
}

class StatService
{

    protected $userManager;
    protected $rootStorage;
    protected $datas;
    protected $loggerService;
    protected $db;

    public function __construct($userManager, $rootStorage, LoggerService $loggerService, IDBConnection $db) {
        $this->userManager = $userManager;
        $this->rootStorage = $rootStorage;
        $this->loggerService = $loggerService;
        $this->db = $db;

        $this->datas = array();
    }

    public function getUserDataDir() {
        if (!isset($this->datas['dataDirectory'])) {
            $this->datas['dataDirectory'] =  \OCP\Config::getSystemValue('datadirectory', \OC::$SERVERROOT.'/data');
        }
        return $this->datas['dataDirectory'];
    }

    public function countUsers() {
        if (!isset($this->datas['nbUsers'])) {
            $nbUsers = 0;

            $nbUsersByBackend = $this->userManager->countUsers();

            if (!empty($nbUsersByBackend) and is_array($nbUsersByBackend)) {
                foreach($nbUsersByBackend as $backend => $count) {
                    $nbUsers += $count;
                }
            }

            $this->datas['nbUsers'] = $nbUsers;
        }

        return $this->datas['nbUsers'];
    }

    /**
     * Get some global usage stats (file nb, user nb, ...)
     * @param string $gid Id of the group of users from which you want stats (means "all users" if $gid = '')
     */
    public function getGlobalStorageInfo() {
        $view = new \OC\Files\View();
        $stats = array();
        $stats['totalSize'] = 0;
        $stats['defaultQuota'] = \OCP\Util::computerFileSize(\OCP\Config::getAppValue('files', 'default_quota', 'none'));

        $dataRoot = $this->getUserDataDir();

        // user list
        $users = \OCP\User::getUsers();

        $statsByGroup = false;
        // stat enabled groups list
        if (Helper::isDashboardGroupsEnabled()) {
            $statsByGroup = true;
            $statEnabledGroupList = Helper::getDashboardGroupList();
            $stats['groups'] = array();
        }

        $output = $this->loggerService->getOutput();

        foreach ($users as $uid) {
            $time_start = microtime(true);
            $date_start = date('H:i:s');

            $user = array();
            $user['filesize'] = 0;

            // group stats ?
            $groupList = array();
            if ($statsByGroup) {
                $userGroups = \OC::$server->getGroupManager()->getUserIdGroups($uid);
                $groupList = array_intersect($userGroups, $statEnabledGroupList);

                foreach($groupList as $group) {
                    if (!isset($stats['groups'][$group])) {
                        $stats['groups'][$group] = array();
                        $stats['groups'][$group]['nbUsers'] = 0;
                        $stats['groups'][$group]['filesize'] = 0;
                    }
                    $stats['groups'][$group]['nbUsers']++;
                }
            }

            // extract datas
            $this->getFilesStat($uid, $user);

            // files stats
            $stats['totalSize'] += $user['filesize'];

            // groups stats
            if ($statsByGroup) {
                foreach($groupList as $group) {
                    $stats['groups'][$group]['filesize'] += $user['filesize'];
                }
            }

            $time_end = microtime(true);
            $time = $time_end - $time_start;
            $date_end = date('H:i:s');

            $output->writeln($uid . ' : debut ' . $date_start . ', fin : ' . $date_end . ' (' . $time . ')');
        }

        // some basic stats
        $stats['sizePerUser'] = ($this->countUsers() == 0) ? $stats['totalSize'] : $stats['totalSize'] / $this->countUsers();

        // by groups
        if ($statsByGroup) {
            foreach(array_keys($stats['groups']) as $group) {
                $stats['groups'][$group]['sizePerUser'] = ($stats['groups'][$group]['nbUsers'] == 0) ? $stats['groups'][$group]['filesize'] : $stats['groups'][$group]['filesize'] / $stats['groups'][$group]['nbUsers'];
            }
        }

        return $stats;
    }

    /**
     * Get some user informations on files and folders
     * @param string $idUser
     * @param mixed $datas array to store the extrated infos
     */
    protected function getFilesStat($idUser, &$datas) {
        $datas['filesize'] = $this->diskUsage($idUser);
    }

    /**
     * Dirty function to extract owner from filepath
     * @param string $path
     * @return string owner of this filepath
     */
    protected function getOwner($path) {
        // admin files seem to begin with "//"
        if (strpos($path, "//") === 0) {
            return str_replace("//", "", $path);
        }

        preg_match("#^/([^/]*)/.*$#", $path, $matches);
        if (isset($matches[1])) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Returns disk space used by a user
     * @param  string $username (userId)
     * @return array ['user_id', 'size']
     */
    public function diskUsage($username) {
        $sql = "SELECT m.user_id, fc.size
            FROM oc_mounts m, oc_filecache fc, oc_storages s
            WHERE m.mount_point = CONCAT('/', :username, '/')
                AND s.numeric_id = m.storage_id
                AND fc.storage = m.storage_id
                AND fc.path = 'files'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':username' => $username,
        ]);

        $row = $stmt->fetch();

        return $row['size'];
    }
}

<?php

/**
 * ownCloud - Dashboard
 *
 * @author Patrick Paysant <ppaysant@linagora.com>
 * @copyright 2014 CNRS DSI
 * @license This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Dashboard\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use OC\DB\Connection;

// Number of lines inserted
define("DEFAULT_NB", 60);

class Populate extends Command {

    protected function configure() {
        $prefix = \OCP\Config::getSystemValue('dbtableprefix', 'oc_');

        $this
            ->setName('dashboard:populate')
            ->setDescription('Populate ' . $prefix . 'dashboard_history and ' . $prefix . 'dashboard_history_by_group (if needed) tables with random test datas')
            ->addArgument('nb', InputArgument::OPTIONAL, 'Number of days you want stats for.', DEFAULT_NB)
            ->addOption('truncate', 't', InputOption::VALUE_NONE, 'Delete all history datas before generating new ones.');
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $output->writeln('Beginning');

        $nb = (int)$input->getArgument('nb');

        if ($input->getOption('truncate')) {
            $this->truncate();
        }

        $date = new \DateTime();
        $date->sub(new \DateInterval('P' . $nb . 'D'));

        // arbitrary starting datas
        $stats['defaultQuota'] = 1073741824; // 1GB
        $stats['totalUsedSpace'] = 221877265;
        $stats['nbUsers'] = 4;

        $groupsEnabled = true;
        $groupsEnabledKey = \OCP\Config::getAppValue('dashboard', 'dashboard_groups_enabled', 'yes');
        if ($groupsEnabledKey !== 'yes') {
            $groupsEnabled = false;
        }
        $output->writeln("groupsEnabledKey : " . $groupsEnabledKey);

        for($i=0 ; $i < $nb; $i++) {
            $date->add(new \DateInterval('P1D'));
            $output->writeln('<info>' . 'Adding stats for date ' . $date->format('Y-m-d H:i:s') . '</info>');

            $way = rand(-1,1);

            $stats['totalUsedSpace'] = $this->addValue($stats['totalUsedSpace'], $way * rand(50000, 500000));
            $stats['nbUsers'] = $this->addValue($stats['nbUsers'], $way * rand(5, 20));

            $stats['sizePerUser'] = $stats['totalUsedSpace'] / $stats['nbUsers'];

            $this->addHistory($date, $stats);

            if ($groupsEnabled) {
                $nbGroups = round ($stats['nbUsers'] / 2) ;

                for ($gkey = 1 ; $gkey <= $nbGroups ; $gkey++) {
                    $groupName = 'group_' . $gkey;

                    $groupStats['nbUsers'] = rand(1, round($stats['nbUsers'] / 2));
                    $groupStats['filesize'] = rand(1, round($stats['totalUsedSpace'] / 2));

                    $groupStats['sizePerUser'] = $groupStats['filesize'] / $groupStats['nbUsers'];

                    $this->addHistoryByGroup($date, $groupName, $groupStats);
                }
            }
        }

        $output->writeln('Done');
    }

    /**
     * Avoid negative values in stats
     * @param int|float $stat Original value
     * @param int|float $value Increment
     * @return int|float Positive value
     */
    protected function addValue($stat, $value) {
        $stat += $value;

        if ($stat < 0) {
            $stat = -$stat;
        }

        return $stat;
    }

    /**
     * DB Insert for global stats
     * @param \DateTime $date Insert date
     * @param array $stats Global stats
     */
    protected function addHistory($date, $stats) {
        $sql = "INSERT INTO *PREFIX*dashboard_history
            SET date = :date,
                total_used_space = :totalUsedSpace,
                default_quota = :defaultQuota,
                nb_users = :nbUsers,
                size_per_user = :sizePerUser";

        $stmt = \OCP\DB::prepare($sql);
        $stmt->execute(array(
            ':date' => $date->format('Y-m-d H:i:s'),
            ':totalUsedSpace' => $stats['totalUsedSpace'],
            ':defaultQuota' => $stats['defaultQuota'],
            ':nbUsers' => $stats['nbUsers'],
            ':sizePerUser' => $stats['sizePerUser'],
        ));
    }

    /**
     * DB Insert for group stats
     * @param \DateTime $date Insert date
     * @param string $groupName Group id
     * @param array $groupStats Group's stats
     */
    protected function addHistoryByGroup($date, $groupName, $groupStats) {
        $sql = "INSERT INTO *PREFIX*dashboard_history_by_group
            SET date = :date,
                gid = :groupId,
                total_used_space = :totalUsedSpace,
                nb_users = :nbUsers,
                size_per_user = :sizePerUser";


        $stmt = \OCP\DB::prepare($sql);
        $stmt->execute(array(
            ':date' => $date->format('Y-m-d H:i:s'),
            ':groupId' => $groupName,
            ':totalUsedSpace' => $groupStats['filesize'],
            ':nbUsers' => $groupStats['nbUsers'],
            ':sizePerUser' => $groupStats['sizePerUser'],
        ));
    }

    protected function truncate() {
        $sql = "TRUNCATE *PREFIX*dashboard_history";
        $stmt = \OCP\DB::prepare($sql);
        $stmt->execute();

        $sql = "TRUNCATE *PREFIX*dashboard_history_by_group";
        $stmt = \OCP\DB::prepare($sql);
        $stmt->execute();
    }
}

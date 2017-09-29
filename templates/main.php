<?php

/**
 * ownCloud - Dashboard
 *
 * @author Patrick Paysant <ppaysant@linagora.com>
 * @copyright 2014 CNRS DSI
 * @license This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

\OCP\Util::addStyle('dashboard', 'dashboard');
\OCP\Util::addStyle('dashboard', 'c3.min');

\OCP\Util::addScript('dashboard', 'lib/angular');
\OCP\Util::addScript('dashboard', 'lib/d3.min');
\OCP\Util::addScript('dashboard', 'lib/c3.min');
\OCP\Util::addScript('dashboard', 'lib/c3-angular');
\OCP\Util::addScript('dashboard', 'app/directives/chartjs-directive');
\OCP\Util::addScript('dashboard', 'app/services/dashboard.services');
\OCP\Util::addScript('dashboard', 'app/dashboard');

?>

<div ng-app="dashboard" ng-controller="statsController">

<div id="dashboard">
    Dashboard (ultra) lite
</div>

<div id="container">

<div id="space" class="dataBlock">
    <p class="header"><?php p($l->t('Disk space')); ?> <span>{{ stats.history.totalUsedSpace | humanFileSize }}</span></p>
    <p><?php p($l->t('User data dir')); ?>: <span>{{ stats.userDataDir }}</span> </p>
    <p><?php p($l->t('Default quota per user')); ?>: <span>{{ stats.history.defaultQuota | humanFileSize }}</span></p>
</div>

<div id="users" class="dataBlock">
    <p class="header"><?php p($l->t('Users')); ?> <span>{{ stats.history.nbUsers }}</span></p>
    <p><?php p($l->t('Size / user')); ?>: <span>{{ stats.history.sizePerUser | humanFileSize }}</span></p>
</div>

<div class="history">
     <div>
        <select ng-model="dataType" ng-options="type for type in dataTypes" ng-change="chartUpdate(false)"></select>
        <select ng-model="nbDays" ng-options="choices as choices.label for choices in nbDaysChoices" ng-change="chartUpdate(false)"></select>
        <select ng-model="groupId" ng-options="groups as groups.id for groups in groupList" ng-change="chartUpdate(true)"></select>
    </div>
    <c3chart bindto-id="chart1" chart-data="datapoints" chart-columns="datacolumns" chart-x="datax" callback-function="handleCallback">
        <chart-colors color-pattern="#888"/>
        <chart-legend show-legend="false"/>
        <chart-size chart-width="800"/>
        <chart-axis>
            <chart-axis-x axis-id="x" axis-type="timeseries" axis-x-format="%Y-%m-%d">
                <chart-axis-x-tick tick-culling="true" tick-culling-max="15" tick-format-time="%d/%m"/>
            </chart-axis-x>
            <chart-axis-y>
                <chart-axis-y-tick tick-format=".2f" />
            </chart-axis-y>
        </chart-axis>
    </c3chart>
</div>

</div>

<div id="footer">
    <p>You're user id #{{ stats.uid }} (last log: {{ stats.userLastLogin }}) - last run: {{ stats.history.completeDate }} - Dashboard version #{{ stats.appVersion }}</p>
    <p>Icons provided by <a href="http://glyphicons.com/">GLYPHICONS.com</a>, released under <a href="http://creativecommons.org/licenses/by/3.0/">Creative Commons Attribution 3.0 Unported (CC BY 3.0)</a></p>
</div>

</div>

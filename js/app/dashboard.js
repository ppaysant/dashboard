/**
 * ownCloud - Dashboard
 *
 * @author Patrick Paysant <ppaysant@linagora.com>
 * @copyright 2014 CNRS DSI
 * @license This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

var dashboard = angular.module('dashboard', ['dashboard.services.stats', 'dashboard.services.chart', 'dashboard.services.groups', 'dashboard.filters', 'chartjs-directive', 'gridshore.c3js.chart']);

dashboard.config(['$httpProvider', function($httpProvider) {
    // CSRF protection
    $httpProvider.defaults.headers.common.requesttoken = oc_requesttoken;
}]);

dashboard.controller('statsController', ['$scope', 'statsService', 'groupsService', 'chartService', function($scope, statsService, groupsService, chartService) {
    _dataTypes = ['totalUsedSpace', 'nbUsers', 'sizePerUser'];
    _dataTypesByGroup = ['totalUsedSpace', 'nbUsers', 'sizePerUser'];
    $scope.dataTypes = _dataTypes;
    $scope.dataType = 'nbUsers';

    $scope.nbDaysChoices = [
        {nb: 7, label:'Last week'},
        {nb: 30, label:'Last month'},
        {nb: 180, label:'Last semester'},
        {nb: 365, label:'Last year'}
    ]
    $scope.nbDays = $scope.nbDaysChoices[1];

    $scope.dashboardGroupsEnabled = false;
    $scope.groupList = [];
    $scope.groupId = 'all';

    $scope.datapoints=[];
    $scope.datacolumns=[{"id":"nbUsers","type":"spline","name":"nbUsers"}];
    $scope.datax={"id":"date"};

    /**
     * Initialisation
     */
    $scope.init = function() {
        statsService.getStats()
            .success(function(data) {
                $scope.stats = data;
                chartType = "Line";
                jQuery(".dataBlock span").show();
            })
            .error(function(data, status) {
                if (status === 422) {
                    OC.Notification.showTemporary(t('dashboard', 'No stats to display for now', {}, undefined, {escape: false}));
                }
                console.log('Error: ' + data);
                $scope.error = true;
            });

        statsService.getHistoryStats('none', 'nbUsers', 30)
            .success(function(data) {
                var item = 'nbUsers';
                $scope.dataHistory = chartService.confChart(data, item, '');

                _.each(
                    data['date'],
                    function(value, index) {
                        var point = {};
                        point['date'] = value;
                        point[item] = data[item][index];

                        $scope.datapoints.push(point);
                    }
                );
            })
            .error(function(data) {
                console.log('Error: ' + data);
                $scope.error = true;
            });

        groupsService.isGroupsEnabled()
            .success(function(data) {
                $scope.dashboardGroupsEnabled = false;
                if (data.enabled == 'yes') {
                    $scope.dashboardGroupsEnabled = true;
                }
            })
            .error(function(data) {
                console.log('Error: ' + data);
                $scope.error = true;
            });

        groupsService.getStatsEnabledGroups()
            .success(function(data) {
                if (data == null || data == undefined) {
                    $scope.groupList = [];
                }
                else {
                    $scope.groupList = data.groups;
                    $scope.groupList.unshift({id:'none'});

                    $scope.groupId = $scope.groupList[0];
                }
            })
            .error(function(data) {
                console.log('Error: ' + data);
                $scope.error = true;
            });
    }
    $scope.init();

    $scope.chartUpdate = function(verifyGroupId) {
        if (verifyGroupId && $scope.groupId.id !== 'none') {
            $scope.dataTypes = _dataTypesByGroup;
            // if dataType is not available (standard variation are not provided for stats by group)
            if (_.indexOf($scope.dataTypes, $scope.dataType) === -1) {
                $scope.dataType = $scope.dataTypes[0];
            }
        }
        else {
            $scope.dataTypes = _dataTypes;
        }

        statsService.getHistoryStats($scope.groupId.id, $scope.dataType, $scope.nbDays.nb)
            .success(function(data) {
                var unit = '';
                if (data.unit && data.unit[$scope.dataType]) {
                    unit = data.unit[$scope.dataType];
                }
                $scope.dataHistory = chartService.confChart(data, $scope.dataType, unit);

                $scope.datacolumns=[{"id": $scope.dataType,"type": "spline","name": $scope.dataType}];
                $scope.datapoints = [];
                $scope.theChart.axis.labels({
                    x: '',
                    y: unit
                });
                _.each(
                    data['date'],
                    function(value, index) {
                        var point = {};
                        point['date'] = value;
                        point[$scope.dataType] = data[$scope.dataType][index];

                        $scope.datapoints.push(point);
                    }
                );
            })
            .error(function(data) {
                console.log('Error: ' + data);
                $scope.error = true;
            });
    }

    $scope.handleCallback = function(chartObject) {
        $scope.theChart = chartObject;
    }

}]);

angular.module('dashboard.filters', [])
    .filter('humanFileSize', function() {
        return function(size) {
            return OC.Util.humanFileSize(size, false);
        };
    });

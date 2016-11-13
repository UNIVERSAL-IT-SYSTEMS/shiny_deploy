(function () {
    "use strict";

    angular
        .module('shinyDeploy')
        .controller('BackupsDashboardController', BackupsDashboardController);

    BackupsDashboardController.$inject = [
        '$location',
        '$routeParams',
        '$scope',
        '$sce',
        'backupsService',
        'ws'
    ];

    function BackupsDashboardController(
        $location,
        $routeParams,
        $scope,
        $sce,
        backupsService,
        ws
    ) {
        /*jshint validthis: true */
        var vm = this;

        // Properties
        vm.backup = {};
        vm.servers = [];

        // Methods
        vm.triggerBackup = triggerBackup;

        // Init
        init();

        /**
         * Loads data required for run backup view.
         */
        function init() {
            // load backup:
            var backupId = ($routeParams.backupId) ? parseInt($routeParams.backupId) : 0;
            backupsService.getBackupData(backupId).then(function (data) {
                vm.backup = data;
            }, function(reason) {
                $location.path('/backups');
            });

            // load servers:
            backupsService.getServersList().then(function(data) {
                vm.servers = data;
            });
        }

        /**
         * Start new backup job.
         */
        function triggerBackup() {
            backupsService.triggerJob('runBackup', {
                backupId: vm.backup.id
            });
        }
    }
})();

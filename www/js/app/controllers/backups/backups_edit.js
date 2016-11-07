(function () {
    "use strict";

    angular
        .module('shinyDeploy')
        .controller('BackupsEditController', BackupsEditController);

    BackupsEditController.$inject = ['$location', '$routeParams', 'backupsService', 'alertsService'];

    function BackupsEditController($location, $routeParams, backupsService, alertsService) {
        /*jshint validthis: true */
        var vm = this;

        // Properties
        vm.isEdit = true;
        vm.servers = {};
        vm.backup = {};

        // Methods
        vm.updateBackup = updateBackup;

        // Init
        init();

        /**
         * Loads data required for edit backup form.
         */
        function init() {
            // load servers:
            backupsService.getServers().then(function (data) {
                vm.servers = data;
            }, function(reason) {
                console.log('Error fetching servers: ' + reason);
            });

            // load backup:
            var backupId = ($routeParams.backupId) ? parseInt($routeParams.backupId) : 0;
            backupsService.getBackupData(backupId).then(function(data) {
                vm.backup = data;
            }, function() {
                $location.path('/backups');
            });
        }

        /**
         * Updates backup data.
         */
        function updateBackup() {
            backupsService.updateBackup(vm.backup).then(function () {
                alertsService.pushAlert('Backup successfully updated.', 'success');
            }, function (reason) {
                alertsService.pushAlert(reason, 'warning');
            });
        }
    }
})();

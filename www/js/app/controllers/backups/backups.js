(function () {
    "use strict";

    angular
        .module('shinyDeploy')
        .controller('BackupsController', BackupsController);

    BackupsController.$inject = ['backupsService', 'alertsService'];

    function BackupsController(backupsService, alertsService) {
        /*jshint validthis: true */
        var vm = this;

        vm.backups = null;

        vm.getBackups = getBackups;
        vm.deleteBackup = deleteBackup;

        init();

        /**
         * Loads data required for backups list view.
         */
        function init() {
            var promise = backupsService.getBackups();
            promise.then(function(data) {
                vm.backups = data;
            }, function(reason) {
                console.log('Error fetching backups: ' + reason);
            });
        }

        /**
         * Returns list of backups.
         *
         * @returns {null|Array}
         */
        function getBackups() {
            return vm.backups;
        }

        /**
         * Removes a backup.
         *
         * @param {number} backupId
         */
        function deleteBackup(backupId) {
            backupsService.deleteBackup(backupId).then(function() {
                for (var i = vm.backups.length - 1; i >= 0; i--) {
                    if (vm.backups[i].id === backupId) {
                        vm.backups.splice(i, 1);
                        break;
                    }
                }
                alertsService.pushAlert('Backup successfully deleted.', 'success');
            }, function(reason) {
                alertsService.pushAlert(reason, 'warning');
            });
        }
    }
})();

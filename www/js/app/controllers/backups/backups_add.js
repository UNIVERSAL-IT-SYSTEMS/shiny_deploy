(function () {
    "use strict";

    angular
        .module('shinyDeploy')
        .controller('BackupsAddController', BackupsAddController);

    BackupsAddController.$inject = ['$location', 'backupsService', 'alertsService'];

    function BackupsAddController($location, backupsService, alertsService) {
        /*jshint validthis: true */
        var vm = this;

        // Properties
        vm.isAdd = true;
        vm.backup = {};
        vm.servers = {};

        // Methods
        vm.addBackup = addBackup;

        // Init
        init();

        /**
         * Loads data required for add backup form.
         */
        function init() {
            // load servers:
            backupsService.getServers().then(function (data) {
                vm.servers = data;
            }, function(reason) {
                console.log('Error fetching servers: ' + reason);
            });
        }

        /**
         * Requests add-backup action on project backend.
         */
        function addBackup() {
            backupsService.addBackup(vm.backup).then(function() {
                $location.path('/backups');
                alertsService.queueAlert('Backup successfully added.', 'success');
            }, function(reason) {
                alertsService.pushAlert(reason, 'warning');
            });
        }
    }
})();

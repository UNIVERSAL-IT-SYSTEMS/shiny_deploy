app.service('backupsService', ['ws', '$q', function (ws, $q) {

    /**
     * Fetches list of backups.
     *
     * @returns {promise}
     */
    this.getBackups = function () {
        return ws.sendDataRequest('getBackups');
    };

    /**
     * Adds new backup.
     *
     * @param {Array} backupData
     * @returns {promise}
     */
    this.addBackup = function (backupData) {
        var requestParams = {
            backupData: backupData
        };
        return ws.sendDataRequest('addBackup', requestParams);
    };

    /**
     * Fetches list of servers.
     *
     * @returns {promise}
     */
    this.getServers = function() {
        return ws.sendDataRequest('getServers');
    };
}]);

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
     * Updates existing backup.
     *
     * @param {Array} backupData
     * @returns {promise}
     */
    this.updateBackup = function (backupData) {
        var requestParams = {
            backupData: backupData
        };
        return ws.sendDataRequest('updateBackup', requestParams);
    };

    /**
     * Removes a backup from database.
     *
     * @param {number} backupId
     * @returns {promise}
     */
    this.deleteBackup = function (backupId) {
        var requestParams = {
            backupId: backupId
        };
        return ws.sendDataRequest('deleteBackup', requestParams);
    };

    /**
     * Fetches data for a backup.
     *
     * @param {number} backupId
     * @returns {bool|promise}
     */
    this.getBackupData = function(backupId) {
        if (backupId === 0) {
            return false;
        }

        var deferred = $q.defer();
        var requestParams = {
            backupId: backupId
        };

        ws.sendDataRequest('getBackupData', requestParams).then(function(data) {
            deferred.resolve(data);
        });

        return deferred.promise;
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

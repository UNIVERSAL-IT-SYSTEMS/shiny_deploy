app.service('backupsService', ['ws', '$q', function (ws, $q) {

    /**
     * Fetches list of backups.
     *
     * @returns {promise}
     */
    this.getBackups = function () {
        return ws.sendDataRequest('getBackups');
    };
}]);

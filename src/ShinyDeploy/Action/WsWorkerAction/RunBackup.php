<?php namespace ShinyDeploy\Action\WsWorkerAction;

use RuntimeException;
use ShinyDeploy\Domain\Database\Auth;
use ShinyDeploy\Domain\Database\Backups;
use ShinyDeploy\Exceptions\MissingDataException;
use ShinyDeploy\Responder\WsLogResponder;
use ShinyDeploy\Responder\WsNotificationResponder;

class RunBackup extends WsWorkerAction
{
    /**
     * Backups files from source to target server.
     *
     * @param int $id
     * @return boolean
     * @throws MissingDataException
     */
    public function __invoke($id)
    {
        $backupId = (int)$id;
        if (empty($backupId)) {
            throw new MissingDataException('Backup-ID can not be empty');
        }

        // get users encryption key:
        $auth = new Auth($this->config, $this->logger);
        $encryptionKey = $auth->getEncryptionKeyFromToken($this->token);
        if (empty($encryptionKey)) {
            throw new RuntimeException('Could not get encryption key.');
        }

        // Init responder:
        $logResponder = new WsLogResponder($this->config, $this->logger);
        $logResponder->setClientId($this->clientId);
        $notificationResponder = new WsNotificationResponder($this->config, $this->logger);
        $notificationResponder->setClientId($this->clientId);

        // Prepare backup object:
        $backups = new Backups($this->config, $this->logger);
        $backups->setEnryptionKey($encryptionKey);
        $backup = $backups->getBackup($backupId);
        $backup->setLogResponder($logResponder);

        // Check prerequisites:
        $logResponder->log('Checking prerequisites...');
        $prerequisitesCheck = $backup->checkPrerequisites();
        if ($prerequisitesCheck !== true) {
            $notificationResponder->send('Prerequisites check failed. Backup aborted.', 'danger');
            return false;
        }
        $logResponder->info('Prerequisites check completed without errors.');

        // Run backup:
        $logResponder->log('Starting backup...');
        $backupResult = $backup->runBackup();
        if ($backupResult !== true) {
            $notificationResponder->send('Backup failed.', 'danger');
            return false;
        }

        // Send success notification
        $notificationResponder->send('Backup successfully completed.', 'success');
        return true;
    }
}

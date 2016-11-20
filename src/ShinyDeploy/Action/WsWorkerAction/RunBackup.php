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

        // Init responder
        $logResponder = new WsLogResponder($this->config, $this->logger);
        $logResponder->setClientId($this->clientId);
        $notificationResponder = new WsNotificationResponder($this->config, $this->logger);
        $notificationResponder->setClientId($this->clientId);

        // Start backup
        $logResponder->log('Starting backup...');

        $backups = new Backups($this->config, $this->logger);
        $backups->setEnryptionKey($encryptionKey);
        $backup = $backups->getBackup($backupId);

        // @todo Implement actual backup methods...
        $logResponder->success('Backup successfully completed.');

        // Send success notification
        $notificationResponder->send('Backup successfully completed.', 'success');
        return true;
    }
}

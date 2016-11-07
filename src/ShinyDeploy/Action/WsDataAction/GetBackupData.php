<?php
namespace ShinyDeploy\Action\WsDataAction;

use ShinyDeploy\Domain\Database\Auth;
use ShinyDeploy\Domain\Database\Backups;
use ShinyDeploy\Exceptions\InvalidPayloadException;

class GetBackupData extends WsDataAction
{
    /**
     * Fetches backup data from database.
     *
     * @param array $actionPayload
     * @return boolean
     * @throws InvalidPayloadException
     */
    public function __invoke(array $actionPayload)
    {
        $this->authorize($this->clientId);

        if (!isset($actionPayload['backupId'])) {
            throw new InvalidPayloadException('Invalid getBackupData request received.');
        }

        // get users encryption key:
        $auth = new Auth($this->config, $this->logger);
        $encryptionKey = $auth->getEncryptionKeyFromToken($this->token);
        if (empty($encryptionKey)) {
            $this->responder->setError('Could not get encryption key.');
            return false;
        }

        $backupId = (int)$actionPayload['backupId'];
        $backups = new Backups($this->config, $this->logger);
        $backups->setEnryptionKey($encryptionKey);
        $backupData = $backups->getBackupData($backupId);
        if (empty($backupData)) {
            $this->responder->setError('Backup not found in database.');
            return false;
        }
        $this->responder->setPayload($backupData);
        return true;
    }
}

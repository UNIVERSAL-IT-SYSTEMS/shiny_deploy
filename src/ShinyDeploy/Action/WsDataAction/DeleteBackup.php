<?php
namespace ShinyDeploy\Action\WsDataAction;

use ShinyDeploy\Domain\Database\Backups;
use ShinyDeploy\Exceptions\InvalidPayloadException;

class DeleteBackup extends WsDataAction
{
    /**
     * Removes a backup from database.
     *
     * @param array $actionPayload
     * @return boolean
     * @throws InvalidPayloadException
     */
    public function __invoke(array $actionPayload)
    {
        $this->authorize($this->clientId);

        if (!isset($actionPayload['backupId'])) {
            throw new InvalidPayloadException('Invalid deleteBackup request received.');
        }
        $backupId = (int)$actionPayload['backupId'];
        $backups = new Backups($this->config, $this->logger);

        // remove server:
        $result = $backups->deleteBackup($backupId);
        if ($result === false) {
            $this->responder->setError('Could not remove backup from database.');
            return false;
        }
        return true;
    }
}

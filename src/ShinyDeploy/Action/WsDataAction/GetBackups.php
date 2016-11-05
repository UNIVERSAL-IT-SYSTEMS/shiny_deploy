<?php
namespace ShinyDeploy\Action\WsDataAction;

use ShinyDeploy\Domain\Database\Auth;
use ShinyDeploy\Domain\Database\Backups;

class GetBackups extends WsDataAction
{
    /**
     * Fetches a backups list
     *
     * @param array $actionPayload
     * @return bool
     */
    public function __invoke(array $actionPayload)
    {
        $this->authorize($this->clientId);

        // get users encryption key:
        $auth = new Auth($this->config, $this->logger);
        $encryptionKey = $auth->getEncryptionKeyFromToken($this->token);
        if (empty($encryptionKey)) {
            $this->responder->setError('Could not get encryption key.');
            return false;
        }

        $backups = new Backups($this->config, $this->logger);
        $backups->setEnryptionKey($encryptionKey);
        $backupsData = $backups->getBackups();
        $this->responder->setPayload($backupsData);
        return true;
    }
}

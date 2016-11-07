<?php
namespace ShinyDeploy\Action\WsDataAction;

use ShinyDeploy\Domain\Database\Auth;
use ShinyDeploy\Domain\Database\Backups;
use ShinyDeploy\Exceptions\InvalidPayloadException;
use Valitron\Validator;

class UpdateBackup extends WsDataAction
{
    /**
     * Updates backup data in database.
     *
     * @param array $actionPayload
     * @return boolean
     * @throws InvalidPayloadException
     */
    public function __invoke(array $actionPayload)
    {
        $this->authorize($this->clientId);

        if (!isset($actionPayload['backupData'])) {
            throw new InvalidPayloadException('Invalid updateBackup request received.');
        }
        $backupData = $actionPayload['backupData'];
        $backups = new Backups($this->config, $this->logger);

        // validate input:
        $validator = new Validator($backupData);
        $validator->rules($backups->getUpdateRules());
        if (!$validator->validate()) {
            $this->responder->setError('Input validation failed. Please check your data.');
            return false;
        }

        // get users encryption key:
        $auth = new Auth($this->config, $this->logger);
        $encryptionKey = $auth->getEncryptionKeyFromToken($this->token);
        if (empty($encryptionKey)) {
            $this->responder->setError('Could not get encryption key.');
            return false;
        }

        // update backup:
        $backups->setEnryptionKey($encryptionKey);
        $updateResult = $backups->updateBackup($backupData);
        if ($updateResult === false) {
            $this->responder->setError('Could not update backup.');
            return false;
        }
        return true;
    }
}

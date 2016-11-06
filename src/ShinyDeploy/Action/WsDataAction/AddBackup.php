<?php
namespace ShinyDeploy\Action\WsDataAction;

use ShinyDeploy\Domain\Database\Auth;
use ShinyDeploy\Domain\Database\Backups;
use ShinyDeploy\Exceptions\InvalidPayloadException;
use Valitron\Validator;

class AddBackup extends WsDataAction
{
    /**
     * Adds new backup to database.
     *
     * @param array $actionPayload
     * @return boolean
     * @throws InvalidPayloadException
     */
    public function __invoke(array $actionPayload)
    {
        $this->authorize($this->clientId);

        if (!isset($actionPayload['backupData'])) {
            throw new InvalidPayloadException('Invalid addBackup request received.');
        }
        $backupData = $actionPayload['backupData'];
        $backups = new Backups($this->config, $this->logger);

        // validate input:
        $validator = new Validator($backupData);
        $validator->rules($backups->getCreateRules());
        if (!$validator->validate()) {
            $this->responder->setError('Input validation failed. Please check your data.');
            return false;
        }

        // @todo Check if other backups save files to same target

        // get users encryption key:
        $auth = new Auth($this->config, $this->logger);
        $encryptionKey = $auth->getEncryptionKeyFromToken($this->token);
        if (empty($encryptionKey)) {
            $this->responder->setError('Could not get encryption key.');
            return false;
        }

        // add backup:
        $backups->setEnryptionKey($encryptionKey);
        $addResult = $backups->addBackup($backupData);
        if ($addResult === false) {
            $this->responder->setError('Could not add backup to database.');
            return false;
        }
        return true;
    }
}

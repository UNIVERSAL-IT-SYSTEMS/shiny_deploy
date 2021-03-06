<?php namespace ShinyDeploy\Action\WsDataAction;

use ShinyDeploy\Action\WsDataAction\WsDataAction;
use ShinyDeploy\Domain\Database\ApiKeys;
use ShinyDeploy\Domain\Database\Auth;
use ShinyDeploy\Exceptions\InvalidPayloadException;
use ShinyDeploy\Exceptions\MissingDataException;

class GenerateApiKey extends WsDataAction
{
    /**
     * Generates a new API key and stores it to database.
     * 
     * @param array $actionPayload
     * @return boolean
     * @throws InvalidPayloadException
     * @throws MissingDataException
     */
    public function __invoke(array $actionPayload)
    {
        $this->authorize($this->clientId);

        if (!isset($actionPayload['deploymentData'])) {
            throw new InvalidPayloadException('Invalid updateDeployment request received.');
        }
        $deploymentData = $actionPayload['deploymentData'];
        if (empty($deploymentData['id'])) {
            throw new MissingDataException('Deployment id can not be empty.');
        }

        // get users encryption key:
        $auth = new Auth($this->config, $this->logger);
        $encryptionKey = $auth->getEncryptionKeyFromToken($this->token);
        if (empty($encryptionKey)) {
            $this->responder->setError('Could not get encryption key.');
            return false;
        }

        // generate API key:
        $apiKeys = new ApiKeys($this->config, $this->logger);
        $apiKeys->setEnryptionKey($encryptionKey);
        $apiKeys->deleteApiKeysByDeploymentId($deploymentData['id']);
        $apiKeyData = $apiKeys->addApiKey($deploymentData['id']);
        $this->responder->setPayload($apiKeyData);
        return true;
    }
}

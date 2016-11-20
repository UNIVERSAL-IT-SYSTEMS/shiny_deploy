<?php
namespace ShinyDeploy\Domain;

use RuntimeException;
use ShinyDeploy\Core\Domain;
use ShinyDeploy\Core\Responder;
use ShinyDeploy\Domain\Database\Servers;
use ShinyDeploy\Traits\CryptableDomain;

class Backup extends Domain
{
    use CryptableDomain;

    /** @var \ShinyDeploy\Responder\WsLogResponder $logResponder */
    protected $logResponder;

    /** @var \ShinyDeploy\Domain\Server\SshServer $sourceServer */
    protected $sourceServer;

    /** @var \ShinyDeploy\Domain\Server\Server $targetServer */
    protected $targetServer;

    public function init(array $data)
    {
        $this->data = $data;
        $servers = new Servers($this->config, $this->logger);
        $servers->setEnryptionKey($this->encryptionKey);
        $this->sourceServer = $servers->getServer($data['source_server_id']);
        $this->targetServer = $servers->getServer($data['target_server_id']);
    }

    /**
     * Setter for the websocket log responder.
     *
     * @param Responder $logResponder
     */
    public function setLogResponder(Responder $logResponder)
    {
        $this->logResponder = $logResponder;
    }

    /**
     * Checks if all requirements are met to do a backup.
     *
     * @return bool
     */
    public function checkPrerequisites()
    {
        // Check if required data is set:
        if (empty($this->data)) {
            throw new RuntimeException('Backup data missing. Object probably not initialized.');
        }
        if (empty($this->sourceServer)) {
            throw new RuntimeException('Source server object can not be empty.');
        }
        if (empty($this->targetServer)) {
            throw new RuntimeException('Target server object can not be empty.');
        }

        // Check if source and target server are of type ssh:
        if ($this->sourceServer->getType() !== 'ssh') {
            $this->logResponder->error('Source server is not of type SSH. Aborting backup.');
            return false;
        }

        // @todo Does is work with SFTP target?
        /*
        if ($this->targetServer->getType() !== 'ssh') {
            $this->logResponder->error('Target server is not of type SSH. Aborting backup.');
            return false;
        }
        */

        // Check connectivity of source and target server:
        if ($this->sourceServer->checkConnectivity() !== true) {
            $this->logResponder->error('Could not connect to source server. Aborting backup.');
            return false;
        }
        if ($this->targetServer->checkConnectivity() !== true) {
            $this->logResponder->error('Could not connect to target server. Aborting backup.');
            return false;
        }

        // Check availability of "tar" on source server:
        $cmdResult = $this->sourceServer->executeCommand('which tar');
        if ($cmdResult === false) {
            $this->logResponder->error('Could not execute ssh command on source server. Aborting backup.');
            return false;
        }
        $cmdResult = trim($cmdResult);
        if (empty($cmdResult) || strpos($cmdResult, 'tar') === false) {
            $this->logResponder->error('Tar not found on source server. Aborting backup.');
            return false;
        }
        $this->logResponder->log('Tar found at: ' . $cmdResult);

        // Check availability of "scp" on source server:
        $cmdResult = $this->sourceServer->executeCommand('which scp');
        if ($cmdResult === false) {
            $this->logResponder->error('Could not execute ssh command on source server. Aborting backup.');
            return false;
        }
        $cmdResult = trim($cmdResult);
        if (empty($cmdResult) || strpos($cmdResult, 'scp') === false) {
            $this->logResponder->error('Scp not found on source server. Aborting backup.');
            return false;
        }
        $this->logResponder->log('Scp found at: ' . $cmdResult);

        return true;
    }

    public function runBackup()
    {
        if (empty($this->data)) {
            throw new RuntimeException('Backup data missing. Object probably not initialized.');
        }
        if (empty($this->sourceServer)) {
            throw new RuntimeException('Source server object can not be empty.');
        }
        if (empty($this->targetServer)) {
            throw new RuntimeException('Target server object can not be empty.');
        }
    }
}

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

    protected $sourceServer;

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

<?php
namespace ShinyDeploy\Domain\Database;

use InvalidArgumentException;
use RuntimeException;
use ShinyDeploy\Domain\Server\Server;
use ShinyDeploy\Domain\Server\SftpServer;
use ShinyDeploy\Domain\Server\SshServer;
use ShinyDeploy\Traits\CryptableDomain;

class Servers extends DatabaseDomain
{
    use CryptableDomain;

    /** @var array $rules Validation rules */
    protected $rules = [
        'required' => [
            ['name'],
            ['type'],
            ['hostname'],
            ['port'],
            ['root_path']
        ],
        'integer' => [
            ['port']
        ],
        'in' => [
            ['type', ['sftp', 'ssh']]
        ],
        'hostname' => [
            ['hostname']
        ],
    ];

    /** @var array $encryptedFields Fields that are encrypted in database. */
    protected $encryptedFields = [
        'hostname',
        'port',
        'username',
        'password',
        'root_path',
    ];

    /**
     * Get validation rules for insert queries.
     *
     * @return array
     */
    public function getCreateRules()
    {
        return $this->rules;
    }

    /**
     * Get validation rules for update queries.
     *
     * @return array
     */
    public function getUpdateRules()
    {
        $rules = $this->rules;
        $rules['required'][] = ['id'];
        return $this->rules;
    }

    /**
     * Creates and returns a server object.
     *
     * @param type $serverId
     * @return Server
     * @throws RuntimeException
     */
    public function getServer($serverId)
    {
        $data = $this->getServerData($serverId);
        if (empty($data)) {
            throw new RuntimeException('Server not found in database.');
        }
        switch ($data['type']) {
            case 'ssh':
                $server = new SshServer($this->config, $this->logger);
                break;
            case 'sftp':
                $server = new SftpServer($this->config, $this->logger);
                break;
            default:
                throw new \RuntimeException('Invalid server type.');
        }
        $server->init($data);
        return $server;
    }

    /**
     * Fetches list of servers from database.
     *
     * @return array|bool
     */
    public function getServers()
    {
        $rows = $this->db->prepare("SELECT * FROM servers ORDER BY `name`")->getResult(false);
        if (empty($rows)) {
            return $rows;
        }
        foreach ($rows as $i => $row) {
            $decryptedRow = $this->decryptData($row, $this->encryptedFields);
            if ($decryptedRow === false) {
                throw new RuntimeException('Date decryption failed.');
            }
            $rows[$i] = $decryptedRow;
        }
        return $rows;
    }

    /**
     * Stores new server in database.
     *
     * @param array $serverData
     * @return bool
     */
    public function addServer(array $serverData)
    {
        $this->prepareDataForSave($serverData);
        $serverData = $this->encryptData($serverData, $this->encryptedFields);
        if ($serverData === false) {
            throw new RuntimeException('Data encryption failed.');
        }

        return $this->db->prepare(
            "INSERT INTO servers
              (`name`, `type`, `hostname`, `port`, `username`, `password`, `root_path`)
              VALUES
                (%s, %s, %s, %s, %s, %s, %s)",
            $serverData['name'],
            $serverData['type'],
            $serverData['hostname'],
            $serverData['port'],
            $serverData['username'],
            $serverData['password'],
            $serverData['root_path']
        )->execute();
    }

    /**
     * Updates server.
     *
     * @param array $serverData
     * @return bool
     */
    public function updateServer(array $serverData)
    {
        if (!isset($serverData['id'])) {
            return false;
        }

        $this->prepareDataForSave($serverData);
        $serverData = $this->encryptData($serverData, $this->encryptedFields);
        if ($serverData === false) {
            throw new RuntimeException('Data encryption failed.');
        }

        return $this->db->prepare(
            "UPDATE servers
            SET `name` = %s,
              `type` = %s,
              `hostname` = %s,
              `port` = %s,
              `username` = %s,
              `password` = %s,
              `root_path` = %s
            WHERE id = %d",
            $serverData['name'],
            $serverData['type'],
            $serverData['hostname'],
            $serverData['port'],
            $serverData['username'],
            $serverData['password'],
            $serverData['root_path'],
            $serverData['id']
        )->execute();
    }

    /**
     * Deletes a server.
     *
     * @param int $serverId
     * @return bool
     */
    public function deleteServer($serverId)
    {
        $serverId = (int)$serverId;
        if ($serverId === 0) {
            return false;
        }
        return $this->db->prepare("DELETE FROM servers WHERE id = %d LIMIT 1", $serverId)->execute();
    }

    /**
     * Fetches server data.
     *
     * @param int $serverId
     * @return array
     */
    public function getServerData($serverId)
    {
        $serverId = (int)$serverId;
        if ($serverId === 0) {
            return [];
        }
        $serverData = $this->db->prepare("SELECT * FROM servers WHERE id = %d", $serverId)->getResult(true);
        if (empty($serverData)) {
            return [];
        }

        $serverData = $this->decryptData($serverData, $this->encryptedFields);
        if ($serverData === false) {
            throw new RuntimeException('Data decryption failed.');
        }
        return $serverData;
    }

    /**
     * Checks whether any relations to given server exist.
     *
     * @param int $serverId
     * @return bool
     * @throws InvalidArgumentException
     */
    public function serverInUse($serverId)
    {
        $serverId = (int)$serverId;
        if (empty($serverId)) {
            throw  new InvalidArgumentException('serverId can not be empty.');
        }
        $cnt = $this->db->prepare("SELECT COUNT(id) FROM deployments WHERE `server_id` = %d", $serverId)->getValue();
        return ($cnt > 0);
    }

    /**
     * Prepares server-data for save into database.
     *
     * @param array $serverData
     */
    protected function prepareDataForSave(array &$serverData)
    {
        $serverData['root_path'] = trim($serverData['root_path']);
        $serverData['root_path'] = rtrim($serverData['root_path'], '/');
        if (empty($serverData['root_path'])) {
            $serverData['root_path'] = '/';
        }
    }
}

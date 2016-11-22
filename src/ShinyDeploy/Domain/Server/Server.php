<?php
namespace ShinyDeploy\Domain\Server;

use ShinyDeploy\Core\Domain;

abstract class Server extends Domain
{
    abstract public function getType();

    abstract public function connect($host, $user, $pass, $port = 22);

    abstract public function getFileContent($path);

    abstract public function upload($localFile, $remoteFile, $mode = 0644);

    abstract public function putContent($content, $remoteFile, $mode = 0644);

    abstract public function delete($remoteFile);

    abstract public function listDir($remotePath);

    abstract public function checkConnectivity();

    public function init(array $data)
    {
        parent::init($data);
        $this->connect(
            $this->data['hostname'],
            $this->data['username'],
            $this->data['password'],
            $this->data['port']
        );
    }

    /**
     * Returns servers root path.
     *
     * @return string
     * @throws \RuntimeException
     */
    public function getRootPath()
    {
        if (empty($this->data)) {
            throw new \RuntimeException('Server data not found. Initialization missing?');
        }
        $remotePath = trim($this->data['root_path']);
        return $remotePath;
    }


    /**
     * Returns servers username.
     *
     * @return string
     */
    public function getUsername()
    {
        if (!isset($this->data['username'])) {
            throw new \RuntimeException('Username not set in server object.');
        }
        return $this->data['username'];
    }

    /**
     * Returns servers password.
     *
     * @return string
     */
    public function getPassword()
    {
        if (!isset($this->data['password'])) {
            throw new \RuntimeException('Password not set in server object.');
        }
        return $this->data['password'];
    }

    /**
     * Returns servers hostname.
     *
     * @return string
     */
    public function getHostname()
    {
        if (!isset($this->data['hostname'])) {
            throw new \RuntimeException('Hostname not set in server object.');
        }
        return $this->data['hostname'];
    }

    /**
     * Returns servers port.
     *
     * @return int
     */
    public function getPort()
    {
        if (!isset($this->data['port'])) {
            throw new \RuntimeException('Port not set in server object.');
        }
        return (int)$this->data['port'];
    }

    /**
     * Check if sever uses a password for authentication.
     *
     * @return bool
     */
    public function usesPasswordAuth()
    {
        return !empty($this->data['password']);
    }
}

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

    /** @var string $backupPath */
    protected $sourceBackupPath = '';

    /** @var string $targetBackupPath */
    protected $targetBackupPath = '';

    /** @var string $backupFilename */
    protected $backupFilename = '';

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

        // Check connectivity of source and target server:
        if ($this->sourceServer->checkConnectivity() !== true) {
            $this->logResponder->error('Could not connect to source server. Aborting backup.');
            return false;
        }
        if ($this->targetServer->checkConnectivity() !== true) {
            $this->logResponder->error('Could not connect to target server. Aborting backup.');
            return false;
        }

        // Collect binaries required on source-server:
        $binaries = ['tar', 'gzip', 'scp'];
        if ($this->targetServer->usesPasswordAuth() === true) {
            array_push($binaries, 'sshpass');
        }

        // Check if required binaries exist on source server:
        foreach ($binaries as $binary) {
            $checkResult = $this->sourceServer->binaryExists($binary);
            if ($checkResult === false) {
                $this->logResponder->error(sprintf('%s not found on source server. Aborting backup.', $binary));
                return false;
            }
        }

        return true;
    }

    /**
     * Executes a backup by compressing source folder on source server and than uploading this backup file
     * to target server.
     *
     * @todo Add verification after upload (checksum)
     *
     * @return bool
     */
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

        // Compress files on source server:
        $this->logResponder->log('Compressing files on source server...');
        $this->setPaths();
        $compressResult = $this->compressSource();
        if ($compressResult !== true) {
            $this->logResponder->error('Could not compress files on source server. Aborting backup.');
            return false;
        }
        $this->logResponder->log(sprintf('Files successfully compressed to: %s', $this->backupFilename));

        // Upload backup to target server:
        $this->logResponder->log('Uploading backup to target server...');
        $uploadResult = $this->uploadBackupToTarget();
        if ($uploadResult !== true) {
            $this->logResponder->error('Could not upload backup to target server. Aborting backup.');
            return false;
        }
        $this->logResponder->log('Backup successfully uploaded to target server.');

        // Remove backup on source server:
        $removeResult = $this->removeBackupSource();
        if ($removeResult !== true) {
            $this->logResponder->info('Could not remove backup-archive on source server.');
        }
        $this->logResponder->log('Backup source successfully deleted.');
        $this->logResponder->success('Backup completed.');

        return true;
    }

    /**
     * Compresses a folder on source server into a backup archive.
     *
     * @return bool
     */
    protected function compressSource()
    {
        $compressCommandRaw = 'cd %s && tar --exclude=%s -c . | gzip -9 > %s';
        $compressCommand = sprintf(
            $compressCommandRaw,
            $this->sourceBackupPath,
            $this->backupFilename,
            $this->backupFilename
        );
        $compressResult = $this->sourceServer->executeCommand($compressCommand);
        if ($compressResult === false) {
            return false;
        }
        $checkCommand = 'file ' . $this->sourceBackupPath . '/' . $this->backupFilename;
        $checkResult = $this->sourceServer->executeCommand($checkCommand);
        if (empty($checkResult) || stripos($checkResult, 'no such') !== false) {
            return false;
        }
        return true;
    }

    /**
     * Uploads a backup archive from source to target server.
     *
     * @return bool
     */
    protected function uploadBackupToTarget()
    {
        if (empty($this->backupFilename)) {
            throw new RuntimeException('Backup filename no set. Can not move backup.');
        }
        $sshPassCommand = '';
        if ($this->targetServer->usesPasswordAuth() === true) {
            $sshPassCommand = "sshpass -p '%s' ";
            $sshPassCommand = sprintf($sshPassCommand, $this->targetServer->getPassword());
        }

        $rawUploadCommand = $sshPassCommand
            . 'scp -q -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -P %d %s %s@%s:%s || echo "failed."';
        $uploadCommand = sprintf(
            $rawUploadCommand,
            $this->targetServer->getPort(),
            $this->sourceBackupPath . '/' . $this->backupFilename,
            $this->targetServer->getUsername(),
            $this->targetServer->getHostname(),
            $this->targetBackupPath
        );
        $uploadResult = $this->sourceServer->executeCommand($uploadCommand);
        if ($uploadResult === false) {
            return false;
        }
        $uploadResult = trim($uploadResult);
        return empty($uploadResult);
    }

    /**
     * Sets backup paths and filename for source and target server.
     */
    protected function setPaths()
    {
        // set source backup path:
        $sourceServerRoot = $this->sourceServer->getRootPath();
        $sourceServerRoot = trim($sourceServerRoot, '/');
        $sourceBackupPath = trim($this->data['source_server_path'], '/');
        $this->sourceBackupPath = '/' . $sourceServerRoot . '/' . $sourceBackupPath;
        $this->sourceBackupPath = str_replace('//', '/', $this->sourceBackupPath);

        // set target backup path:
        $targetServerRoot = $this->targetServer->getRootPath();
        $targetServerRoot = trim($targetServerRoot, '/');
        $targetBackupPath = trim($this->data['target_server_path'], '/');
        $this->targetBackupPath = '/' . $targetServerRoot . '/' . $targetBackupPath;
        $this->targetBackupPath = str_replace('//', '/', $this->targetBackupPath);

        // set backup filename:
        $backupName = strtolower($this->data['name']);
        $basename = preg_replace('[^a-z0-9-_.]', '', $backupName);
        $filename = $basename . '_' . strftime('%Y-%m-%d_%H%M') . '.tar.gz';
        $this->backupFilename = $filename;
    }

    /**
     * Removes backup archive on source server.
     *
     * @return bool
     */
    protected function removeBackupSource()
    {
        if (empty($this->backupFilename)) {
            throw new RuntimeException('Backup filename not set. Can not remove backup.');
        }
        $rawCommand = "rm -f %s";
        $command = sprintf($rawCommand, $this->sourceBackupPath . '/' . $this->backupFilename);
        $delResult = $this->sourceServer->executeCommand($command);
        return ($delResult !== false);
    }
}

<?php
namespace ShinyDeploy\Domain;

use RuntimeException;
use ShinyDeploy\Core\Domain;
use ShinyDeploy\Core\Responder;
use ShinyDeploy\Domain\Database\Repositories;
use ShinyDeploy\Domain\Database\Servers;

class Deployment extends Domain
{
    /** @var \ShinyDeploy\Domain\Server\SftpServer|\ShinyDeploy\Domain\Server\SshServer $server */
    protected $server;

    /** @var Repository $repository */
    protected $repository;

    /** @var \ShinyDeploy\Responder\WsLogResponder $logResponder */
    protected $logResponder;

    /** @var array $changedFiles */
    protected $changedFiles = [];

    /** @var string $encryptionKey */
    protected $encryptionKey;


    public function setEncryptionKey($encryptionKey)
    {
        if (empty($encryptionKey)) {
            throw new \InvalidArgumentException('Encryption key can not be empty.');
        }
        $this->encryptionKey = $encryptionKey;
    }

    public function init(array $data)
    {
        $this->data = $data;
        $servers = new Servers($this->config, $this->logger);
        $servers->setEnryptionKey($this->encryptionKey);
        $repositories = new Repositories($this->config, $this->logger);
        $repositories->setEnryptionKey($this->encryptionKey);
        $this->server = $servers->getServer($data['server_id']);
        $this->repository = $repositories->getRepository($data['repository_id']);
    }

    /**
     * Setter for the websocket log repsonder.
     *
     * @param Responder $logResponder
     */
    public function setLogResponder(Responder $logResponder)
    {
        $this->logResponder = $logResponder;
    }

    /**
     * Returns list of changed files.
     *
     * @return array
     */
    public function getChangedFiles()
    {
        return $this->changedFiles;
    }

    /**
     * Executes an actual deployment.
     *
     * @param bool $listMode If true changed files are only listed but not acutually deployed.
     * @throws RuntimeException
     * @return bool
     */
    public function deploy($listMode = false)
    {
        if (empty($this->data)) {
            throw new RuntimeException('Deployment data not found. Initialization missing?');
        }
        if (empty($this->server)) {
            throw new RuntimeException('Server object not found.');
        }
        if (empty($this->repository)) {
            throw new RuntimeException('Repository object not found');
        }

        $this->logResponder->log('Checking prerequisites...');
        if ($this->checkPrerequisites() === false) {
            $this->logResponder->error('Prerequisites check failed. Aborting job.');
            return false;
        }

        $this->logResponder->log('Switching branch...');
        if ($this->switchBranch() === false) {
            $this->logResponder->error('Could not swtich to selected branch. Aborting job.');
            return false;
        }

        $this->logResponder->log('Preparing local repository...');
        if ($this->prepareRepository() === false) {
            $this->logResponder->error('Preparation of local repository failed. Aborting job.');
            return false;
        }

        if ($listMode === false) {
            $this->logResponder->log('Running tasks...');
            if ($this->runTasks('before') === false) {
                $this->logResponder->error('Running tasks failed. Aborting job.');
                return false;
            }
        }

        $this->logResponder->log('Estimating remote revision...');
        $remoteRevision = $this->getRemoteRevision();
        if ($remoteRevision === false) {
            $this->logResponder->error('Could not estimate remote revision. Aborting job.');
            return false;
        }

        $this->logResponder->log('Estimating local revision...');
        $localRevision = $this->getLocalRevision();
        if ($localRevision === false) {
            $this->logResponder->error('Could not estimate local revision. Aborting job.');
            return false;
        }

        // If remote server is up to date we can stop right here:
        if ($localRevision === $remoteRevision) {
            if ($listMode === false) {
                $this->logResponder->info('Remote server is aleady up to date.');
            }
            return true;
        }

        $this->logResponder->log('Collecting changed files...');
        $changedFiles = $this->getChangedFilesList($localRevision, $remoteRevision);
        if (empty($changedFiles)) {
            $this->logResponder->error('Could not estimate changed files.');
            return false;
        }

        // If we are in list mode we can now respond with the list of changed files:
        if ($listMode === true) {
            $this->changedFiles = $changedFiles;
            return true;
        }

        $this->logResponder->log('Sorting changed files...');
        $sortedChangedFiles = $this->sortFilesByOperation($changedFiles);

        $this->logResponder->log('Processing changed files...');
        if ($this->processChangedFiles($sortedChangedFiles) === false) {
            $this->logResponder->error('Could not process files. Aborting job.');
            return false;
        }

        $this->logResponder->log('Updating revision file...');
        if ($this->updateRemoteRevisionFile($localRevision) === false) {
            $this->logResponder->error('Could not update remove revision file. Aborting job.');
            return false;
        }

        $this->logResponder->log('Running tasks...');
        if ($listMode === false && $this->runTasks('after') === false) {
            $this->logResponder->error('Running tasks failed. Aborting job.');
            return false;
        }

        return true;
    }

    /**
     * Checks various requirements to be fulfilled before stating a deployment.
     *
     * @return boolean
     */
    protected function checkPrerequisites()
    {
        $this->logResponder->log('Checking git binary...');
        if ($this->repository->checkGit() === false) {
            $this->logResponder->danger('Git executable not found.');
            return false;
        }
        $this->logResponder->log('Checking connection to repository...');
        if ($this->repository->checkConnectivity() === false) {
            $this->logResponder->danger('Connection to repository failed.');
            return false;
        }
        $this->logResponder->log('Checking connection target server...');
        if ($this->server->checkConnectivity() === false) {
            $this->logResponder->danger('Connection to remote server failed.');
            return false;
        }
        return true;
    }

    /**
     * If local repository does not exist it will be pulled from git. It it exists it will be updated.
     *
     * @return bool
     */
    protected function prepareRepository()
    {
        if ($this->repository->exists() === true) {
            $result = $this->repository->doPull();
            if ($result === false) {
                $this->logResponder->danger('Error while updating repository.');
            }
            $pruneResult = $this->repository->doPrune();
            if ($pruneResult === false) {
                $this->logResponder->info('Possible error during git remote prune.');
            }
        } else {
            $result = $this->repository->doClone();
            if ($result === false) {
                $this->logResponder->danger('Error while cloning repository.');
            }
        }
        return $result;
    }

    /**
     * Runs user defined tasks on target server.
     *
     * @param string $type
     * @return boolean
     */
    protected function runTasks($type)
    {
        // Skip if no tasks defined
        if (empty($this->data['tasks'])) {
            return true;
        }

        // Skip if no tasks of given type defined:
        $typeTasks = [];
        foreach ($this->data['tasks'] as $task) {
            if ($task['type'] === $type) {
                array_push($typeTasks, $task);
            }
        }
        if (empty($typeTasks)) {
            return true;
        }

        // Skip if server is not ssh capable:
        if ($this->server->getType() !== 'ssh') {
            $this->logResponder->danger('Server not of type SSH. Skipping tasks.');
            return false;
        }

        // Execute tasks on server:
        $remotePath = $this->getRemotePath();
        foreach ($typeTasks as $task) {
            $command = 'cd ' . $remotePath . ' && ' . $task['command'];
            $this->logResponder->info('Executing task: ' . $task['name']);
            $response = $this->server->executeCommand($command);
            if ($response === false) {
                $this->logResponder->danger('Task failed.');
            } else {
                $this->logResponder->log($response);
            }
        }
        return true;
    }

    /**
     * Get the deployment path on target server.
     *
     * @return string
     */
    protected function getRemotePath()
    {
        $serverRoot = $this->server->getRootPath();
        $serverRoot = rtrim($serverRoot, '/');
        $targetPath = trim($this->data['target_path']);
        $targetPath = trim($targetPath, '/');
        $remotePath = $serverRoot . '/' . $targetPath . '/';
        return $remotePath;
    }

    /**
     * Fetches remote revision from REVISION file in project root.
     *
     * @return string|bool
     */
    public function getRemoteRevision()
    {
        $targetPath = $this->getRemotePath();
        $targetPath .= 'REVISION';
        $revision = $this->server->getFileContent($targetPath);
        $revision = trim($revision);
        if (!empty($revision) && preg_match('#[0-9a-f]{40}#', $revision) === 1) {
            $this->logResponder->info('Remote server is at revision: ' . $revision);
            return $revision;
        }
        $targetDir = dirname($targetPath);
        $targetDirContent = $this->server->listDir($targetDir);
        if ($targetDirContent === false) {
            $this->logResponder->danger('Target path on remote server not found or not accessible.');
            return false;
        }
        if (is_array($targetDirContent) && empty($targetDirContent)) {
             $this->logResponder->info('Target path is empty. No revision yet.');
            return '-1';
        }
        return false;
    }

    /**
     * Fetches revision of local repository.
     *
     * @return bool|string
     */
    public function getLocalRevision()
    {
        if ($this->repository->checkConnectivity() === false) {
            $this->logResponder->danger('Could not connect to remote repository.');
            return false;
        }
        $revision = $this->repository->getRemoteRevision($this->data['branch']);
        if ($revision !== false) {
            $this->logResponder->info('Local repository is at revision: ' . $revision);
        } else {
            $this->logResponder->danger('Local revision not found.');
        }
        return $revision;
    }

    /**
     * Switch repository to deployment branch.
     *
     * @return bool
     */
    protected function switchBranch()
    {
        return $this->repository->switchBranch($this->data['branch']);
    }

    /**
     * Generates list with changed,added,deleted files.
     *
     * @param string $localRevision
     * @param string $remoteRevision
     * @return bool|array
     */
    protected function getChangedFilesList($localRevision, $remoteRevision)
    {
        if ($remoteRevision === '-1') {
            $changedFiles = $this->repository->listFiles();
        } else {
            $changedFiles = $this->repository->getDiff($localRevision, $remoteRevision);
        }
        if (empty($changedFiles)) {
            return false;
        }

        $files = [];
        if ($remoteRevision === '-1') {
            foreach ($changedFiles as $file) {
                $item = [
                    'type' => 'A',
                    'file' => $file,
                    'diff' => '',
                ];
                array_push($files, $item);
            }
        } else {
            $files = $changedFiles;
        }

        return $files;
    }

    /**
     * Sort files by opration to do (e.g. upload, delete, ...)
     * @param array $files
     * @return array
     */
    protected function sortFilesByOperation($files)
    {
        $sortedFiles = [
            'upload' => [],
            'delete' => [],
        ];
        foreach ($files as $item) {
            if (in_array($item['type'], ['A', 'C', 'M', 'R'])) {
                $sortedFiles['upload'][] = $item['file'];
            } elseif ($item['type'] === 'D') {
                $sortedFiles['delete'][] = $item['file'];
            }
        }
        return $sortedFiles;
    }

    /**
     * Deploys changes to target server by uploading/deleting files.
     *
     * @param array $changedFiles
     * @return bool
     */
    protected function processChangedFiles($changedFiles)
    {
        $repoPath = $this->repository->getLocalPath();
        $repoPath = rtrim($repoPath, '/') . '/';
        $remotePath = $this->getRemotePath();
        $uploadCount = count($changedFiles['upload']);
        $deleteCount = count($changedFiles['delete']);
        if ($uploadCount === 0 && $deleteCount === 0) {
            $this->logResponder->info('Noting to upload or delete.');
            return true;
        }
        $this->logResponder->info(
            'Files to upload: '.$uploadCount.' - Files to delete: ' . $deleteCount . ' - processing...'
        );

        if ($uploadCount > 0) {
            foreach ($changedFiles['upload'] as $file) {
                $uploadStart = microtime(true);
                $result = $this->server->upload($repoPath.$file, $remotePath.$file);
                $uploadEnd = microtime(true);
                $uploadDuration = round($uploadEnd - $uploadStart, 2);
                if ($result === true) {
                    $this->logResponder->info('Uploading ' . $file . ': success ('.$uploadDuration.'s)');
                } else {
                    $this->logResponder->danger('Uploading ' . $file . ': failed');
                }
            }
        }
        if ($deleteCount > 0) {
            $this->logResponder->log('Removing files...');
            foreach ($changedFiles['delete'] as $file) {
                $result = $this->server->delete($remotePath.$file);
                if ($result === true) {
                    $this->logResponder->info('Deleting ' . $file . ': success');
                } else {
                    $this->logResponder->danger('Deleting ' . $file . ': failed');
                }
            }
        }

        $this->logResponder->info('Processing files completed.');

        return true;
    }

    /**
     * Updates revision file on remote server.
     *
     * @param string $revision Revision hash
     * @return boolean
     */
    protected function updateRemoteRevisionFile($revision)
    {
        $remotePath = $this->getRemotePath();
        if ($this->server->putContent($revision, $remotePath.'REVISION') === false) {
            $this->logResponder->error('Could not update remote revision file.');
            return false;
        }
        return true;
    }

    /**
     * Checks if deployments branch matches the passed one.
     *
     * @param string $checkBranch
     * @return bool
     */
    public function isBranch($checkBranch)
    {
        if (empty($this->data)) {
            throw new RuntimeException('Deployment data not found. Initialization missing?');
        }
        $brachParts = explode('/', $this->data['branch']);
        $branch = array_pop($brachParts);
        return ($branch === $checkBranch);
    }
}

<?php namespace ShinyDeploy\Worker;

require __DIR__ . '/../../../vendor/autoload.php';

use ShinyDeploy\Action\WsWorkerAction\RunBackup;
use ShinyDeploy\Core\Worker;
use ShinyDeploy\Exceptions\MissingDataException;

class BackupActions extends Worker
{
    /**
     * Calls all "init methods" and waits for jobs from gearman server.
     */
    protected function registerCallbacks()
    {
        $this->GearmanWorker->addFunction('runBackup', [$this, 'runBackup']);
    }

    /**
     * Clones a repository
     *
     * @param \GearmanJob $Job
     * @throws \Exception
     * @return bool
     */
    public function runBackup(\GearmanJob $Job)
    {
        try {
            $this->countJob();
            $params = json_decode($Job->workload(), true);
            if (empty($params['clientId'])) {
                throw new MissingDataException('ClientId can not be empty.');
            }
            if (empty($params['token'])) {
                throw new MissingDataException('Token can not be empty.');
            }
            if (empty($params['backupId'])) {
                throw new MissingDataException('BackupId can not be empty.');
            }
            $runBackupAction = new RunBackup($this->config, $this->logger);
            $runBackupAction->setClientId($params['clientId']);
            $runBackupAction->setToken($params['token']);
            $runBackupAction->__invoke($params['backupId']);
        } catch (\Exception $e) {
            $this->logger->alert(
                'Worker Exception: ' . $e->getMessage() . ' (' . $e->getFile() . ': ' . $e->getLine() . ')'
            );
        }
        return true;
    }
}

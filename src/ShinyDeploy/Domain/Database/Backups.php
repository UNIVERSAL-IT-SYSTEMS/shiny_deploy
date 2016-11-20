<?php
namespace ShinyDeploy\Domain\Database;

use ShinyDeploy\Domain\Backup;
use ShinyDeploy\Traits\CryptableDomain;

class Backups extends DatabaseDomain
{
    use CryptableDomain;

    /** @var array $rules Validation rules */
    protected $rules = [
        'required' => [
            ['name'],
            ['source_server_id'],
            ['source_server_path'],
            ['target_server_id'],
            ['target_server_path'],
        ],
        'integer' => [
            ['source_server_id'],
            ['target_server_id'],
        ],
        'lengthBetween' => [
            ['name', 1, 100],
            ['source_server_path', 1, 100],
            ['target_server_path', 1, 200],
        ]
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
     * Fetches list of backups from database.
     *
     * @return array|bool
     */
    public function getBackups()
    {
        $rows = $this->db->prepare("SELECT * FROM backups ORDER BY `name`")->getResult(false);
        return $rows;
    }

    /**
     * Stores new backup in database.
     *
     * @param array $backupData
     * @return bool
     */
    public function addBackup(array $backupData)
    {
        $this->prepareDataForSave($backupData);
        return $this->db->prepare(
            "INSERT INTO backups
              (`name`, `source_server_id`, `source_server_path`, `target_server_id`, `target_server_path`)
              VALUES
                (%s, %d, %s, %d, %s)",
            $backupData['name'],
            $backupData['source_server_id'],
            $backupData['source_server_path'],
            $backupData['target_server_id'],
            $backupData['target_server_path']
        )->execute();
    }

    /**
     * Updates backup.
     *
     * @param array $backupData
     * @return bool
     */
    public function updateBackup(array $backupData)
    {
        if (!isset($backupData['id'])) {
            return false;
        }
        $this->prepareDataForSave($backupData);
        return $this->db->prepare(
            "UPDATE backups
            SET `name` = %s,
              `source_server_id` = %d,
              `source_server_path` = %s,
              `target_server_id` = %d,
              `target_server_path` = %s
            WHERE id = %d",
            $backupData['name'],
            $backupData['source_server_id'],
            $backupData['source_server_path'],
            $backupData['target_server_id'],
            $backupData['target_server_path'],
            $backupData['id']
        )->execute();
    }

    /**
     * Fetches backup data.
     *
     * @param int $backupId
     * @return array
     */
    public function getBackupData($backupId)
    {
        $backupId = (int)$backupId;
        if ($backupId === 0) {
            return [];
        }
        $backupData = $this->db->prepare("SELECT * FROM backups WHERE `id` = %d", $backupId)
            ->getResult(true);
        if (empty($backupData)) {
            return [];
        }
        return $backupData;
    }

    /**
     * Creates and returns a backup object.
     *
     * @param int $backupId
     * @return Backup
     */
    public function getBackup($backupId)
    {
        $data = $this->getBackupData($backupId);
        if (empty($data)) {
            throw new \RuntimeException('Backup not found in database.');
        }
        $backup = new Backup($this->config, $this->logger);
        $backup->setEnryptionKey($this->encryptionKey);
        $backup->init($data);
        return $backup;
    }

    /**
     * Deletes a backup.
     *
     * @param int $backupId
     * @return bool
     */
    public function deleteBackup($backupId)
    {
        $backupId = (int)$backupId;
        if ($backupId === 0) {
            return false;
        }

        // delete backup:
        return $this->db->prepare("DELETE FROM backups WHERE `id` = %d LIMIT 1", $backupId)->execute();
    }

    /**
     * Prepares backup-data for save into database.
     *
     * @param array $backupData
     */
    protected function prepareDataForSave(array &$backupData)
    {
        $backupData['source_server_path'] = trim($backupData['source_server_path']);
        $backupData['source_server_path'] = rtrim($backupData['source_server_path'], '/');
        if (empty($backupData['source_server_path'])) {
            $backupData['source_server_path'] = '/';
        }

        $backupData['target_server_path'] = trim($backupData['target_server_path']);
        $backupData['target_server_path'] = rtrim($backupData['target_server_path'], '/');
        if (empty($backupData['target_server_path'])) {
            $backupData['target_server_path'] = '/';
        }
    }
}

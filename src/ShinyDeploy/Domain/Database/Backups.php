<?php
namespace ShinyDeploy\Domain\Database;

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
}

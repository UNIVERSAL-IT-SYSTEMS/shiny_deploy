<?php namespace ShinyDeploy\Core;

use Apix\Log\Logger;
use Apix\Log\Logger\File;
use Nekudo\Angela\Worker as AngelaBaseWorker;
use Noodlehaus\Config;

abstract class Worker extends AngelaBaseWorker
{
    /** @var Config Project config. */
    protected $config;

    /** @var  Logger $logger */
    protected $logger;

    /** @var  \ZMQContext $zmqContext */
    protected $zmqContext;

    public function __construct($workerName, $gearmanHost, $gearmanPort, $runPath)
    {
        // load config:
        $this->config = Config::load(__DIR__ . '/../config.php');

        // init logger:
        $this->logger = new Logger;
        $fileLogger = new File($this->config->get('logging.file'));
        $fileLogger->setMinLevel($this->config->get('logging.level'));
        $this->logger->add($fileLogger);

        $this->zmqContext = new \ZMQContext;
        $this->logger->info('Starting worker. (Name: ' . $workerName . ')');
        parent::__construct($workerName, $gearmanHost, $gearmanPort, $runPath);
    }
}

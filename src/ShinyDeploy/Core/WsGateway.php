<?php
namespace ShinyDeploy\Core;

use Apix\Log\Logger;
use Noodlehaus\Config;
use Ratchet\ConnectionInterface;
use Ratchet\Wamp\WampServerInterface;
use ShinyDeploy\Exceptions\InvalidTokenException;
use ShinyDeploy\Exceptions\MissingDataException;
use ShinyDeploy\Exceptions\WebsocketException;
use ShinyDeploy\Responder\WsDataResponder;

class WsGateway implements WampServerInterface
{
    /** @var Config $config */
    protected $config;

    /** @var  Logger $logger */
    protected $logger;

    /** @var array $subscriptions */
    protected $subscriptions = [];

    public function __construct(Config $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Adds new client to list of subscribers.
     *
     * @param ConnectionInterface $conn
     * @param \Ratchet\Wamp\Topic $Topic
     */
    public function onSubscribe(ConnectionInterface $conn, $Topic)
    {
        $clientId = $Topic->getId();
        if (!array_key_exists($clientId, $this->subscriptions)) {
            $this->subscriptions[$clientId] = $Topic;
        }
    }

    /**
     * This method is called whenever a message is pushed into the server using ZMQ.
     *
     * @param string $dataEncoded Json Encode data passed by ZMQ.
     * @return bool
     */
    public function onApiEvent($dataEncoded)
    {
        try {
            $this->logger->debug('onApiEvent: ' . $dataEncoded);
            $data = json_decode($dataEncoded, true);
            if (empty($data['clientId'])) {
                throw new MissingDataException('ClientId can not be empty.');
            }
            if (empty($data['eventName'])) {
                throw new MissingDataException('EventName can not be empty.');
            }
            if (!isset($this->subscriptions[$data['clientId']])) {
                throw new WebsocketException('Invalid client-id.');
            }
            /** @var \Ratchet\Wamp\Topic $Topic */
            $Topic = $this->subscriptions[$data['clientId']];
            $Topic->broadcast($data);
            return true;
        } catch (\Exception $e) {
            $this->logger->alert(
                'Gateway Error: ' . $e->getMessage() . ' (' . $e->getFile() . ': ' . $e->getLine() . ')'
            );
        }
        return false;
    }

    /**
     * This methods handles events triggered by the client/browser.
     *
     * @param ConnectionInterface $conn
     * @param string $id
     * @param \Ratchet\Wamp\Topic|string $topic
     * @param array $params
     */
    public function onCall(ConnectionInterface $conn, $id, $topic, array $params)
    {
        try {
            $this->logger->debug('onCall: ' . json_encode($params));
            $actionName = $topic->getId();
            $clientId = $params['clientId'];
            $token = (!empty($params['token'])) ? $params['token'] : '';
            $callbackId = (isset($params['callbackId'])) ? $params['callbackId'] : null;
            $actionPayload = (isset($params['actionPayload'])) ? $params['actionPayload'] : [];
            if (!empty($callbackId)) {
                $this->handleDataRequest($clientId, $token, $callbackId, $actionName, $actionPayload);
            } else {
                $this->handleTriggerRequest($clientId, $token, $actionName, $actionPayload);
            }
        } catch (InvalidTokenException $e) {
            $this->onUnauthorized($params['clientId']);
        } catch (\Exception $e) {
            $this->logger->alert(
                'Gateway Error: ' . $e->getMessage() . ' (' . $e->getFile() . ': ' . $e->getLine() . ')'
            );
        }
    }

    /**
     * Handles requests which directly respond with the requested data.
     *
     * @param string $clientId
     * @param string $token
     * @param string $callbackId
     * @param string $actionName
     * @param array $actionPayload
     * @throws WebsocketException
     */
    protected function handleDataRequest($clientId, $token, $callbackId, $actionName, array $actionPayload)
    {
        $actionClassName = 'ShinyDeploy\Action\WsDataAction\\' . ucfirst($actionName);
        if (!class_exists($actionClassName)) {
            throw new WebsocketException('Invalid data action passed to worker gateway. ('.$actionName.')');
        }
        /** @var \ShinyDeploy\Action\WsDataAction $action */
        $responder = new WsDataResponder($this->config, $this->logger);
        $action = new $actionClassName($this->config, $this->logger);
        $action->setResponder($responder);
        $action->setClientId($clientId);
        $action->setToken($token);
        $action->__invoke($actionPayload);
        $actionResponse = $action->getResponse($callbackId);

        /** @var \Ratchet\Wamp\Topic|string $topic **/
        $topic = $this->subscriptions[$clientId];
        $topic->broadcast($actionResponse);
    }

    /**
     * Handles requests which just trigger an action. Response data (if any) will be send
     * later form within the action itself.
     *
     * @param string $clientId
     * @param string $token
     * @param string $actionName
     * @param array $actionPayload
     * @throws WebsocketException
     */
    protected function handleTriggerRequest($clientId, $token, $actionName, $actionPayload)
    {
        $this->wsLog($clientId, 'Triggering job: ' . $actionName);
        $client = new \GearmanClient;
        $client->addServer($this->config->get('gearman.host'), $this->config->get('gearman.port'));
        $actionPayload['clientId'] = $clientId;
        $actionPayload['token'] = $token;
        $payloadEncoded = json_encode($actionPayload);
        $client->doBackground($actionName, $payloadEncoded);
        unset($client);
    }

    /**
     * Closes connection cause "onPublish" event is not used within this application.
     *
     * @param ConnectionInterface $conn
     * @param \Ratchet\Wamp\Topic|string $topic
     * @param string $event
     * @param array $exclude
     * @param array $eligible
     */
    public function onPublish(ConnectionInterface $conn, $topic, $event, array $exclude, array $eligible)
    {
        $conn->close();
    }

    public function onUnSubscribe(ConnectionInterface $conn, $Topic)
    {
    }

    public function onOpen(ConnectionInterface $conn)
    {
    }

    public function onClose(ConnectionInterface $conn)
    {
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
    }

    /**
     * Sends a log-event response to client.
     *
     * @param string $clientId
     * @param string $msg
     * @param string $type
     * @throws WebsocketException
     */
    protected function wsLog($clientId, $msg, $type = 'default')
    {
        if (empty($clientId)) {
            throw new WebsocketException('Invalid client id.');
        }
        $eventData = [
            'clientId' => $clientId,
            'eventName' => 'log',
            'eventPayload' => [
                'text' => $msg,
                'type' => $type,
            ],
        ];
        /** @var \Ratchet\Wamp\Topic $topic */
        $topic = $this->subscriptions[$clientId];
        $topic->broadcast($eventData);
    }

    /**
     * Sends "unauthotized" event.
     *
     * @param string $clientId
     */
    protected function onUnauthorized($clientId)
    {
        $eventData = [
            'clientId' => $clientId,
            'eventName' => 'unauthorized',
        ];
        /** @var \Ratchet\Wamp\Topic $topic */
        $topic = $this->subscriptions[$clientId];
        $topic->broadcast($eventData);
    }
}

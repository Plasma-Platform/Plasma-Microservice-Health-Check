<?php
/**
 * @package templatemonster\healthchecks
 */

namespace templatemonster\healthchecks;

use Yii;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\Response;
use templatemonster\healthchecks\models\HealthCheck;

class Module extends \yii\base\Module
{
    public $checks = [];

    protected $healthCheks = [];
    protected $health;

    public function init()
    {
        parent::init();
        foreach ($this->checks as $key => $val) {
            $args = null;
            if (!is_callable($val)) {
                if (is_numeric($key)) {
                    $key = $val;
                } else {
                    $args = $val;
                }
                $val = [$this, 'check' . ucfirst($key)];
            }
            $this->addHealthCheck($key, $val, $args);
        }
    }

    public function addHealthCheck($name, $callback, $args = null)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('Health check must be callable');
        }
        $this->healthCheks[$name] = ['callback' => $callback, 'args' => $args];
    }

    public function getHealth()
    {
        return $this->health;
    }

    public function doHealthChecks()
    {
        $this->health = true;
        $result = [];
        foreach ($this->healthCheks as $name => $callbackData) {
            if (!empty($callbackData['args'])) {
                $checkResult = call_user_func($callbackData['callback'], $callbackData['args']);
            } else {
                $checkResult = call_user_func($callbackData['callback']);
            }
            if (is_array($checkResult)) {
                foreach ($checkResult as $checkName => $check) {
                    if (!$check) {
                        $this->health = false;
                    }
                    $model = new HealthCheck;
                    $model->name = $checkName;
                    $model->passed = $check;
                    $result[] = $model;
                }
            } else {
                if (!$checkResult) {
                    $this->health = false;
                }
                $model = new HealthCheck;
                $model->name = $name;
                $model->passed = $checkResult;
                $result[] = $model;
            }
        }
        $package = \Composer\InstalledVersions::getRootPackage();
        $result['version'] = $package['pretty_version'] ?? '%VERSION%';
        return $result;
    }

    public function checkDb($dbName = null)
    {
        try {
            if ($dbName) {
                $connection = \Yii::$app->get($dbName);
            } else {
                $connection = Yii::$app->db;
            }
            $connection->open();
            if ($connection->pdo !== null) {
                return true;
            }
        } catch (\Exception $e) {
            Yii::error($e);
        }
        return false;
    }

    public function checkCache($cacheName = null)
    {
        try {
            if ($cacheName) {
                $cache = \Yii::$app->get($cacheName);
            } else {
                $cache = Yii::$app->cache;
            }
            return $cache->set('healthcheck', 1);
        } catch (\Exception $e) {
            Yii::error($e);
        }
        return false;
    }

    public function checkMongodb($mongodbName = null)
    {
        try {
            /** @var \yii\mongodb\Connection $connection */
            if ($mongodbName) {
                $connection = \Yii::$app->get($mongodbName);
            } else {
                $connection = \Yii::$app->mongodb;
            }
            $connection->open();
            return $connection->getIsActive();
        } catch (\Exception $e) {
            Yii::error($e);
            return false;
        }
    }

    public function checkRabbitmq($queueName = null)
    {
        try {
            if ($queueName) {
                $queue = \Yii::$app->get($queueName);
            } else {
                $queue = \Yii::$app->queue;
            }
            return $queue
                ->getContext()
                ->getLibChannel()
                ->is_open();
        } catch (\Exception $e) {
            Yii::error($e);
            return false;
        }
    }

    public function checkElasticsearch($elasticsearchName = null)
    {
        try {
            /** @var \yii\elasticsearch\Connection $connection */
            if ($elasticsearchName){
                $connection = \Yii::$app->get($elasticsearchName);
            } else {
                $connection = \Yii::$app->elasticsearch;
            }
            $connection->open();
            if ($connection->getIsActive()) {
                return true;
            }
        } catch (\Exception $e) {
            Yii::error($e);
        }
        return false;
    }

    public function checkUrls(array $urls)
    {
        $client = new Client([]);
        $promises = [];
        $checks = [];
        foreach ($urls as $name => $url) {
            $checks[$name] = false;
            try {
                $promises[$name] = $client->getAsync($url);
            } catch (\Throwable $e) {
                Yii::error($e);
            }
        }
        $responses = Utils::settle($promises)->wait();
        foreach ($responses as $name => $item) {
            /** @var Response $response */
            $response = $item['value'] ?? null;
            $checks[$name] = $response !== null && $response->getStatusCode() === 200;
        }
        return $checks;
    }
}

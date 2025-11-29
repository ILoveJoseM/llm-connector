<?php

namespace JoseChan\LlmConnector\Llm;

use JoseChan\LlmConnector\Contracts\LlmConnectorInterface;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;

/**
 * LLM 管理器
 *
 * 架构: Application → Model → Connection
 * - Application: 业务应用层，配置 api_key 和使用的 model
 * - Model: 模型层，定义模型参数和关联的 connection
 * - Connection: 连接层，定义 API 端点的基础设施配置
 */
class LlmManager
{
    /**
     * The application instance.
     *
     * @var Application
     */
    protected $app;

    /**
     * 已解析的 application 实例缓存
     *
     * @var array
     */
    protected $applications = [];

    /**
     * 已解析的 connector 实例缓存
     *
     * @var array
     */
    protected $connectors = [];

    protected $customCreators = [];

    /**
     * 构造函数
     *
     * @param Application $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * 获取默认 application 名称
     *
     * @return string
     */
    public function getDefaultApplication(): string
    {
        return $this->app['config']['llm.default'] ?? 'default';
    }

    /**
     * 获取指定 application 的 connector 实例
     *
     * @param string|null $application Application 名称，null 使用默认
     * @return LlmConnectorInterface
     * @throws \Exception
     */
    public function application(?string $application = null): LlmConnectorInterface
    {
        $application = $application ?: $this->getDefaultApplication();

        // 如果已经解析过，直接返回缓存
        if (isset($this->applications[$application])) {
            return $this->applications[$application];
        }

        // 解析并缓存
        $this->applications[$application] = $this->resolveApplication($application);

        return $this->applications[$application];
    }

    /**
     * 检查 application 是否已连接
     *
     * @param string|null $application
     * @return bool
     */
    public function isConnected(?string $application = null): bool
    {
        $application = $application ?: $this->getDefaultApplication();
        return isset($this->applications[$application]);
    }

    /**
     * 解析 application 配置并创建 connector 实例
     *
     * @param string $application
     * @return LlmConnectorInterface
     * @throws \Exception
     */
    protected function resolveApplication(string $application): LlmConnectorInterface
    {
        // 获取 application 配置
        $appConfig = $this->getApplicationConfig($application);

        if (empty($appConfig)) {
            throw new \Exception("Application [{$application}] not configured.");
        }

        // 获取 model 配置
        $modelName = $appConfig['model'] ?? null;
        if (empty($modelName)) {
            throw new \Exception("Model not specified for application [{$application}].");
        }

        $modelConfig = $this->getModelConfig($modelName);
        if (empty($modelConfig)) {
            throw new \Exception("Model [{$modelName}] not configured.");
        }

        // 获取 connection 配置
        $connectionName = $modelConfig['connection'] ?? null;
        if (empty($connectionName)) {
            throw new \Exception("Connection not specified for model [{$modelName}].");
        }

        $connectionConfig = $this->getConnectionConfig($connectionName);
        if (empty($connectionConfig)) {
            throw new \Exception("Connection [{$connectionName}] not configured.");
        }

        // 合并配置
        $config = $this->mergeConfigs($appConfig, $modelConfig, $connectionConfig);

        // 获取 connector 并连接
        $connector = $this->getConnector($connectionConfig['driver'] ?? 'openai');

        Log::info('LLM application resolved', [
            'application' => $application,
            'model' => $modelName,
            'connection' => $connectionName,
        ]);

        return $connector->connect($config);
    }

    /**
     * 获取 application 配置
     *
     * @param string $application
     * @return array
     */
    protected function getApplicationConfig(string $application): array
    {
        return $this->app['config']["llm.applications.{$application}"] ?? [];
    }

    /**
     * 获取 model 配置
     *
     * @param string $model
     * @return array
     */
    protected function getModelConfig(string $model): array
    {
        return $this->app['config']["llm.models.{$model}"] ?? [];
    }

    /**
     * 获取 connection 配置
     *
     * @param string $connection
     * @return array
     */
    protected function getConnectionConfig(string $connection): array
    {
        return $this->app['config']["llm.connections.{$connection}"] ?? [];
    }

    /**
     * 合并三层配置
     *
     * 优先级: application > model > connection
     *
     * @param array $appConfig
     * @param array $modelConfig
     * @param array $connectionConfig
     * @return array
     */
    protected function mergeConfigs(array $appConfig, array $modelConfig, array $connectionConfig): array
    {
        return array_merge(
            $connectionConfig,
            $modelConfig,
            [
                'api_key' => $appConfig['api_key'] ?? '',
                'options' => array_merge(
                    $modelConfig['options'] ?? [],
                    $appConfig['options'] ?? []
                ),
            ]
        );
    }

    /**
     * 获取 connector 实例
     *
     * @param string $driver
     * @return LlmConnectorInterface
     * @throws \Exception
     */
    protected function getConnector(string $driver): LlmConnectorInterface
    {
        // 如果已经创建过该 driver 的 connector，直接返回
        if (isset($this->connectors[$driver])) {
            return clone $this->connectors[$driver];
        }

        // 根据 driver 创建对应的 connector
        $connector = $this->createConnector($driver);

        // 缓存 connector 实例
        $this->connectors[$driver] = $connector;

        return clone $connector;
    }

    /**
     * 创建 connector 实例
     *
     * @param string $driver
     * @return LlmConnectorInterface
     */
    protected function createConnector(string $driver): LlmConnectorInterface
    {
        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($driver);
        } else {
            $driverMethod = 'create'.ucfirst($driver).'Driver';

            if (method_exists($this, $driverMethod)) {
                return $this->{$driverMethod}($this->app);
            } else {
                throw new \Exception("Driver [{$driver}] is not supported.");
            }
        }
    }

    public function extend(string $driver, Closure $callback): void
    {
        $this->customCreators[$driver] = $callback;
    }

    /**
     * @param $driver
     * @return LlmConnectorInterface
     */
    protected function callCustomCreator($driver)
    {
        return $this->customCreators[$driver]($this->app);
    }

    protected function createOpenDriver($app): LlmConnectorInterface
    {
        return new DefaultConnector($app);
    }

    /**
     * 获取所有已配置的 applications
     *
     * @return array
     */
    public function getAvailableApplications(): array
    {
        return array_keys($this->app['config']['llm.applications'] ?? []);
    }

    /**
     * 获取所有已配置的 models
     *
     * @return array
     */
    public function getAvailableModels(): array
    {
        return array_keys($this->app['config']['llm.models'] ?? []);
    }

    /**
     * 获取所有已配置的 connections
     *
     * @return array
     */
    public function getAvailableConnections(): array
    {
        return array_keys($this->app['config']['llm.connections'] ?? []);
    }

    /**
     * 清除所有缓存
     *
     * @return void
     */
    public function purge(): void
    {
        $this->applications = [];
        $this->connectors = [];
    }

    /**
     * @return Application
     */
    public function getApp(): Application
    {
        return $this->app;
    }
}

<?php

namespace JoseChan\LlmConnector\Facade;

use JoseChan\LlmConnector\Contracts\LlmConnectorInterface;
use JoseChan\LlmConnector\Llm\LlmManager;
use Closure;
use Illuminate\Support\Facades\Facade;

/**
 * LLM Facade
 *
 * @method static LlmConnectorInterface application(?string $application = null) 获取指定 application 的 connector 实例
 * @method static string getDefaultApplication() 获取默认 application 名称
 * @method static bool isConnected(?string $application = null) 检查 application 是否已连接
 * @method static array getAvailableApplications() 获取所有已配置的 applications
 * @method static array getAvailableModels() 获取所有已配置的 models
 * @method static array getAvailableConnections() 获取所有已配置的 connections
 * @method static void purge() 清除所有缓存
 * @method static LlmManager getApp() 获取 Application 实例
 * @method static void extend(string $driver, Closure $callback) 扩展 connector 驱动
 *
 * @see LlmManager
 */
class LLM extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return LlmManager::class;
    }
}

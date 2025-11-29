<?php

namespace JoseChan\LlmConnector;

use JoseChan\LlmConnector\Llm\LlmManager;
use Illuminate\Support\ServiceProvider;

class LlmServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // 注册LLM 管理器
        $this->app->singleton(LlmManager::class, function ($app){
            return new LlmManager($app);
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // 发布配置文件
        $this->publishes([
            __DIR__ . '/../config/llm.php' => config_path('llm.php'),
        ], 'llm-config');
    }
}

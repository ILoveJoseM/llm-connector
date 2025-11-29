<?php

namespace JoseChan\LlmConnector\Contracts;

use Psr\Http\Message\ResponseInterface;

/**
 * LLM Connector 接口
 *
 * 定义 LLM 连接器的标准接口
 */
interface LlmConnectorInterface
{
    /**
     * 连接到 LLM 服务
     *
     * @param array $config 合并后的配置
     * @return $this
     */
    public function connect($config);

    /**
     * 调用 chat completions API
     *
     * @param string|array $messages 消息内容，支持字符串或消息数组
     * @param array $options 额外选项，会覆盖默认配置
     * @return ResponseInterface
     */
    public function completions($messages, array $options = []);

    /**
     * 流式调用 chat completions API
     *
     * @param string|array $messages 消息内容
     * @param array $options 额外选项
     * @param callable $callback 处理每个数据块的回调函数
     * @return void
     */
    public function streamCompletions($messages, array $options = [], callable $callback = null);

    /**
     * 获取当前配置
     *
     * @return array
     */
    public function getConfig(): array;

    /**
     * 获取模型名称
     *
     * @return string
     */
    public function getModelName(): string;

    /**
     * 检查是否支持流式输出
     *
     * @return bool
     */
    public function supportsStreaming(): bool;
}

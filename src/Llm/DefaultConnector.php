<?php

namespace JoseChan\LlmConnector\Llm;



use JoseChan\LlmConnector\Contracts\LlmConnectorInterface;
use GuzzleHttp\Client;
use Illuminate\Foundation\Application;

/**
 * 默认 LLM Connector (OpenAI API 兼容)
 *
 * 支持所有兼容 OpenAI API 的模型服务
 * 集成 MCP Server 的 Tools、Prompts、Resources 能力
 */
class DefaultConnector implements LlmConnectorInterface
{
    /**
     * 合并后的完整配置
     *
     * @var array
     */
    protected $config;

    /**
     * HTTP 客户端
     *
     * @var Client
     */
    protected $client;

    /**
     * Application 实例
     *
     * @var Application
     */
    protected $app;
    private $allowedOptions = [
        'temperature',
        'max_tokens',
        'top_p',
        'frequency_penalty',
        'presence_penalty',
        'stop',
        'stream',
        'tool_choice',
        'response_format',
        'seed',
        'logprobs',
        'top_logprobs',
    ];

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
     * 连接到 LLM 服务
     *
     * @param array $config 合并后的配置
     * @return $this
     */
    public function connect($config)
    {
        $this->config = $config;

        // 创建 Guzzle Client
        $this->client = new Client([
            'base_uri' => $config['base_uri'] ?? '',
            'timeout' => $config['timeout'] ?? 60,
            'connect_timeout' => $config['connect_timeout'] ?? 10,
            'verify' => $config['verify'] ?? true,
            'headers' => [
                'User-Agent' => 'Laravel-MCP-Client/1.0',
            ],
        ]);

        return $this;
    }

    /**
     * 调用 chat completions API
     *
     * @param string|array $messages 消息内容，支持字符串或消息数组
     * @param array $options 额外选项，会覆盖默认配置
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function completions($messages, array $options = [])
    {
        // 标准化 messages 格式
        $messagesArray = $this->normalizeMessages($messages);

        // 合并选项
        $requestOptions = array_merge(
            $this->config['options'] ?? [],
            $options
        );

        // 构建基础请求参数
        $params = [
            'model' => $this->config['model_name'] ?? 'gpt-3.5-turbo',
            'messages' => $messagesArray,
        ];

        // 添加可选参数
        $this->applyRequestOptions($params, $requestOptions);

        // 发送请求
        $response = $this->client->post('chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . ($this->config['api_key'] ?? ''),
                'Content-Type' => 'application/json',
            ],
            'json' => $params,
        ]);

        return $response;
    }

    /**
     * 流式调用 chat completions API
     *
     * @param string|array $messages
     * @param array $options
     * @param callable $callback 处理每个数据块的回调函数
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function streamCompletions($messages, array $options = [], callable $callback = null)
    {
        // 强制启用流式输出
        $options['stream'] = true;

        $messagesArray = $this->normalizeMessages($messages);
        $requestOptions = array_merge(
            $this->config['options'] ?? [],
            $options
        );

        $params = [
            'model' => $this->config['model_name'] ?? 'gpt-3.5-turbo',
            'messages' => $messagesArray,
            'stream' => true,
        ];

        // 添加可选参数
        foreach (['temperature', 'max_tokens', 'top_p', 'frequency_penalty', 'presence_penalty', 'stop'] as $key) {
            if (isset($requestOptions[$key])) {
                $params[$key] = $requestOptions[$key];
            }
        }

        // 发送流式请求
        $response = $this->client->post('chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . ($this->config['api_key'] ?? ''),
                'Content-Type' => 'application/json',
            ],
            'json' => $params,
            'stream' => true,
        ]);

        // 读取流式响应
        $body = $response->getBody();
        while (!$body->eof()) {
            $line = $this->readLine($body);
            if ($line === '') {
                continue;
            }

            // SSE 格式: data: {...}
            if (strpos($line, 'data: ') === 0) {
                $data = substr($line, 6);
                if ($data === '[DONE]') {
                    break;
                }

                $chunk = json_decode($data, true);
                if ($chunk) {
                    call_user_func($callback, $chunk);
                }
            }
        }
    }

    /**
     * 应用请求选项
     *
     * @param array $params
     * @param array $requestOptions
     * @return void
     */
    protected function applyRequestOptions(array &$params, array $requestOptions): void
    {
        foreach ($this->allowedOptions as $key) {
            if (isset($requestOptions[$key])) {
                $params[$key] = $requestOptions[$key];
            }
        }
    }

    /**
     * 从流中读取一行
     *
     * @param \Psr\Http\Message\StreamInterface $stream
     * @return string
     */
    protected function readLine($stream): string
    {
        $buffer = '';
        while (!$stream->eof()) {
            $byte = $stream->read(1);
            if ($byte === "\n") {
                break;
            }
            $buffer .= $byte;
        }
        return trim($buffer);
    }

    /**
     * 标准化消息格式
     *
     * @param string|array $messages
     * @return array
     */
    protected function normalizeMessages($messages): array
    {
        if (is_string($messages)) {
            return [
                [
                    'role' => 'user',
                    'content' => $messages,
                ],
            ];
        }

        // 如果是数组，检查是否已经是标准格式
        if (is_array($messages)) {
            // 如果第一个元素有 role 和 content，认为是标准格式
            if (isset($messages[0]['role']) && isset($messages[0]['content'])) {
                return $messages;
            }

            // 否则，将整个数组作为单条 user 消息
            return [
                [
                    'role' => 'user',
                    'content' => json_encode($messages),
                ],
            ];
        }

        return [];
    }

    /**
     * 获取当前配置
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * 获取模型名称
     *
     * @return string
     */
    public function getModelName(): string
    {
        return $this->config['model_name'] ?? 'unknown';
    }

    /**
     * 检查是否支持流式输出
     *
     * @return bool
     */
    public function supportsStreaming(): bool
    {
        return $this->config['supports_streaming'] ?? false;
    }
}

# LLM 服务使用文档

## 安装说明

```shell
composer require "jose-chan/llm-connector"
```

## 架构设计

LLM 服务采用三层架构：**Application → Model → Connection**

```
Application (应用层)
  ├── 配置业务相关参数
  ├── 指定使用的 Model
  ├── 设置 API Key
  └── 定义调用选项 (temperature, max_tokens 等)

Model (模型层)
  ├── 关联 Connection
  ├── 定义模型名称
  ├── 配置模型能力
  └── 设置模型默认参数

Connection (连接层)
  ├── 定义 API 端点
  ├── 配置超时时间
  ├── 设置连接参数
  └── 不包含敏感信息 (api_key)
```

### 设计优势

1. **多租户支持**：不同 application 可以使用不同的 API Key
2. **灵活切换**：同一 Model 可以被多个 Application 使用
3. **配置隔离**：业务配置和基础设施配置分离
4. **安全性**：API Key 只在 Application 层配置

## 配置说明

### 配置发布

````bash
php artisan vendor:publish --provider=JoseChan\LlmConnector\LlmServiceProvider
````

### config/llm.php

```php
return [
    // 默认使用的 application
    'default' => env('LLM_DEFAULT_APP', 'default'),

    // Applications - 应用配置
    'applications' => [
        'default' => [
            'model' => 'qwen-plus',
            'api_key' => env('QWEN_API_KEY', ''),
            'options' => [
                'temperature' => 0.7,
                'max_tokens' => 2000,
            ],
        ],
        'code-assistant' => [
            'model' => 'deepseek-reasoner',
            'api_key' => env('DEEPSEEK_API_KEY', ''),
            'options' => [
                'temperature' => 0.3,
                'max_tokens' => 4000,
            ],
        ],
    ],

    // Models - 模型配置
    'models' => [
        'qwen-plus' => [
            'connection' => 'qwen',
            'model_name' => 'qwen-plus',
            'supports_streaming' => true,
            'max_tokens' => 8000,
        ],
        'deepseek-reasoner' => [
            'connection' => 'deepseek',
            'model_name' => 'deepseek-reasoner',
            'supports_streaming' => true,
            'max_tokens' => 8000,
        ],
    ],

    // Connections - 连接配置
    'connections' => [
        'qwen' => [
            'driver' => 'openai',
            'base_uri' => 'https://dashscope.aliyuncs.com/compatible-mode/v1/',
            'timeout' => 60,
            'connect_timeout' => 10,
            'verify' => true,
        ],
        'deepseek' => [
            'driver' => 'openai',
            'base_uri' => 'https://api.deepseek.com/v1/',
            'timeout' => 60,
            'connect_timeout' => 10,
            'verify' => true,
        ],
    ],
]
```

## 基础使用

### 1. 使用默认 Application

```php
use App\Services\Mcp\Facade\LLM;

// 获取默认 application 的 connector
$llm = LLM::application();

// 发送简单消息
$response = $llm->completions('你好，请介绍一下你自己');

// 解析响应
$result = json_decode($response->getBody(), true);
$answer = $result['choices'][0]['message']['content'];

echo $answer;
```

### 2. 使用指定 Application

```php
use App\Services\Mcp\Facade\LLM;

// 使用 code-assistant application
$codeAssistant = LLM::application('code-assistant');

$response = $codeAssistant->completions('帮我写一个快速排序算法');
```

### 3. 发送多轮对话

```php
use App\Services\Mcp\Facade\LLM;

$llm = LLM::application();

// 构建消息数组
$messages = [
    [
        'role' => 'system',
        'content' => '你是一个专业的代码助手',
    ],
    [
        'role' => 'user',
        'content' => '请解释什么是闭包',
    ],
    [
        'role' => 'assistant',
        'content' => '闭包是指函数可以访问其外部作用域的变量...',
    ],
    [
        'role' => 'user',
        'content' => '能给我一个 PHP 的例子吗？',
    ],
];

$response = $llm->completions($messages);
```

### 4. 自定义调用选项

```php
use App\Services\Mcp\Facade\LLM;

$llm = LLM::application();

// 覆盖默认配置
$response = $llm->completions('写一首诗', [
    'temperature' => 0.9,  // 更有创造性
    'max_tokens' => 500,
    'top_p' => 0.95,
]);
```

## 流式输出

### 基础流式调用

```php
use App\Services\Mcp\Facade\LLM;

$llm = LLM::application();

// 流式输出
$llm->streamCompletions('讲一个故事', [], function($chunk) {
    if (isset($chunk['choices'][0]['delta']['content'])) {
        $content = $chunk['choices'][0]['delta']['content'];
        echo $content;
        flush();
    }
});
```

### 在 Laravel 控制器中使用流式输出

```php
use App\Services\Mcp\Facade\LLM;
use Symfony\Component\HttpFoundation\StreamedResponse;

public function stream(Request $request)
{
    $message = $request->input('message');

    return new StreamedResponse(function() use ($message) {
        $llm = LLM::application();

        $llm->streamCompletions($message, [], function($chunk) {
            if (isset($chunk['choices'][0]['delta']['content'])) {
                echo 'data: ' . json_encode([
                    'content' => $chunk['choices'][0]['delta']['content']
                ]) . "\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        });

        echo "data: [DONE]\n\n";
    }, 200, [
        'Content-Type' => 'text/event-stream',
        'Cache-Control' => 'no-cache',
        'X-Accel-Buffering' => 'no',
    ]);
}
```

## 高级功能

### 获取配置信息

```php
use App\Services\Mcp\Facade\LLM;

// 获取默认 application 名称
$defaultApp = LLM::getDefaultApplication();

// 获取所有可用的 applications
$apps = LLM::getAvailableApplications();
// ['default', 'code-assistant', 'creative-writer']

// 获取所有可用的 models
$models = LLM::getAvailableModels();
// ['qwen-plus', 'qwen-turbo', 'deepseek-reasoner', 'deepseek-chat']

// 获取所有可用的 connections
$connections = LLM::getAvailableConnections();
// ['qwen', 'deepseek']

// 检查 application 是否已连接
$isConnected = LLM::isConnected('default');
```

### 获取 Connector 信息

```php
use App\Services\Mcp\Facade\LLM;

$llm = LLM::application();

// 获取模型名称
$modelName = $llm->getModelName();  // 'qwen-plus'

// 检查是否支持流式输出
$supportsStreaming = $llm->supportsStreaming();  // true

// 获取完整配置
$config = $llm->getConfig();
```

### 清除缓存

```php
use App\Services\Mcp\Facade\LLM;

// 清除所有已创建的 connector 实例
LLM::purge();
```

## 完整示例

### 示例 1：代码审查助手

```php
use App\Services\Mcp\Facade\LLM;

class CodeReviewService
{
    public function review(string $code, string $language = 'PHP'): string
    {
        $llm = LLM::application('code-assistant');

        $message = "请审查以下 {$language} 代码：\n\n```{$language}\n{$code}\n```";

        $response = $llm->completions($message, [
            'temperature' => 0.3,  // 更专注于准确性
        ]);

        $result = json_decode($response->getBody(), true);
        return $result['choices'][0]['message']['content'];
    }
}

// 使用
$service = new CodeReviewService();
$review = $service->review('
function addNumbers($a, $b) {
    return $a + $b;
}
');

echo $review;
```

### 示例 2：聊天机器人

```php
use App\Services\Mcp\Facade\LLM;

class ChatBot
{
    protected $history = [];

    public function chat(string $userMessage): string
    {
        $this->history[] = [
            'role' => 'user',
            'content' => $userMessage,
        ];

        $llm = LLM::application();
        $response = $llm->completions($this->history);

        $result = json_decode($response->getBody(), true);
        $botMessage = $result['choices'][0]['message']['content'];

        // 添加助手的回复到历史
        $this->history[] = [
            'role' => 'assistant',
            'content' => $botMessage,
        ];

        return $botMessage;
    }

    public function reset(): void
    {
        $this->history = [];
    }
}

// 使用
$bot = new ChatBot();
echo $bot->chat('你好');
echo $bot->chat('今天天气怎么样？');
$bot->reset();
```

## 错误处理

```php
use App\Services\Mcp\Facade\LLM;
use GuzzleHttp\Exception\GuzzleException;

try {
    $llm = LLM::application('non-existent');
} catch (\Exception $e) {
    // Application 配置不存在
    Log::error('LLM application error: ' . $e->getMessage());
}

try {
    $llm = LLM::application();
    $response = $llm->completions('Hello');
} catch (GuzzleException $e) {
    // API 调用失败
    Log::error('LLM API error: ' . $e->getMessage());
}
```

## 最佳实践

1. **合理配置 Application**：为不同的业务场景创建不同的 application
2. **使用环境变量**：API Key 等敏感信息通过 .env 配置
3. **控制 Token 消耗**：合理设置 max_tokens
4. **处理失败**：添加重试机制和错误处理
5. **缓存结果**：对于相同的问题，可以缓存 LLM 响应
6. **监控成本**：记录 API 调用次数和 token 使用量

## 环境变量配置

在 `.env` 文件中添加：

```env
# LLM 配置
LLM_DEFAULT_APP=default

# Qwen API
QWEN_API_KEY=your-qwen-api-key
QWEN_BASE_URI=https://dashscope.aliyuncs.com/compatible-mode/v1/

# DeepSeek API
DEEPSEEK_API_KEY=your-deepseek-api-key
DEEPSEEK_BASE_URI=https://api.deepseek.com/v1/

# 可选：为不同应用配置不同的 Key
QWEN_CREATIVE_API_KEY=another-qwen-api-key
```

## 故障排查

### 问题：Application not configured

**原因**：指定的 application 名称在配置中不存在

**解决**：检查 `config/mcp.php` 中的 `applications` 配置

### 问题：Model not configured

**原因**：Application 指定的 model 在配置中不存在

**解决**：检查 `config/mcp.php` 中的 `models` 配置

### 问题：Connection timeout

**原因**：网络问题或 API 响应慢

**解决**：增加 `timeout` 配置值或检查网络连接

### 问题：Unauthorized (401)

**原因**：API Key 无效或未配置

**解决**：检查 `.env` 文件中的 API Key 配置

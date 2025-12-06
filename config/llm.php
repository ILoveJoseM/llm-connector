<?php

return [
    // 默认使用的 application
    'default' => env('LLM_DEFAULT_APP', 'default'),

    // Applications - 应用配置（业务级别）
    // 每个 application 配置自己的 api_key 和使用的 model
    'applications' => [
        'default' => [
            'model' => 'qwen-plus',
            'api_key' => env('QWEN_API_KEY', ''),
            'options' => [
                'temperature' => 0.7,
                'max_tokens' => 2000,
                'enable_thinking' => env('QWEN_ENABLE_THINKING', false),
            ],
        ],
    ],

    // Models - 模型配置（模型级别）
    // 定义每个模型使用哪个 connection 和模型的默认参数
    'models' => [
        'qwen-plus' => [
            'connection' => 'qwen',
            'model_name' => 'qwen-plus',
            'supports_streaming' => false,
            'max_tokens' => 8000,
        ],
        'qwen-turbo' => [
            'connection' => 'qwen',
            'model_name' => 'qwen-turbo',
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

    // Connections - 连接配置（基础设施级别）
    // 定义 API 端点的基础配置，不包含 api_key
    'connections' => [
        'qwen' => [
            'driver' => 'openai', // 兼容 OpenAI API
            'base_uri' => env('QWEN_BASE_URI', 'https://dashscope.aliyuncs.com/compatible-mode/v1/'),
            'timeout' => 60,
            'connect_timeout' => 10,
            'verify' => true,
        ],
        'deepseek' => [
            'driver' => 'openai',
            'base_uri' => env('DEEPSEEK_BASE_URI', 'https://api.deepseek.com/v1/'),
            'timeout' => 60,
            'connect_timeout' => 10,
            'verify' => true,
        ],
    ]
];

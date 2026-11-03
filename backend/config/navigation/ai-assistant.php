<?php

// Spec 0347: AI assistant page; the same permission gates every /api/ai-assistant endpoint.
return [
    'key' => 'ai-assistant',
    'label' => 'navigation.aiAssistant',
    'icon' => 'sparkles',
    'route' => '/ai-assistant',
    'permission' => 'ai-assistant.use',
];

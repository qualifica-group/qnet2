<?php

// Spec 0347: AI assistant page; the same permission gates every /api/ai-assistant endpoint.
// Shown only while AI_ASSISTANT_PAGE_ENABLED is on (D-16).
return [
    'key' => 'ai-assistant',
    'label' => 'navigation.aiAssistant',
    'icon' => 'sparkles',
    'route' => '/ai-assistant',
    'permission' => 'ai-assistant.use',
    'feature' => 'ai-assistant.page_enabled',
];

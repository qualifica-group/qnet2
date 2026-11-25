<?php

return [

    /*
    | Switches the project work order chat on or off for the whole installation
    | (spec 0432): off, its API answers 404, nobody can read or write a channel,
    | the "Non letti" column and the dock's channels disappear. The AI assistant
    | of the dock has its own switches (config/ai-assistant.php).
    */
    'enabled' => (bool) env('CHAT_ENABLED', true),

    /*
    | Minutes after sending during which an author may edit or delete their own
    | work order chat message (spec 0359, D-5). Super-admins are not limited.
    */
    'edit_window_minutes' => (int) env('CHAT_EDIT_WINDOW_MINUTES', 15),

];

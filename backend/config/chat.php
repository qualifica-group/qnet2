<?php

return [

    /*
    | Minutes after sending during which an author may edit or delete their own
    | work order chat message (spec 0352, D-5). Super-admins are not limited.
    */
    'edit_window_minutes' => (int) env('CHAT_EDIT_WINDOW_MINUTES', 15),

];

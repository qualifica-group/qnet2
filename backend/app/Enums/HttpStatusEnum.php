<?php

namespace App\Enums;

enum HttpStatusEnum: int
{
    // Success
    case OK = 200;
    case CREATED = 201;
    // Spec 0175: POST .../emails/{email}/send answers 202 -- the row moves to
    // `queued` synchronously, but delivery itself happens later in
    // SendOutboundEmailJob (D-12), so the request is "accepted", not "done".
    case ACCEPTED = 202;
    case NO_CONTENT = 204;

    // Client errors
    case BAD_REQUEST = 400;
    case UNAUTHORIZED = 401;
    case FORBIDDEN = 403;
    case NOT_FOUND = 404;
    case CONFLICT = 409;
    case UNPROCESSABLE_ENTITY = 422;
    case TOO_MANY_REQUESTS = 429;

    // Server errors
    case INTERNAL_SERVER_ERROR = 500;
}

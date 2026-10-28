<?php

namespace App\Enums;

enum AccessLogEvent: string
{
    case Login = 'login';
    case Logout = 'logout';
    case LoginFailed = 'login_failed';
}

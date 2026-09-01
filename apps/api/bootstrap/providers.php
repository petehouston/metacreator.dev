<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\MailServiceProvider;
use App\Providers\ToolServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    MailServiceProvider::class,
    ToolServiceProvider::class,
];

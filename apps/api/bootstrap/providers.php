<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\FrontendCacheServiceProvider;
use App\Providers\MailServiceProvider;
use App\Providers\ToolServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    FrontendCacheServiceProvider::class,
    MailServiceProvider::class,
    ToolServiceProvider::class,
];

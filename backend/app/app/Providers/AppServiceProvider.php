<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Xrequests\Services\Mailman\Mailman;
use Xrequests\Services\Mailman\MailmanImpl;
use Xrequests\Services\Filesystem\Filesystem;
use Xrequests\Services\Filesystem\Local;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(Mailman::class, function ($app) {
            return new MailmanImpl();
        });

        $this->app->bind(Filesystem::class, function ($app) {
            return new Local();
        });
    }

    public function boot()
    {
        //
    }
}

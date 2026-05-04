<?php

namespace Modules\AuthorChannel\Providers;

use Illuminate\Support\ServiceProvider;

class AuthorChannelServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $modulePath = __DIR__ . '/..';

        // Load routes
        if (file_exists($modulePath . '/Routes/admin.php')) {
            $this->loadRoutesFrom($modulePath . '/Routes/admin.php');
        }
        if (file_exists($modulePath . '/Routes/web.php')) {
            $this->loadRoutesFrom($modulePath . '/Routes/web.php');
        }

        // Load views
        $this->loadViewsFrom($modulePath . '/Resources/views', 'authorchannel');
    }

    public function register()
    {
        // register bindings if needed
    }
}

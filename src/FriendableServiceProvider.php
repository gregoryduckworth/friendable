<?php

namespace GregoryDuckworth\Friendable;

use Illuminate\Support\ServiceProvider;

class FriendableServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish your migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('/migrations'),
        ], 'migrations');
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}

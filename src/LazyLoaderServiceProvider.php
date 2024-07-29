<?php
namespace Basanta\LazyLoader;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Collection;

class LazyLoaderServiceProvider extends ServiceProvider
{
    public function register()
    {
        Collection::macro('lazyLoad', function() {
            return LazyLoader::make($this)->load(...func_get_args());
        });
    }
}
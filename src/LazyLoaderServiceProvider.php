<?php
namespace Basanta\LazyLoader;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Collection;

class LazyLoaderServiceProvider extends ServiceProvider
{
    public function register()
    {
        Collection::macro('lazyLoad', function($related, $relationAlias) {
            return LazyLoader::make($this)->load($related, $relationAlias);
        });
        Collection::macro('lazyload', function($related, $relationAlias) {
            return LazyLoader::make($this)->load($related, $relationAlias);
        });
    }
}
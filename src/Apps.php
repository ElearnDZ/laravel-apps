<?php

namespace ElfSundae\Laravel\Apps;

use Illuminate\Support\Collection;
use Illuminate\Contracts\Container\Container;

class Apps
{
    /**
     * The container instance.
     *
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $container;

    /**
     * The current application identifier.
     *
     * @var string|false
     */
    protected $id = false;

    /**
     * Create a new Apps instance.
     *
     * @param  \Illuminate\Contracts\Container\Container  $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;

        $this->container->rebinding('request', function () {
            $this->refreshId();
        });
    }

    /**
     * Get or check the current application identifier.
     *
     * @return string|bool
     */
    public function id()
    {
        if ($this->id === false) {
            $this->id = $this->idForUrl($this->container['request']->getUri());
        }

        if (func_num_args() > 0) {
            return in_array($this->id, is_array(func_get_arg(0)) ? func_get_arg(0) : func_get_args());
        }

        return $this->id;
    }

    /**
     * Refresh the current application identifier.
     *
     * @return $this
     */
    public function refreshId()
    {
        $this->id = false;

        return $this;
    }

    /**
     * Get application identifier for the given URL.
     *
     * @param  string  $url
     * @return string
     */
    public function idForUrl($url)
    {
        return Collection::make($this->container['config']['apps.url'])
            ->filter(function ($root) use ($url) {
                return $this->urlHasRoot($url, $root);
            })
            ->sortByDesc(function ($root) {
                return strlen($root);
            })
            ->keys()
            ->first();
    }

    /**
     * Determine if an URL has the given root URL.
     *
     * @param  string  $url
     * @param  string  $root
     * @param  bool  $strict
     * @return bool
     */
    protected function urlHasRoot($url, $root, $strict = false)
    {
        if (! $strict) {
            $url = preg_replace('#^https?://#i', '', $url);
            $root = preg_replace('#^https?://#i', '', $root);
        }

        return preg_match('~^'.preg_quote($root, '~').'([/\?#].*)?$~i', $url);
    }

    /**
     * Get the root URL for the given application identifier.
     *
     * @param  string|null  $appId
     * @return string
     */
    public function rootUrl($appId = null)
    {
        $key = $appId ? "apps.url.$appId" : 'app.url';

        return $this->container['config'][$key];
    }

    /**
     * Generate an absolute URL to the given path.
     *
     * @param  string  $path
     * @param  mixed  $query
     * @param  mixed  $appId
     * @return string
     */
    public function url($path = '', $query = [], $appId = '')
    {
        if (is_string($query)) {
            list($query, $appId) = [$appId, $query];
        }

        $url = $this->container['config']->get(
            "apps.url.$appId",
            $this->container['config']['app.url']
        );

        if ($path = ltrim($path, '/')) {
            $url .= (strpos($path, '?') === 0 ? '' : '/').$path;
        }

        if ($query && $query = http_build_query($query, '', '&', PHP_QUERY_RFC3986)) {
            $url .= (strpos($url, '?') === false ? '?' : '&').$query;
        }

        return $url;
    }
}
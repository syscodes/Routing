<?php

/**
 * Lenevor Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file license.md.
 * It is also available through the world-wide-web at this URL:
 * https://lenevor.com/license
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@Lenevor.com so we can send you a copy immediately.
 *
 * @package     Lenevor
 * @subpackage  Base
 * @link        https://lenevor.com
 * @copyright   Copyright (c) 2019 - 2022 Alexander Campo <jalexcam@gmail.com>
 * @license     https://opensource.org/licenses/BSD-3-Clause New BSD license or see https://lenevor.com/license or see /license.md
 */

namespace Syscodes\Components\Routing\Supported;

use InvalidArgumentException;
use Syscodes\Components\Support\Arr;
use Syscodes\Components\Support\Str;
use Syscodes\Components\Http\Request;
use Syscodes\Components\Routing\RouteCollection;

/**
 * Returns the URL generated by the user.
 * 
 * @author Alexander Campo <jalexcam@gmail.com>
 */
class UrlGenerator
{
    /**
     * A cached copy of the URL root for the current request.
     * 
     * @var string|null $cachedRoo
     */
    protected $cachedRoot;
    
    /**
     * A cached copy of the URL scheme for the current request.
     * 
     * @var string|null $cachedScheme
     */
    protected $cachedScheme;

    /**
     * The force URL root.
     * 
     * @var string $forcedRoot 
     */
    protected $forcedRoot;

    /**
     * The force Scheme for URLs.
     * 
     * @var string $forcedScheme
     */
    protected $forcedScheme;

    /**
     * The Request instance.
     * 
     * @var object $request
     */
    protected $request;
    
    /**
     * The route URL generator instance.
     * 
     * @var \Syscodes\Components\Routing\RouteUrlGenerator|null
     */
    protected $routeGenerator;

    /**
     * The route collection.
     * 
     * @var \Syscodes\Components\Routing\RouteCollection $routes
     */
    protected $routes;

    /**
     * Constructor. The UrlGenerator class instance.
     * 
     * @param  \Syscodes\Components\Routing\RouteCollection  $route
     * @param  \Syscodes\Components\Http\Request  $request
     * 
     * @return void
     */
    public function __construct(RouteCollection $route, Request $request)
    {
        $this->routes = $route;

        $this->setRequest($request);
    }
    
    /**
     * Get the full URL for the current request.
     * 
     * @return string
     */
    public function full(): string
    {
        return $this->request->fullUrl();
    }

    /**
     * Get the current URL for the request.
     * 
     * @return string
     */
    public function current(): string
    {
        return $this->to($this->request->getPathInfo());
    }

    /**
     * Get the URL for the previous request.
     * 
     * @param  mixed  $fallback  
     * 
     * @return string
     */
    public function previous($fallback = false): string
    {
        $referer = $this->request->referer();

        $url = $referer ? $this->to($referer) : [];

        if ($url) {
            return $url;
        } elseif ($fallback) {
            return $this->to($fallback);
        }

        return $this->to('/');
    }

    /**
     * Generate a absolute URL to the given path.
     * 
     * @param  string  $path
     * @param  mixed  $options
     * @param  bool|null  $secure
     * 
     * @return string
     */
    public function to($path, $options = [], $secure = null): string
    {
        // First we will check if the URL is already a valid URL. If it is we will not
        // try to generate a new one but will simply return the URL as is, which is
        // convenient since developers do not always have to check if it's valid.
        if ($this->isValidUrl($path)) {
            return $path;
        }

        $scheme = $this->getScheme($secure);

        $tail = implode('/', array_map('rawurlencode', (array) $options));

        $root = $this->getRootUrl($scheme);

        return $this->format($root, $path, $tail);
    }

    /**
     * Generate a secure, absolute URL to the given path.
     * 
     * @param  string  $path
     * @param  array  $parameters
     * 
     * @return string
     */
    public function secure($path, $parameters = []): string
    {
        return $this->to($path, $parameters, true);
    }

    /**
     * Generate a URL to an application asset.
     * 
     * @param  string  $path
     * @param  bool|null  $secure  
     * 
     * @return string
     */
    public function asset($path, $secure = null): string
    {
        if ($this->isValidUrl($path)) {
            return $path;
        }

        // Once we get the root URL, we will check to see if it contains an index.php
        // file in the paths. If it does, we will remove it since it is not needed
        // for asset paths, but only for routes to endpoints in the application.
        $root = $this->getRootUrl($this->getScheme($secure));

        return $this->removeIndex($root).'/'.trim($path, '/');
    }
    
    /**
     * Generate a URL to a secure asset.
     * 
     * @param  string  $path
     * 
     * @return string
     */
    public function secureAsset($path): string
    {
        return $this->asset($path, true);
    }

    /**
     * Remove the index.php file from a path.
     * 
     * @param  string  $root
     * 
     * @return string
     */
    protected function removeIndex($root): string
    {
        $index = 'index.php';

        return Str::contains($root, $index) ? str_replace('/'.$index, '', $root) : $root;
    }

    /**
     * Get the scheme for a raw URL.
     * 
     * @param  bool|null  $secure
     * 
     * @return string
     */
    public function getScheme($secure): string
    {
        if ( ! is_null($secure)) {
            return $secure ? 'https://' : 'http://';
        }

        if (is_null($this->cachedScheme)) {
            $this->cachedScheme = $this->forcedScheme ?: $this->request->getScheme().'://';
        }

        return $this->cachedScheme;
    }

    /**
     * Force the scheme for URLs.
     * 
     * @param  string  $scheme
     * 
     * @return void
     */
    public function forcedScheme($scheme): void
    {
        $this->cachedScheme = null;

        $this->forcedScheme = $scheme ? $scheme.'://' : null; 
    }

    /**
     * Get the URL to a named route.
     * 
     * @param  string  $name
     * @param  array  $parameters
     * @param  bool  $forced 
     * 
     * @return string
     * 
     * @throws \InvalidArgumentException
     */
    public function route($name, array $parameters = [], $forced = true): string
    {
        if ( ! is_null($route = $this->routes->getByName($name))) {
            return $this->toRoute($route, $parameters, $forced);
        }

        throw new InvalidArgumentException("Route [{$name}] not defined");
    }

    /**
     * Get the URL for a given route instance.
     * 
     * @param  \Syscodes\Components\Routing\Route  $route
     * @param  mixed  $parameters
     * @param  bool  $forced
     * 
     * @return string
     */
    protected function toRoute($route, $parameters, $forced): string
    {
        return $this->routeUrl()->to(
            $route, $this->formatParameters($parameters), $forced
        );
    }
    
    /**
     * Get the URL to a controller action.
     * 
     * @param  string  $action
     * @param  mixed  $parameters
     * @param  bool  $forced  
     * 
     * @return string
     * 
     * @throws \InvalidArgumentException
     */
    public function action($action, $parameters = [], $forced = true): string
    {
        return $this->route($action, $parameters, $forced, $this->routes->getByAction($action));
    }
    
    /**
     * Format the array of URL parameters.
     * 
     * @param  mixed|array  $parameters
     * 
     * @return array
     */
    public function formatParameters($parameters): array
    {
        $parameters = Arr::wrap($parameters);
        
        foreach ($parameters as $key => $parameter) {
            $parameters[$key] = $parameter;
        }
        
        return $parameters;
    }

    /**
     * Set the forced root URL.
     * 
     * @param  string  $root
     * 
     * @return void
     */
    public function forcedRoot($root): void
    {
        $this->forcedRoot = $root;
    }
    
    /**
     * Get the base URL for the request.
     * 
     * @param  string  $scheme
     * @param  string|null  $root
     * 
     * @return string
     */
    public function getRootUrl($scheme, $root = null): string
    {
        if (is_null($root)) {
            if (is_null($this->cachedRoot)) {
                $this->cachedRoot = $this->forcedRoot ?: $this->request->root();
            }

            $root = $this->cachedRoot;
        }

        $start = Str::startsWith($root, 'http://') ? 'http://' : 'https://';

        return preg_replace('~'.$start.'~', $scheme, $root, 1);
    }
    
    /**
     * Determine if the given path is a valid URL.
     * 
     * @param  string  $path
     * 
     * @return bool
     */
    public function isValidUrl($path): bool
    {
        if (Str::startsWith($path, ['#', '//', 'mailto:', 'tel:', 'http://', 'https://'])) {
            return true;
        }
        
        return filter_var($path, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Get the Route URL generator instance.
     * 
     * @return \Syscodes\Components\Routing\RouteUrlGenerator
     */
    protected function routeUrl()
    {
        if ( ! $this->routeGenerator) {
            $this->routeGenerator = new RouteUrlGenerator($this, $this->request);
        }
        
        return $this->routeGenerator;
    }

    /**
     * Format the given URL segments into a single URL.
     * 
     * @param  string  $root
     * @param  string  $path
     * @param  string  $tail
     * 
     * @return string
     */
    public function format($root, $path, $tail = ''): string
    {
        return trim($root .'/' .trim($path .'/' .$tail, '/'), '/');
    }

    /**
     * Gets the Request instance.
     * 
     * @return \Syscodes\Components\Http\Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Sets the current Request instance.
     * 
     * @param  \Syscodes\Components\Http\Request  $request
     * 
     * @return void
     */
    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }
}
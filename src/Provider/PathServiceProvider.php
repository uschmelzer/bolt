<?php

namespace Bolt\Provider;

use Bolt\Configuration\ForwardToPathResolver;
use Bolt\Configuration\LazyPathsProxy;
use Bolt\Configuration\PathResolverFactory;
use Bolt\Configuration\ResourceManager;
use Bolt\Exception\BootException;
use Eloquent\Pathogen\FileSystem\Factory\PlatformFileSystemPathFactory;
use Silex\Application;
use Silex\ServiceProviderInterface;

class PathServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        // @deprecated
        if (!isset($app['path_resolver_factory'])) {
            $app['path_resolver_factory'] = $app->share(
                function ($app) {
                    return (new PathResolverFactory())
                        ->setRootPath($app['path_resolver.root'])
                        ->addPaths($app['path_resolver.paths'])
                    ;
                }
            );
        }

        $app['path_resolver'] = $app->share(
            function ($app) {
                $resolver = $app['path_resolver_factory']
                    ->addPaths($app['path_resolver.paths'])
                    ->create()
                ;

                // Bolt's project directory. Not configurable.
                $resolver->define('bolt', __DIR__ . '/../../');

                return $resolver;
            }
        );
        $app['path_resolver.root'] = '';
        $app['path_resolver.paths'] = [];

        $app['pathmanager'] = $app->share(
            function () {
                $filesystempath = new PlatformFileSystemPathFactory();

                return $filesystempath;
            }
        );

        if (!isset($app['resources'])) {
            $app['resources'] = $app->share(
                function ($app) {
                    $resources = new ForwardToPathResolver(new \ArrayObject([
                        'rootpath'              => $app['path_resolver.root'],
                        'path_resolver'         => $app['path_resolver'],
                        'path_resolver_factory' => $app['path_resolver_factory'],
                        'pathmanager'           => $app['pathmanager'],
                    ]));

                    return $resources;
                }
            );
        }

        $resourcesSetup = function (ResourceManager $resources) use ($app) {
            // This is to sync service if ResourceManager is created without the factory passed in.
            // In most cases it is so this technically doesn't change anything.
            $app['path_resolver_factory'] = $resources->getPathResolverFactory();

            $resources->setApp($app);
        };

        // Run resources setup either immediately if instance is given or lazily if closure is given.
        $resources = $app->raw('resources');
        if ($resources instanceof ResourceManager) {
            $resourcesSetup($resources);
        } else {
            $app['resources'] = $app->share(
                $app->extend(
                    'resources',
                    function ($resources) use ($resourcesSetup) {
                        $resourcesSetup($resources);

                        return $resources;
                    }
                )
            );
        }

        $app['classloader'] = $app->share(function ($app) {
            return $app['resources']->getClassLoader();
        });

        $app['paths'] = $app->share(function ($app) {
            return new LazyPathsProxy(function () use ($app) {
                return $app['resources'];
            });
        });
    }

    public function boot(Application $app)
    {
        $resolver = $app['path_resolver'];

        $theme = $app['config']->get('general/theme');
        $resolver->define('theme', "%themes%/$theme");

        if (!file_exists($resolver->resolve('web')) &&
            (!file_exists($resolver->resolve('.bolt.yml')) || !file_exists($resolver->resolve('.bolt.php')))
        ) {
            BootException::earlyExceptionMissingLoaderConfig();
        }
    }
}

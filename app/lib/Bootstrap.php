<?php

namespace Honeybee\FrameworkBinding\Silex;

use Auryn\Injector;
use Auryn\StandardReflector;
use Honeybee\FrameworkBinding\Silex\Config\ConfigProvider;
use Honeybee\FrameworkBinding\Silex\Config\ConfigProviderInterface;
use Honeybee\FrameworkBinding\Silex\Config\Handler\CrateConfigHandler;
use Honeybee\FrameworkBinding\Silex\Controller\ControllerResolverServiceProvider;
use Honeybee\FrameworkBinding\Silex\Crate\CrateLoader;
use Honeybee\FrameworkBinding\Silex\Crate\CrateLoaderInterface;
use Honeybee\FrameworkBinding\Silex\Service\ServiceProvider;
use Honeybee\FrameworkBinding\Silex\Service\ServiceProvisioner;
use Honeybee\Infrastructure\Config\ArrayConfig;
use Honeybee\Infrastructure\Config\ConfigInterface;
use Honeybee\Infrastructure\Config\Settings;
use Psr\Log\LoggerInterface;
use Silex\Application;
use Silex\Provider\AssetServiceProvider;
use Silex\Provider\HttpFragmentServiceProvider;
use Silex\Provider\MonologServiceProvider;
use Silex\Provider\WebProfilerServiceProvider;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Parser;

class Bootstrap
{
    public function __invoke(Application $app, array $settings)
    {
        $injector = new Injector(new StandardReflector);
        $config = $this->bootstrapConfig($app, $injector, $settings);
        $app['version'] = $config->getVersion();
        $this->bootstrapLogger($app, $config, $injector);
        // then kick off service provisioning
        $serviceProvisioner = new ServiceProvisioner($app, $injector, $config, $config->provide('services.yml'));
        // and register some standard service providers.
        $app->register(new ServiceProvider($serviceProvisioner));
        $app->register(new ControllerResolverServiceProvider);
        $app->register(new AssetServiceProvider);
        $app->register(new HttpFragmentServiceProvider);
        // load context specific configuration (well, only web atm. needs to change too)
        if ($config->getAppContext() === 'web') {
            $this->registerWebErrorHandler($app);
            $this->loadProjectRoutes($config->getConfigDir().'/routing.php', $app);
            // dev specific switches
            if ($config->getAppEnv() === 'dev') {
                $app['debug'] = true;
                $app->register(
                    new WebProfilerServiceProvider(),
                    [ 'profiler.cache_dir' => $config->getProjectDir().'/var/cache/profiler' ]
                );
            }
        }
        // load environment specific configuration (this has to change badly)
        $envConfig = $config->getEnvConfigPath();
        if (is_readable($envConfig)) {
            require $envConfig;
        }

        return $app;
    }

    protected function loadProjectRoutes($routesFile, Application $routing)
    {
        if (is_readable($routesFile)) {
            $app = $routing;
            require $routesFile;
        }
    }

    protected function registerWebErrorHandler(Application $app)
    {
        $app->error(function (\Exception $e, Request $request, $code) use ($app) {
            if ($app['debug']) {
                return;
            }
            // 404.html, or 40x.html, or 4xx.html, or error.html
            $templates = [
                'errors/' . $code . '.html.twig',
                'errors/' . substr($code, 0, 2) . 'x.html.twig',
                'errors/' . substr($code, 0, 1) . 'xx.html.twig',
                'errors/default.html.twig',
            ];

            return new Response(
                $app['twig']->resolveTemplate($templates)->render([ 'code' => $code ]),
                $code
            );
        });
    }

    protected function bootstrapConfig(Application $app, Injector $injector, array $settings)
    {
        $configDir = $settings['project']['config_dir'];
        $configHandlers = new ArrayConfig(
            (new Parser)->parse(
                file_get_contents($configDir.'/config_handlers.yml')
            )
        );
        $crateMap = (new CrateLoader)->loadCrates(
            $app,
            (new CrateConfigHandler)->handle([ $configDir.'/crates.yml' ])
        );
        // load crates and init config-provider
        $config = new ConfigProvider(new Settings($settings), $crateMap, $configHandlers, new Finder);
        $injector->share($config)->alias(ConfigProviderInterface::CLASS, get_class($config));

        return $config;
    }

    protected function bootstrapLogger(Application $app, ConfigProviderInterface $config, Injector $injector)
    {
        // register logger as first item within the DI chain
        $app->register(new MonologServiceProvider(), [
            'monolog.logfile' => $config->getProjectDir().'/var/logs/silex_dev.log'
        ]);
        $logger = $app['logger'];
        $injector
            ->share($logger)
            ->alias(LoggerInterface::CLASS, get_class($logger));

        return $logger;
    }
}
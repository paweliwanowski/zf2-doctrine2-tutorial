<?php

namespace Application;

use Zend\Module\Manager,
    Zend\EventManager\StaticEventManager,
    Zend\Module\Consumer\AutoloaderProvider;

class Module implements AutoloaderProvider
{
    protected $view;
    protected $viewListener;

    public function init(Manager $moduleManager)
    {
        $events = StaticEventManager::getInstance();
        $events->attach('bootstrap', 'bootstrap', array($this, 'initializeView'), 100);
        // Init Doctrine
        $events->attach('bootstrap', 'bootstrap', array($this, 'initializeDoctrine'));
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\ClassMapAutoloader' => array(
                __DIR__ . '/autoload_classmap.php',
            ),
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }
    
    public function initializeView($e)
    {
        $app          = $e->getParam('application');
        $locator      = $app->getLocator();
        $config       = $e->getParam('config');
        $view         = $this->getView($app);
        $viewListener = $this->getViewListener($view, $config);
        $app->events()->attachAggregate($viewListener);
        $events       = StaticEventManager::getInstance();
        $viewListener->registerStaticListeners($events, $locator);
    }
    
    public function initializeDoctrine ($e) {
        // YAML metadata
        $metaConfig = \Doctrine\ORM\Tools\Setup::createYAMLMetadataConfiguration(array('models/Yaml'), true, 'models/Proxies');
        // Proxies
        $metaConfig->setProxyNamespace('Proxies');
        // Cache
        $metaConfig->setMetadataCacheImpl(new \Doctrine\Common\Cache\ArrayCache);
        // Class loaders
        $classLoader = new \Doctrine\Common\ClassLoader('Entities', 'models');
        $classLoader->register();
        $classLoader = new \Doctrine\Common\ClassLoader('Proxies', 'models');
        $classLoader->register();
        // Get application config
        $config = $e->getParam('config');
        // Create and register Entity Manager
        $em = \Doctrine\ORM\EntityManager::create($config['doctrine']['connection']->toArray(), $metaConfig);
        // Register Entity Manager
        \Zend\Registry::set('em', $em);
    }

    protected function getViewListener($view, $config)
    {
        if ($this->viewListener instanceof View\Listener) {
            return $this->viewListener;
        }

        $viewListener       = new View\Listener($view, $config->layout);
        $viewListener->setDisplayExceptionsFlag($config->display_exceptions);

        $this->viewListener = $viewListener;
        return $viewListener;
    }

    protected function getView($app)
    {
        if ($this->view) {
            return $this->view;
        }

        $locator = $app->getLocator();
        $view    = $locator->get('view');
        $url     = $view->plugin('url');
        $url->setRouter($app->getRouter());

        $view->plugin('headTitle')->setSeparator(' - ')
                                  ->setAutoEscape(false)
                                  ->append('ZF2 Skeleton Application');

        $basePath = $app->getRequest()->detectBaseUrl();

        $view->plugin('headLink')->appendStylesheet($basePath . 'css/bootstrap.min.css');

        $html5js = '<script src="' . $basePath . 'js/html5.js"></script>';
        $view->plugin('placeHolder')->__invoke('html5js')->set($html5js);
        $favicon = '<link rel="shortcut icon" href="' . $basePath . 'images/favicon.ico">';
        $view->plugin('placeHolder')->__invoke('favicon')->set($favicon);

        $this->view = $view;
        return $view;
    }
}

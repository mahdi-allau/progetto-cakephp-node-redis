<?php
declare(strict_types=1);

namespace App;

use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Datasource\FactoryLocator;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Http\BaseApplication;
use Cake\Http\Middleware\BodyParserMiddleware;
use Cake\Http\Middleware\CsrfProtectionMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\ORM\Locator\TableLocator;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;

/**
 * Application setup class.
 *
 * @extends \Cake\Http\BaseApplication<\App\Application>
 */
class Application extends BaseApplication
{
    /**
     * Load all the application configuration and bootstrap logic.
     *
     * @return void
     */
    public function bootstrap(): void
    {
        // Call parent to load bootstrap from files.
        parent::bootstrap();

        if (PHP_SAPI !== 'cli') {
            // The bake plugin requires fallback table classes to work properly
            FactoryLocator::add('Table', (new TableLocator())->allowFallbackClass(false));
        }
    }

    /**
     * Setup the middleware queue your application will use.
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to setup.
     * @return \Cake\Http\MiddlewareQueue The updated middleware queue.
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        // Middleware per errori
        $middlewareQueue->add(new ErrorHandlerMiddleware(Configure::read('Error'), $this));

        // Asset statici (css/js)
        $middlewareQueue->add(new AssetMiddleware([
            'cacheTime' => Configure::read('Asset.cacheTime'),
        ]));

        // Routing
        $middlewareQueue->add(new RoutingMiddleware($this));

        // Parse JSON, form, ecc.
        $middlewareQueue->add(new BodyParserMiddleware());

        // CSRF protection
        $csrf = new CsrfProtectionMiddleware([
            'httponly' => true,
        ]);

        // ⛔ Salta il CSRF per /api/... e /auth/...
        $csrf->skipCheckCallback(function ($request) {
            $path = $request->getUri()->getPath();

            // tutte le URL che iniziano con /api/ o /auth/ NON richiedono CSRF
            if (str_starts_with($path, '/api/') || str_starts_with($path, '/auth/')) {
                return true; // salta il controllo
            }

            return false; // per il resto, CSRF attivo
        });

        $middlewareQueue->add($csrf);

        return $middlewareQueue;
    }

    /**
     * Register application container services.
     *
     * @param \Cake\Core\ContainerInterface $container The Container to update.
     * @return void
     */
    public function services(ContainerInterface $container): void
    {
    }
}

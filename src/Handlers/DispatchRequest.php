<?php

namespace PHPNomad\Static\Handlers;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use PHPNomad\Events\Interfaces\CanHandle;
use PHPNomad\Events\Interfaces\Event;
use PHPNomad\Http\Enums\Method;
use PHPNomad\Http\Interfaces\Request;
use PHPNomad\Http\Interfaces\Response;
use PHPNomad\Static\Events\RequestInitiated;
use PHPNomad\Static\Providers\MarkdownRouteProvider;

use PHPNomad\Static\Services\NavigationGeneratorService;
use PHPNomad\Template\Interfaces\CanRender;

use function FastRoute\simpleDispatcher;

/**
 * @extends CanHandle<RequestInitiated>
 */
class DispatchRequest implements CanHandle
{
    public function __construct(
      protected Request $request,
      protected Response $response,
      protected MarkdownRouteProvider $markdownRouteProvider,
      protected NavigationGeneratorService $sidebarGeneratorService,
      protected CanRender $template
    ) {}

    public function handle(Event $event): void
    {
        $dispatcher = simpleDispatcher(function(RouteCollector $r) {
            foreach ($this->markdownRouteProvider->getRoutes() as $route) {
                $r->addRoute(Method::Get, $route->endpoint, fn(Request $request) => $route->controller->content($route, $request));
            }
        });

        $routeInfo = $dispatcher->dispatch(Method::Get, $event->uri);

        switch ($routeInfo[0]) {
            case Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $request = clone $this->request;

                // Add route parameters
                foreach ($routeInfo[2] as $key => $value) {
                    $request->setParam($key, $value);
                }

                $event->setResponse($handler($request));
                break;

            default:
                $response = clone $this->response;
                $response->setStatus(404);
                $response->setBody($this->template->render('404',            [
                  'sidebarItems' => $this->sidebarGeneratorService->generateItems()
                ]));
                $event->setResponse($response);
                break;
        }
    }
}
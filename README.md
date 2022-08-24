# Waiter HTTP Server
#### An inelegant HTTP server written in PHP

Let's marry an HTTP server and direct access to PHP code. What's the harm?

## Installation

Installation is done using composer:

```console
composer require waiter-http/waiter
```

### Requirements
- PHP 8.1 or higher
- A PSR-15 compatible middleware stack to handle the request

## Usage
Waiter HTTP Server is a small, single threaded HTTP server that creates a 
PSR-15 compatible request object, passes it off to a PSR-15 compatible handler,
and returns the response back to a client. You define the IP, port, and public
file directory, pass it a middleware handler, and let the system go from there.

For example, you can use [pocket-framework/framework](https://github.com/dragonmantank/pocket-framework)
to define your routes and actions/controllers, and pass the framework application
to Waiter.

```php
// public/index.php
use DI\ContainerBuilder;
use Waiter\Waiter\Server;
use FastRoute\RouteCollector;
use PocketFramework\Framework\Application;
use PocketFramework\Framework\Router\RouteProcessor;

require_once __DIR__ . '/../vendor/autoload.php';

// Bootstrap Pocket Framework
$container = (new ContainerBuilder())->build();
$dispatcher = FastRoute\simpleDispatcher(function (RouteCollector $r) {
    $processor = new RouteProcessor(realpath(__DIR__ . '/../src/HTTP/'), 'Dragonmantank\\MyApp\\HTTP\\');
    $processor->addRoutes($r);
});
$app = new Application($dispatcher, $container);

// Create the HTTP server and pass it the Pocket Framework instance
$waiter = new Server('127.0.0.1', 3000, __DIR__);
$waiter->setHandler($app);
$waiter->run();
```

In the above example, Waiter will listen on the `127.0.0.1` IP address on port
3000. It will then wait until an HTTP request comes in. The text is converted
into a PSR-7 Request thanks to [laminas/laminas-diactoros](https://github.com/laminas/laminas-diactoros).
The Request is passed to any PSR-15 compatible middleware runner, which in this
case is being provided by Pocket Framework. Pocket Framework will return a PSR-7
Response object, and Waiter will deserialize the object back to text and return
it to the client.

Waiter is able to handle concurrent connections so is partially async. More
work needs to be done to make it more async. Depending on the actual PSR-15
handler, Waiter has shown to handle up to 300 concurrent connections with
synthetic tests and a minimal routing set.

## Configuration
Waiter takes three configuration parameters to it's `Server` object:

- `$listenAddress` - The IP address to bind to and list for socket connections
- `$listenPortNumber` - The port to listen for a connection on
- `$publicAssets` - Directory where static, public assets will be searched for
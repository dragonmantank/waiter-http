<?php
declare(strict_types=1);

namespace WaiterHttp\Waiter;

use Laminas\Diactoros\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\Response\Serializer;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Request\Serializer as RequestSerializer;

class Server
{
    protected RequestHandlerInterface $handler;

    /**
     * @var Socket
     */
    protected $socket;

    protected $routes = [];

    public function __construct(
        protected string $listenAddress,
        protected int $listenPortNumber,
        protected string $publicAssets,
    )
    {

    }

    public function addRoute(string $route, callable $callback) {
        $this->routes[$route] = $callback;
    }

    public function processRequest(RequestInterface $request): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $resourcePath = realpath($this->publicAssets . $path);
        
        if (is_file($resourcePath)) {
            $fh = fopen($resourcePath, 'r');
            $response = new Response($fh);
            return $response;
        }

        $response = $this->handler->handle($request);
        return $response;
    }

    public function run()
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, 0);
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($this->socket, $this->listenAddress, $this->listenPortNumber);
        socket_listen($this->socket);

        $originalReadSockets = [$this->socket];
        $writeSockets = NULL;
        $exceptSockets = [];
        while (true) {
            $readSockets = $originalReadSockets;
            $numChanged = socket_select($readSockets, $writeSockets, $exceptSockets, 0);
            if ($numChanged === false) {
                continue;
            }

            if ($numChanged === 0) {
                continue;
            }

            foreach ($exceptSockets as $socket) {
                echo 'Closing bad socket' . PHP_EOL;
                socket_close($socket);
                unset($readSockets[array_search($socket, $readSockets)]);
                unset($exceptSockets[array_search($socket, $exceptSockets)]);
            }

            if (in_array($this->socket, $readSockets)) {
                $originalReadSockets[] = $newSocket = socket_accept($this->socket);
                unset($readSockets[array_search($this->socket, $readSockets)]);
            }

            foreach($readSockets as $currentSocket) {
                $data = socket_read($currentSocket, 1024);
                if ($data === false) {
                    unset($originalReadSockets[array_search($currentSocket, $originalReadSockets)]);
                    continue;
                }

                $data = trim($data);
                if (!empty($data)) {
                    $request = RequestSerializer::fromString($data);
                    $response = $this->processRequest($request);
                    $responseString = Serializer::toString($response);
                    socket_write($currentSocket, $responseString, strlen($responseString));
                    socket_close($currentSocket);
                    unset($originalReadSockets[array_search($currentSocket, $originalReadSockets)]);
                }
            }
        }
        socket_close($this->socket);
    }

    public function setHandler($handler)
    {
        $this->handler = $handler;
    }
}
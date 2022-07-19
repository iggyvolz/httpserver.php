<?php

use iggyvolz\httpserver\HttpServer;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Revolt\EventLoop;

require_once __DIR__ . "/vendor/autoload.php";
$server = new HttpServer(8081, new class implements RequestHandlerInterface {

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $data = [
            "headers" => $request->getHeaders(),
            "method" => $request->getMethod(),
            "body" => $request->getBody()->getContents(),
            "version" => $request->getProtocolVersion(),
        ];
        return new Response(body: json_encode($data));
    }
});
$server->start();

EventLoop::repeat(0.01, function() use($server) { $server->poll(); });
EventLoop::run();
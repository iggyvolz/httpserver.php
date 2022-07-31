<?php
namespace iggyvolz\httpserver;

use FFI;
use FFI\CData;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

class HttpServer
{
    private static ?FFI $ffi = null;
    public static function ffi(): FFI
    {
        return self::$ffi ??= FFI::cdef(file_get_contents(__DIR__ . "/../lib/header.h"), __DIR__ . "/../lib/httpserver.so");
    }
    private readonly CData $cdata;

    public function __construct(public readonly int $port, private readonly RequestHandlerInterface $requestHandler)
    {
        $this->cdata = self::ffi()->http_server_init($port, $this->handle(...));
    }

    public function listen(?string $address = null): never
    {
        $response = self::ffi()->http_server_listen_addr($this->cdata, $address);
        throw new RuntimeException("Error $response");
    }

    /** Start listening, call poll() every frame */
    public function start(?string $address = null): void
    {
        $response = self::ffi()->http_server_listen_addr_poll($this->cdata, $address);
        if($response !== 0) {
            throw new RuntimeException("Error $response");
        }
    }

    public function poll(): bool
    {
        return self::ffi()->http_server_poll($this->cdata) === 1;
    }

    private static function getRequestHeaders(CData $request): array
    {
        $headers = [];
        $iter = FFI::new("int");
        $key = self::ffi()->new("struct http_string_s");
        $val = self::ffi()->new("struct http_string_s");
        while(self::ffi()->http_request_iterate_headers($request, FFI::addr($key), FFI::addr($val), FFI::addr($iter)) !== 0) {
            $headers[FFI::string($key->buf, $key->len)] = FFI::string($val->buf, $val->len);
        }
        return $headers;
    }

    private static function getString(CData $httpStringS): string
    {
        return FFI::string($httpStringS->buf, $httpStringS->len);
    }

    private function handle(CData $request): void
    {
        $response = $this->requestHandler->handle(new ServerRequest(
            method: self::getString(self::ffi()->http_request_method($request)),
            uri: self::getString(self::ffi()->http_request_target($request)),
            headers: self::getRequestHeaders($request),
            body: new RequestStreamWrapper($request),
            // poor man's http_request_version
            version: self::getString(self::ffi()->http_get_token_string($request, 3)),
        ));
        $responseObj = self::ffi()->http_response_init();
        self::ffi()->http_response_status($responseObj, $response->getStatusCode());
        foreach ($response->getHeaders() as $key => $values) {
            foreach($values as $value) {
                self::ffi()->http_response_header($responseObj, $key, $value);
            }
        }
        $body = $response->getBody();
        $body->rewind();
        /** @noinspection PhpUnreachableStatementInspection */
        $conts = $body->getContents();
        self::ffi()->http_response_body($responseObj, $conts, strlen($conts));

        self::ffi()->http_respond($request, $responseObj);
    }

}

<?php

namespace iggyvolz\httpserver;

use FFI;
use FFI\CData;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

class RequestStreamWrapper implements StreamInterface
{
    private int $pos = 0;
    private string $buf = "";
    private bool $eof = false;
    public function __construct(private readonly CData $request)
    {
    }

    public function __toString()
    {
        return $this->getContents();
    }

    public function close()
    {
    }

    public function detach()
    {
        return null;
    }

    public function getSize()
    {
        return null;
    }

    public function tell(): int
    {
        return $this->pos;
    }

    public function eof(): bool
    {
        return $this->eof;
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function seek($offset, $whence = SEEK_SET): never
    {
        throw new RuntimeException();
    }

    public function rewind(): never
    {
        throw new RuntimeException();
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write($string): never
    {
        throw new RuntimeException();
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function read($length): string
    {
        while(strlen($this->buf) < $length) {
            // Read a chunk
            HttpServer::ffi()->http_request_read_chunk($this->request, static function(){
            });
            $chunk = HttpServer::ffi()->http_request_chunk($this->request);
            $chunk = FFI::string($chunk->buf, $chunk->len);
            if($chunk === "") break;
            $this->buf .= $chunk;
            $this->pos += strlen($chunk);
        }
        $out = substr($this->buf, 0, $length);
        $this->buf = substr($this->buf, $length);
        return $out;
    }

    public function getContents(): string
    {
        $buf = "";
        while(($chunk = $this->read(1024)) !== "") {
            $buf .= $chunk;
        }
        return $buf;
    }

    public function getMetadata($key = null): ?array
    {
        if($key === null) return [];
        return null;
    }
}
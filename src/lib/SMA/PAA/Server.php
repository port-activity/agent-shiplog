<?php
namespace SMA\PAA;

class Server
{
    private $data;
    public function __construct($data = [])
    {
        $this->data = $data ? $data : $_SERVER;
    }
    private function get($key): string
    {
        return array_key_exists($key, $this->data) ? $this->data[$key] : "";
    }
    public function requestUri(): string
    {
        return $this->get("REQUEST_URI");
    }
    public function requestMethod(): string
    {
        return $this->get("REQUEST_METHOD");
    }
    public function hostname(): string
    {
        return $this->get("SERVER_NAME");
    }
    public function authorization()
    {
        return $this->get('HTTP_AUTHORIZATION');
    }
    public function isDev(): bool
    {
        return $this->hostname() === "localhost";
    }
    public function bodyParameters(): array
    {
        $json = file_get_contents('php://input');
        return $json ? json_decode($json, true) : [];
    }
}

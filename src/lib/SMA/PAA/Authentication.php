<?php
namespace SMA\PAA;

class Authentication
{
    public function isAutheticated(string $authKey): bool
    {
        $server = new Server();
        if ($server->authorization()) {
            list($prefix, $key) = explode(" ", $server->authorization());
            return $prefix === "ApiKey" && $key === $authKey;
        }
        return false;
    }
}

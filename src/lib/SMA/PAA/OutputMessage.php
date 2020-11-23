<?php
namespace SMA\PAA;

class OutputMessage
{

    public function ok(int $code, string $message)
    {
        http_response_code($code);
        echo json_encode([
            "status" => "ok",
            "message" => $message
        ]);
        exit(0);
    }

    public function error(int $code, string $message)
    {
        http_response_code($code);
        echo json_encode([
            "status" => "error",
            "message" => $message
        ]);
        exit(0);
    }
}

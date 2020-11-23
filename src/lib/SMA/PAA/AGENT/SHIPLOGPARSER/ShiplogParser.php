<?php
namespace SMA\PAA\AGENT\SHIPLOGPARSER;

use SMA\PAA\RESULTPOSTER\IResultPoster;
use SMA\PAA\AGENT\ApiConfig;
use SMA\PAA\AINO\AinoClient;

use Exception;

class ShiplogParser
{
    private $inputJson;
    private $resultPoster;
    private $aino;

    public function __construct(
        IResultPoster $resultPoster,
        string $inputJson,
        AinoClient $aino = null,
        string $mapping = null
    ) {
        $this->resultPoster = $resultPoster;
        $this->inputJson = $inputJson;
        $this->aino = $aino;
        $this->mapping = $mapping;
    }

    public function execute(ApiConfig $apiConfig)
    {
        if ($this->inputJson === "") {
            throw new Exception("No input JSON defined");
        }
        $data = json_decode($this->inputJson, true);
        if (!$data) {
            throw new Exception("Not valid JSON");
        }
        $tools = new ShiplogTools();
        $result = $tools->parse($data, $this->mapping);
        if (!is_array($result)) {
            throw new ENoResults("Could not parse data");
        }

        $ainoTimestamp = gmdate("Y-m-d\TH:i:s\Z");

        $ainoFlowId = $this->resultPoster->resultChecksum($apiConfig, $result);
        try {
            $this->resultPoster->postResult($apiConfig, $result);
            if (isset($this->aino)) {
                $this->aino->succeeded(
                    $ainoTimestamp,
                    "Shiplog agent succeeded",
                    "Post",
                    "timestamp",
                    ["imo" => $result["imo"]],
                    [],
                    $ainoFlowId
                );
            }
        } catch (\Exception $e) {
            error_log($e->getMessage());
            error_log($e->getTraceAsString());
            if (isset($this->aino)) {
                $this->aino->failure(
                    $ainoTimestamp,
                    "Shiplog agent failed",
                    "Post",
                    "timestamp",
                    ["imo" => $result["imo"]],
                    [],
                    $ainoFlowId
                );
            }
        }
    }
}

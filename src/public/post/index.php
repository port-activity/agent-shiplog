<?php
namespace SMA\PAA\AGENT;

require_once __DIR__ . "/../../lib/init.php";

use SMA\PAA\Authentication;
use SMA\PAA\OutputMessage;
use SMA\PAA\CURL\CurlRequest;
use SMA\PAA\RESULTPOSTER\ResultPoster;
use SMA\PAA\AGENT\SHIPLOGPARSER\ShiplogParser;
use SMA\PAA\AGENT\SHIPLOGPARSER\ESignalloss;
use SMA\PAA\AGENT\SHIPLOGPARSER\EAisClassBWithoutImo;
use SMA\PAA\AGENT\ApiConfig;
use SMA\PAA\AINO\AinoClient;
use Exception;

$shipLogAuthKey = getenv("SHIPLOG_AUTH_KEY"); // used for incoming auth
$apiKey = getenv("API_KEY");
$apiUrl = getenv("API_URL");
$ainoKey = getenv("AINO_API_KEY");
$mapping = getenv('AREA_TO_STATUS_MAPPING');
$ainoTimestamp = gmdate("Y-m-d\TH:i:s\Z");

$aino = null;
if ($ainoKey) {
    $aino = new AinoClient($ainoKey, "Shiplog service", "Shiplog");
}

$authentication = new Authentication();
$output = new OutputMessage();
if (!$authentication->isAutheticated($shipLogAuthKey)) {
    $output->error(401, "Invalid auhorization");
}

$authFileForUploadChecks = "/usr/local/etc/users";
if (!file_exists($authFileForUploadChecks)) {
    exec("htpasswd -cb /usr/local/etc/users shiplog " . escapeshellarg($shipLogAuthKey));
}

$timestamp = gmdate("Y-m-d\TH:i:s\Z");

$dir = "/var/www/src/public/uploads/";
$targetFile = $dir . "shiplog-" . gmdate("Y-m-d\TH:i:s\Z") . "-" . uniqid() . ".json";
$lastProcessed = $dir . "last.json";
$lastFailed = $dir . "last-failed.json";

$json = file_get_contents('php://input');

if ($json) {
    file_put_contents($targetFile, $json);

    if (isset($aino)) {
        $aino->succeeded(
            $ainoTimestamp,
            "Shiplog agent succeeded",
            "Fetch",
            "timestamp",
            [],
            ["file" => basename($targetFile)]
        );
    }

    $apiParameters = ["imo", "vessel_name", "time_type", "state", "time", "payload"];

    $apiConfig = new ApiConfig($apiKey, $apiUrl, $apiParameters);

    $ainoForAgent = null;
    if ($ainoKey) {
        $toApplication = parse_url($apiUrl, PHP_URL_HOST);
        $ainoForAgent = new AinoClient($ainoKey, "Shiplog", $toApplication);
    }

    $agent = new ShiplogParser(
        new ResultPoster(new CurlRequest()),
        $json,
        $ainoForAgent,
        $mapping
    );

    try {
        $agent->execute($apiConfig);
        file_put_contents($lastProcessed, $json);
        file_put_contents($lastProcessed . "-time.txt", date("c"));
        unlink($targetFile);
        if (isset($aino)) {
            $aino->succeeded(
                $ainoTimestamp,
                "Shiplog agent succeeded",
                "Parse",
                "timestamp",
                [],
                ["file" => basename($targetFile)]
            );
        }
        $output->ok(200, "File posted");
    } catch (ESignalloss $e) {
        // skip signalloss message yet return ok message
        // unlink($targetFile); //TODO: keep files still for debuging
        if (isset($aino)) {
            $aino->succeeded(
                $ainoTimestamp,
                "Shiplog agent succeeded",
                "Signalloss",
                "timestamp",
                [],
                ["file" => basename($targetFile)]
            );
        }
        $output->ok(200, "File received ok but not parsed since signalloss");
    } catch (EAisClassBWithoutImo $e) {
        // skip AIS class B without IMO message yet return ok message
        // unlink($targetFile); //TODO: keep files still for debuging
        if (isset($aino)) {
            $aino->succeeded(
                $ainoTimestamp,
                "Shiplog agent succeeded",
                "AisBNoIMO",
                "timestamp",
                [],
                ["file" => basename($targetFile)]
            );
        }
        $output->ok(200, "File received ok but not parsed since AIS class B without IMO");
    } catch (Exception $e) {
        file_put_contents($lastFailed, $json);
        file_put_contents($lastFailed . "-time.txt", date("c"));
        error_log($e->getTraceAsString());
        error_log("FAILED JSON: " . $json);
        if (isset($aino)) {
            $aino->failure(
                $ainoTimestamp,
                "Shiplog agent failed",
                "Parse",
                "timestamp",
                [],
                ["file" => basename($targetFile)]
            );
        }
        $output->error(400, "Failed to handle post correcly. Posted file data: '" . $json ."'. " . $e->getMessage());
    }
} else {
    if (isset($aino)) {
        $aino->failure(
            $ainoTimestamp,
            "Shiplog agent failed",
            "Fetch",
            "timestamp",
            [],
            []
        );
    }
    $output->error(400, "No data posted");
}

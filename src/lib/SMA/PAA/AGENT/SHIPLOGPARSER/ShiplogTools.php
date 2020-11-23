<?php
namespace SMA\PAA\AGENT\SHIPLOGPARSER;

use DateTime;

class ShiplogTools
{
    private function get(array $data, ...$keys)
    {
        $next = $data;
        foreach ($keys as $key) {
            if (is_array($next) && array_key_exists($key, $next)) {
                $next = $next[$key];
            } else {
                return null;
            }
        }
        return $next;
    }
    private function mapAreaToStateLocation($area, string $mappingString)
    {
        $mapping = [];
        $rows = explode(";", $mappingString);
        foreach ($rows as $row) {
            $tokens = explode(":", $row);
            if (sizeof($tokens) === 2) {
                $mapping[$tokens[1]] = $tokens[0];
            }
        }
        if (array_key_exists($area, $mapping)) {
            return $mapping[$area];
        }
        throw new \Exception("Can't resolve timestamp state location for area '" . $area . "'!");
    }
    public function floorToSecondsAccuracy(string $ts)
    {
        $validFormats = [DateTime::RFC3339_EXTENDED, DateTime::ATOM];
        $datetime = null;

        foreach ($validFormats as $validFormat) {
            $test = DateTime::createFromFormat($validFormat, $ts);
            if ($test !== false) {
                $datetime = $test;
            }
        }

        if (!isset($datetime)) {
            throw new \Exception("Can't resolve time '" . $ts . "'!");
        }
        return  $datetime->format(DateTime::ISO8601);
    }
    public function parse($data, string $mapping)
    {
        /**
         * "arrived": "2020-01-29T10:00:55.773Z",
         * "departure": {
         *    "departure": "2020-01-29T10:26:18.753Z",
         *    "reason": {
         *        "type": "exit"
         *    },
         *    "tentative": false
         * }
         */
        $departureReasonType = $this->get($data, 'portCall', 'departure', 'reason', 'type');

        if ($departureReasonType === "signalloss") {
            // ignore, this means that signal is lost and departure signal is send after some period of time
            // reconsider this later if it starts to make sense to trigger timestamp from it
            throw new ESignalloss("Singalloss message. Refusing to parse.");
        }
        $imo = $this->get($data, 'vesselMeta', 'imo');
        if ($imo === null) {
            $imo = 0;
            if ($this->get($data, 'portCall', 'vessel', 'aisClass') !== null) {
                $aisClass = $this->get($data, 'portCall', 'vessel', 'aisClass');

                if ($aisClass === "b") {
                    throw new EAisClassBWithoutImo("No IMO and AIS class B. Not parsing.");
                }
            }
        }
        $vesselName = $this->get($data, 'vesselMeta', 'name');
        $area = $this->get($data, 'area', 'name');
        $arrivelTime = $this->get($data, 'portCall', 'arrived');
        $departureTime = $this->get($data, 'portCall', 'departure', 'departure');
        $stateLocation = $this->mapAreaToStateLocation($area, $mapping);

        $payload = [];

        $payload["source"] = "shiplog";

        if (isset($area)) {
            $payload["location"] = $area;
        }

        if ($this->get($data, 'portCall', 'vessel', 'mmsi') !== null) {
            $payload["mmsi"] = $this->get($data, 'portCall', 'vessel', 'mmsi');
        }

        if ($this->get($data, 'vesselMeta', 'callsign') !== null) {
            $payload["call_sign"] = $this->get($data, 'vesselMeta', 'callsign');
        }

        if ($this->get($data, 'vesselMeta', 'draught') !== null) {
            $payload["vessel_draft"] = $this->get($data, 'vesselMeta', 'draught');
        }

        if ($stateLocation === "Berth") {
            $payload["berth_name"] = $area;
        }

        $payload["original_message"] = $data;

        return [
            "imo" => $imo,
            "vessel_name" => $vesselName,
            "state" =>
                ($departureReasonType ? "Departure" : "Arrival")
                . "_Vessel_"
                . $stateLocation,
            "time_type" => "Actual",
            "time" => $this->floorToSecondsAccuracy($departureReasonType ? $departureTime : $arrivelTime),
            "payload" => $payload
        ];
    }
    public function parsePlaceAndCoordinates(array $data)
    {
        $area = $this->get($data, "area", "name");
        $latitute = $this->get($data, "portCall", "vessel", "latitude");
        $longitude = $this->get($data, "portCall", "vessel", "longitude");
        $trueHeading = $this->get($data, "portCall", "vessel", "trueHeading");
        $navigationalStatus = $this->get($data, "portCall", "vessel", "navigationalStatus");
        $departureReason = $this->get($data, "portCall", "departure", "reason", "type");
        return [
            "latitude" => $latitute,
            "longitude" => $longitude,
            "area" => $area,
            "trueHeading" => $trueHeading,
            "navigationalStatus" => $navigationalStatus,
            "departureReason" => $departureReason
        ];
    }
}

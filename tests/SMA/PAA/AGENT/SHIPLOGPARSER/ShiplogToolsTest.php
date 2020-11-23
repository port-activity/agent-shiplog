<?php

namespace SMA\PAA\AGENT\SHIPLOGPARSER;

use PHPUnit\Framework\TestCase;

final class ShiplogToolsTest extends TestCase
{
    private function mapping()
    {
        $s = <<<EOF
Berth:1 Kemikalie kajen
Berth:10 Östra kajen
Berth:105 Bulk kajen
Berth:11 Östra kajen
Berth:12 Östra kajen
Berth:13-14 Östra kajen
Berth:16 Slig kajen
Berth:17 Container kajen
Berth:19 Container kajen
Berth:201 Olje kajen
Berth:22-23 Bulk/Container kajen
Berth:24-25 Bulk/Container kajen
Berth:27 Energi kajen
Berth:301 Granudden
Berth:302-303 Granudden
Berth:4 Sydvästra kajen
Berth:5 Sydvästra kajen
Berth:6 Sydvästra kajen
Berth:7 Södra kajen
Berth:8 Södra kajen
Berth:9 Östra kajen
TrafficArea:End of seapassage
PortArea:Hamnområde
AnchorageArea:Anchorage A
AnchorageArea:Anchorage B
AnchorageArea:Anchorage C
EOF;
        return implode(";", explode("\n", $s)); // note: on env we have it separated by ;
    }
    private function data($name)
    {
        return json_decode(file_get_contents(__DIR__ . "/" . $name), true);
    }
    private function result(string $name)
    {
        return file_get_contents(__DIR__ . "/" . $name . "-result.json");
    }
    private function saveResult(string $name, string $data)
    {
        return file_put_contents(__DIR__ . "/" . $name . "-result.json", $data);
    }
    private function parse(array $data)
    {


        $tools = new ShiplogTools();
        return json_encode($tools->parse($data, $this->mapping()), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    public function testFlooringTsAsSecondsAccurary()
    {
        $tools = new ShiplogTools();
        $this->assertEquals("2020-01-29T10:26:18+0000", $tools->floorToSecondsAccuracy("2020-01-29T10:26:18.753Z"));
    }
    /**
     * @expectedException Exception
     * @expectedExceptionMessage Can't resolve timestamp state location for area 'Aurajoki'!
     */
    public function testParsingUnmappedAreaFails(): void
    {
        $data = json_decode(file_get_contents(__DIR__ . "/shiplog-bad-area.json"), true);
        $parser = new ShiplogTools();
        $parser->parse($data, $this->mapping());
    }
    public function testParsingPortareaIncomingMessage(): void
    {
        $file = "shiplog-portarea-incoming.json";
        $data = $this->data($file);
        // $this->saveResult($file, $this->parse($data));
        $this->assertEquals($this->result($file), $this->parse($data));
    }

    public function testParsingBerthIncomingMessage(): void
    {
        $file = "shiplog-berth-incoming.json";
        $data = $this->data($file);
        // $this->saveResult($file, $this->parse($data));
        $this->assertEquals($this->result($file), $this->parse($data));
    }

    public function testParsingBerthOutgoingMessage(): void
    {
        $file = "shiplog-berth-outgoing.json";
        $data = $this->data($file);
        // $this->saveResult($file, $this->parse($data));
        $this->assertEquals($this->result($file), $this->parse($data));
    }

    public function testParsingAnchorageB(): void
    {
        $file = "anchorage-example.json";
        $data = $this->data($file);
        // $this->saveResult($file, $this->parse($data));
        $this->assertEquals($this->result($file), $this->parse($data));
    }

    public function testParsingNullImoMessage(): void
    {
        $file = "shiplog-null-imo.json";
        $data = $this->data($file);
        // $this->saveResult($file, $this->parse($data));
        $this->assertEquals($this->result($file), $this->parse($data));
    }

    /**
     * @expectedException SMA\PAA\AGENT\SHIPLOGPARSER\EAisClassBWithoutImo
     * @expectedExceptionMessage No IMO and AIS class B. Not parsing.
     */
    public function testParsingNullImoAisClassBMessage(): void
    {
        $file = "shiplog-null-imo-ais-class-b.json";
        $data = $this->data($file);
        $this->parse($data);
    }

    // This function is to help building up KML visulization over google maps
    // Later we can build someking visualisation directly to application with similar approach
    public function testGeneratingListOfPlaces()
    {
        $tools = new ShiplogTools();
        $files = scandir(__DIR__ . "/data/");
        $places = [];
        foreach ($files as $file) {
            if (preg_match("/.json$/", $file)) {
                $text = file_get_contents(__DIR__ . "/data/". $file);
                $text = iconv(mb_detect_encoding($text, mb_detect_order(), true), "UTF-8", $text);
                $data = json_decode($text, true, 512, JSON_UNESCAPED_UNICODE);
                file_put_contents(
                    __DIR__ . "/data/". $file,
                    json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                );
                $parsedData = false;
                try {
                    // departureReason
                    $parsedData = $tools->parsePlaceAndCoordinates($data);
                } catch (\Exception $e) {
                    // skip
                    // throw $e;
                }
                if ($parsedData && $parsedData["departureReason"] !== "signalloss") {
                    $places[] = $parsedData;
                }
            }
        }
        $template = file_get_contents(__DIR__ . "/placemark.template.xml");
        $xml = "";
        foreach ($places as $place) {
            $search = array_map(function ($key) {
                return '{' . $key . '}';
            }, array_keys($place));
            $xml .= str_replace($search, $place, $template) . "\n\n";
        };
        $header = '<?xml version="1.0" encoding="UTF-8"?>
        <kml xmlns="http://earth.google.com/kml/2.1">
        <Document>';
        $footer = '</Document></kml>';
        // note: refer this new file from map.html to see visualization
        // note: don't use same *.kml filename twice since google caches it (like forever)
        // file_put_contents(__DIR__ . "/cta-" . time() . ".kml", $header . $xml . $footer);
        $this->assertTrue(true);
    }
}

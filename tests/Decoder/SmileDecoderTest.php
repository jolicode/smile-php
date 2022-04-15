<?php

/*
 * This file is part of the Smile PHP project, a project by JoliCode.
 */

namespace Jolicode\SmilePhp\Decoder;

use Jolicode\SmilePhp\Decoder\SmileDecoder;
use PHPUnit\Framework\TestCase;

class SmileDecoderTest extends TestCase
{
    private SmileDecoder $decoder;

    protected function setUp(): void
    {
        $this->decoder = new SmileDecoder();
    }

    /**
     * @dataProvider provideFiles
     *
     * @covers SmileDecoder::decode
     * */
    public function testDecode(string $fileName)
    {
        $file = sprintf('%s%s%s', __DIR__, '/../../tests/data/', $fileName);

        $smile = file_get_contents($file . '.smile');
        $json = file_get_contents($file . '.json');
        $results = $this->decoder->decode($smile);

        $this->assertEquals(json_decode($json), $results);
    }

    public function provideFiles()
    {
        yield ['numbers-int-4k'];
        yield ['numbers-int-64k'];
        yield ['test1'];
        yield ['test2'];
        yield ['db100.xml'];
        yield ['json-org-sample1'];
        yield ['json-org-sample2'];
        yield ['json-org-sample3'];
        yield ['json-org-sample4'];
        yield ['json-org-sample5'];
        yield ['map-spain.xml'];
        yield ['ns-invoice100.xml'];
        yield ['ns-soap.xml'];
        yield ['unicode'];
        yield ['numbers-fp-4k'];
        yield ['numbers-fp-64k'];
        yield ['big-integer'];
    }
}

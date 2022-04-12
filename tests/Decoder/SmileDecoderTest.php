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
     * @dataProvider provideIntegers
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

    public function provideIntegers()
    {
        yield ['numbers-int-4k'];
        yield ['numbers-int-64k'];
        yield ['test1'];
        yield ['test2'];
        // Not working for now :(
        // yield [
        //     'smile' => __DIR__ . '/../../tests/data/numbers-fp-4k.smile',
        //     'json' => __DIR__ . '/../../tests/data/numbers-fp-4k.json',
        // ];
        // yield [
        //     'smile' => __DIR__ . '/../../tests/data/numbers-fp-64k.smile',
        //     'json' => __DIR__ . '/../../tests/data/numbers-fp-64k.json',
        // ];
        // yield [
        //     'smile' => __DIR__ . '/../../tests/data/test1.smile',
        //     'json' => __DIR__ . '/../../tests/data/test1.json',
        // ];
    }
}

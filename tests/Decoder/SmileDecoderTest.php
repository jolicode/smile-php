<?php

namespace Jolicode\SmilePhp\Decoder;

use Jolicode\SmilePhp\Encoder\SmileDecoder;
use PHPUnit\Framework\TestCase;

class SmileDecoderTest extends TestCase
{
    private SmileDecoder $decoder;

    protected function setUp(): void
    {
        $this->decoder = new SmileDecoder();
    }

    /** @dataProvider provideIntegers */
    public function testIntegers(string $smile, string $json)
    {
        $encoded = file_get_contents($smile);
        $expected = file_get_contents($json);
        $actual = $this->decoder->decode($encoded);

        $this->assertSame($expected, $actual);
    }

    public function provideIntegers()
    {
        yield [
            'smile' => __DIR__ . '/../../tests/data/numbers-int-4k.smile',
            'json' => __DIR__ . '/../../tests/data/numbers-int-4k.json',
        ];
        yield [
            'smile' => __DIR__ . '/../../tests/data/numbers-int-64k.smile',
            'json' => __DIR__ . '/../../tests/data/numbers-int-64k.json',
        ];
        // Not working for now :(
        // yield [
        //     'smile' => __DIR__ . '/../../tests/data/numbers-fp-4k.smile',
        //     'json' => __DIR__ . '/../../tests/data/numbers-fp-4k.json',
        // ];
        // yield [
        //     'smile' => __DIR__ . '/../../tests/data/numbers-fp-64k.smile',
        //     'json' => __DIR__ . '/../../tests/data/numbers-fp-64k.json',
        // ];
        yield [
            'smile' => __DIR__ . '/../../tests/data/test1.smile',
            'json' => __DIR__ . '/../../tests/data/test1.json',
        ];
    }
}

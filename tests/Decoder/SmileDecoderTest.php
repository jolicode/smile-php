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

    public function testIntegers()
    {
        $smile = file_get_contents(__DIR__ . '/../../tests/data/numbers-int-4k.smile');
        $expected = file_get_contents(__DIR__ . '/../../tests/data/numbers-int-4k.json');
        $actual = $this->decoder->decode($smile);

        $this->assertSame($expected, $actual);
    }
}

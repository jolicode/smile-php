<?php

namespace Jolicode\SmilePhp\Encoder;

class EncodingResult
{
    public function __construct(
        public readonly mixed $data,
        public readonly bool $shouldBeSkipped = false
    ) {
    }
}

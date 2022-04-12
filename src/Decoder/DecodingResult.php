<?php

namespace Jolicode\SmilePhp\Decoder;

class DecodingResult
{
    public mixed $data;
    public bool $shouldBeSkipped;

    public function __construct(mixed $data, bool $shouldBeSkipped = false)
    {
        $this->data = $data;
        $this->shouldBeSkipped = $shouldBeSkipped;
    }
}

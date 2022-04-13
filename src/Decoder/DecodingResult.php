<?php

/*
 * This file is part of the Smile PHP project, a project by JoliCode.
 */

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

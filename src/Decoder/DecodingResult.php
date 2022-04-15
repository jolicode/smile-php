<?php

/*
 * This file is part of the Smile PHP project, a project by JoliCode.
 */

namespace Jolicode\SmilePhp\Decoder;

class DecodingResult
{
    public function __construct(
        public readonly mixed $data,
        public readonly bool $shouldBeSkipped = false
    ) {
    }
}

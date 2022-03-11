<?php

namespace Jolicode\SmilePhp\Enum;

enum Byte: int
{
    case NULL = 0;

    // LITTERALS
    case LITTERAL_EMPTY_STRING = 32;
    case LITTERAL_NULL = 33;
    case LITTERAL_FALSE = 34;
    case LITTERAL_TRUE = 35;

    case PREFIX_INTEGER = 36;
    case PREFIX_FP = 40;
}

<?php

/*
 * This file is part of the Smile PHP project, a project by JoliCode.
 */

namespace Jolicode\SmilePhp\Enum;

enum Bytes: int
{
    public const NULL_BYTE = 0;

    // THRESHOLDS
    public const THRESHOLD_SIMPLE_LITERALS = 32;
    public const THRESHOLD_INTEGERS = 36;
    public const THRESHOLD_FLOATS = 40;
    public const THRESHOLD_RESERVED = 43;
    public const THRESHOLD_LONG_SHARED_KEY = 48;
    public const THRESHOLD_TINY_ASCII = 64;
    public const THRESHOLD_SMALL_ASCII = 96;
    public const THRESHOLD_TINY_UNICODE = 128;
    public const THRESHOLD_SMALL_UNICODE = 160;
    public const THRESHOLD_SMALL_INT = 192;
    public const THRESHOLD_LONG_ASCII = 224;
    public const THRESHOLD_LONG_UNICODE = 228;
    public const THRESHOLD_7BITS = 232;
    public const THRESHOLD_LONG_SHARED_STRING = 236;
    public const THRESHOLD_HEADER_BIT_VERSION = 240;
    public const THRESHOLD_STRUCTURE_LITERALS = 248;

    // SIMPLE LITERALS
    public const LITERAL_EMPTY_STRING = 32;
    public const LITERAL_NULL = 33;
    public const LITERAL_FALSE = 34;
    public const LITERAL_TRUE = 35;

    // INTS
    public const INT_32 = 36;
    public const INT_64 = 37;
    public const INT_BIG = 38;

    // FLOATS
    public const FLOAT_32 = 40;
    public const FLOAT_64 = 41;
    public const BIG_DECIMAL = 42;

    // KEYS
    public const KEY_LONG_KEY_NAME = 52;
    public const KEY_FORBIDDEN_KEY = 58;
    public const KEY_SHORT_SHARED_REFERENCE = 64;
    public const KEY_SHORT_ASCII = 128;
    public const KEY_SHORT_UNICODE = 192;

    // STRUCTURE LITERALS
    public const LITERAL_ARRAY_START = 248;
    public const LITERAL_ARRAY_END = 249;
    public const LITERAL_OBJECT_START = 250;
    public const LITERAL_OBJECT_END = 251;

    // END MARKERS
    public const MARKER_END_OF_STRING = 252;
    public const MARKER_END_OF_CONTENT = 254;

    // Sad, alone, no friends raw binary data :'(
    public const RAW_BINARY = 253;
}

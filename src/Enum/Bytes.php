<?php

namespace Jolicode\SmilePhp\Enum;

enum Bytes: int
{
    const NULL_BYTE = 0;

    // THRESHOLDS
    const THRESHOLD_SIMPLE_LITERALS = 32;
    const THRESHOLD_INTEGERS = 36;
    const THRESHOLD_FLOATS = 40;
    const THRESHOLD_RESERVED = 43;
    const THRESHOLD_LONG_SHARED_KEY = 48;
    const THRESHOLD_TINY_ASCII = 64;
    const THRESHOLD_SMALL_ASCII = 96;
    const THRESHOLD_TINY_UNICODE = 128;
    const THRESHOLD_SMALL_UNICODE = 160;
    const THRESHOLD_SMALL_INT = 192;
    const THRESHOLD_LONG_ASCII = 224;
    const THRESHOLD_LONG_UNICODE = 228;
    const THRESHOLD_7BITS = 232;
    const THRESHOLD_LONG_SHARED_STRING = 236;
    const THRESHOLD_HEADER_BIT_VERSION = 240;
    const THRESHOLD_STRUCTURE_LITERALS = 248;

    // SIMPLE LITERALS
    const LITERAL_EMPTY_STRING = 32;
    const LITERAL_NULL = 33;
    const LITERAL_FALSE = 34;
    const LITERAL_TRUE = 35;

    // FLOATS
    const FLOAT_32 = 40;
    const FLOAT_64 = 41;
    const BIG_DECIMAL = 42;

    // KEYS
    const KEY_LONG_KEY_NAME = 52;
    const KEY_FORBIDDEN_KEY = 58;
    const KEY_SHORT_SHARED_REFERENCE = 64;
    const KEY_SHORT_ASCII = 128;
    const KEY_SHORT_UNICODE = 192;

    // STRUCTURE LITERALS
    const LITERAL_ARRAY_START = 248;
    const LITERAL_ARRAY_END = 249;
    const LITERAL_OBJECT_START = 250;
    const LITERAL_OBJECT_END = 251;

    // END MARKERS
    const MARKER_END_OF_STRING = 252;
    const MARKER_END_OF_CONTENT = 254;

    // Sad, alone, no friends raw binary data :'(
    const RAW_BINARY = 253;
}

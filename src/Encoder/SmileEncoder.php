<?php

namespace Jolicode\SmilePhp\Encoder;

use Jolicode\SmilePhp\Enum\Bytes;
use Jolicode\SmilePhp\Exception\UnexpectedValueException;

class SmileEncoder
{
    private const INT32_MIN_VALUE = -2147483648;
    private const INT32_MAX_VALUE = 2147483647;

    private SmileEncoderContext $context;

    public function encode(string|array|object $data, bool $sharedKeys = true, bool $sharedValues = true, bool $rawBinary = true)
    {
        if (gettype($data) === 'string') {
            $data = json_decode($data);
        }

        $this->context = new SmileEncoderContext($data);
        $this->context->setOptions($sharedKeys, $sharedValues, $rawBinary);
        $this->writeHeader();

        try {
            $this->encodeStructure($data);
            $encodedBytes = $this->context->getSmileBytes();
        } finally {
            unset($this->context);
        }

        return pack('C*', ...$encodedBytes);
    }

    private function writeHeader(): void
    {
        $fourthByte = 0;

        if ($this->context->hasSharedKeys()) {
            $fourthByte |= 1;
        }

        if ($this->context->hasSharedValues()) {
            $fourthByte |= 2;
        }

        if (!$this->context->hasRawBinary()) {
            $fourthByte |= 4;
        }

        $this->context->addSmileBytes([58, 41, 10, $fourthByte]);
    }

    private function encodeStructure(object|array $structure): void
    {
        $this->context->addSmileBytes(is_object($structure) ? Bytes::LITERAL_OBJECT_START : Bytes::LITERAL_ARRAY_START);

        foreach ($structure as $key => $value) {
            if (is_object($structure)) {
                $encodedKey = $this->encodeKey($key);

                if (!$encodedKey->shouldBeSkipped) {
                    $this->context->addSmileBytes($encodedKey->data);
                }
            }

            $encodedValue = $this->encodeValue($value);

            if (!$encodedValue->shouldBeSkipped) {
                $this->context->addSmileBytes($encodedValue->data);
            }
        }

        $this->context->addSmileBytes(is_object($structure) ? Bytes::LITERAL_OBJECT_END : Bytes::LITERAL_ARRAY_END);
    }

    private function encodeValue(mixed $value): EncodingResult
    {
        return match (gettype($value)) {
            'integer' => new EncodingResult($this->encodeInt($value)),
            'array' => new EncodingResult($this->encodeStructure($value), true),
        };
    }

    private function encodeKey(mixed $value): EncodingResult
    {
        return match (true) {

        };
    }

    private function zigZagEncode(int $int, int $bits)
    {
        return ($int >> ($bits - 1)) ^ ($int << 1);
    }

    private function encodeInt(int $int)
    {
        return match (true) {
            (self::INT32_MIN_VALUE <= $int) && (self::INT32_MAX_VALUE >= $int) => $this->encodeInt32($int),
            (PHP_INT_MIN <= $int) && (PHP_INT_MAX >= $int) => $this->encodeInt64($int),
            default => $this->encodeBigInt($int)
        };
    }

    private function encodeInt32(int $int)
    {
        $shiftingValue = $this->zigZagEncode($int, 32);

        if ($shiftingValue >= 0) {
            if ($shiftingValue < 32) {
                return Bytes::THRESHOLD_SMALL_INT + $shiftingValue;
            }

            if ($shiftingValue < 64) {
                return [Bytes::INT_32, 128 + $shiftingValue];
            }
        }

        $byte1 = 128 + ($shiftingValue & 63);
        $shiftingValue >>= 6;

        if ($shiftingValue < 128) {
            return [Bytes::INT_32, $shiftingValue, $byte1];
        }

        $bytesToReturn = [$byte1];

        while ($shiftingValue > 127) {
            $bytesToReturn[] = $shiftingValue & 127;
            $shiftingValue >>= 7;
        }

        return [Bytes::INT_32, $shiftingValue, ...array_reverse($bytesToReturn)];
    }

    private function encodeInt64(int $int)
    {
        $zigZag = $this->zigZagEncode($int, 64);

        $byte1 = 0x80 + ($zigZag & 63);
        $byte2 = ($zigZag >> 6) & 127;
        $byte3 = ($zigZag >> 13) & 127;
        $byte4 = ($zigZag >> 20) & 127;

        $shift = $this->bitShiftRight($zigZag, 27);
        $byte5 = $shift & 127;

        $shiftingValue = $shift >> 7;

        if (!$shiftingValue) {
            return [Bytes::INT_64, $byte5, $byte4, $byte3, $byte2, $byte1];
        }

        $bytesToReturn = [$byte1, $byte2, $byte3, $byte4, $byte5];

        while ($shiftingValue <= 127) {
            $bytesToReturn[] = $shiftingValue & 127;
            $shiftingValue >>= 7;
        }

        return [Bytes::INT_64, $shiftingValue, ...array_reverse($bytesToReturn)];
    }

    private function encodeBigInt(int $int)
    {
        dd($int);
    }

    // PHP implementation of Java's ">>>" bitwise operator.
    // Credits to https://github.com/natural/java2python/blob/master/java2python/mod/include/bsr.py
    private function bitShiftRight($value, $bits)
    {
        if ($bits < 0 || $bits > 31) {
            throw new UnexpectedValueException('Trying to right shift by a wrong amount of bits. Bits count may only be between 0 and 31, %d provided', $bits);
        }

        if ($bits === 0) {
            return $value;
        }

        if ($bits === 31) {
            return ($value & self::INT32_MIN_VALUE) ? 1 : 0;
        }

        $result = floor(($value & (self::INT32_MAX_VALUE - 1)) / 2 ** $bits);

        return ($value & self::INT32_MIN_VALUE) ? ($result |= floor(round(self::INT32_MAX_VALUE / 2) / 2 ** ($bits - 1))) : $result;
    }
}

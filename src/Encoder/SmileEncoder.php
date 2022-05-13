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
            'array' => new EncodingResult($this->encodeStructure($value), true),
        };
    }

    private function encodeKey(mixed $value): EncodingResult
    {
        return match (true) {

        };
    }
}

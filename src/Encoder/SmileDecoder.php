<?php

/*
 * This file is part of the Smile PHP project, a project by JoliCode.
 */

namespace Jolicode\SmilePhp\Encoder;

use Jolicode\SmilePhp\Enum\Bytes;
use Jolicode\SmilePhp\Exception\UnexpectedValueException;

class SmileDecoder
{
    private bool $isFullyDecoded = false;
    private bool $isDecodingKey = false;

    /** @var int[] $bytesArray */
    private array $bytesArray = [];
    private string $decodedString = "";

    private int $index = 1; // index is 1 because unpack method returns a 1 indexed array

    private int $depthLevel = 0; // Used to know how far we are in a nested structure

    /** @var bool[] $currentStructure */
    private array $currentStructure = [];

    /** @var string[] $sharedKeyStrings */
    private array $sharedKeyStrings = [];

    /** @var string[] $sharedValueStrings */
    private array $sharedValueStrings = [];

    public function decode(string $smileData): string
    {
        $this->bytesArray = unpack('C*', $smileData);

        $this->decodeHead();

        while (!$this->isFullyDecoded) {
            if ($this->isDecodingKey) {
                $this->decodeKey();
            } else {
                $this->decodeBody();
            }
        }

        return $this->decodedString;
    }

    private function getNextByte(): ?int
    {
        if ($this->index > count($this->bytesArray)) {
            $this->isFullyDecoded = true;

            return null;
        }

        $byte = $this->bytesArray[$this->index];

        ++$this->index;

        return $byte;
    }

    /**
     * @return int[]
     */
    private function getMultipleBytes(int $amount): array
    {
        $bytes = [];

        foreach (range(1, $amount) as $byte) {
            $bytes[] = $this->getNextByte();
        }

        return $bytes;
    }

    private function decodeInt(): int
    {
        $decoded = 0;
        $nextBytes = array_slice($this->bytesArray, $this->index);

        foreach ($nextBytes as $byte) {
            ++$this->index;

            if ($byte & 128) {
                $decoded <<= 6;
                $decoded |= ($byte & 63);
                break;
            }

            $decoded <<= 7;
            $decoded |= $byte;
        }

        return $decoded;
    }

    private function zigzagDecode(int $encoded): int
    {
        return ($encoded >> 1) ^ (-($encoded - 1));
    }

    private function copyStringValue(int $length): string
    {
        $value = $this->escape($this->index + $length);

        $this->sharedValueStrings[] = $value;
        $this->index += $length;

        return $value;
    }

    private function escape(int $index): string
    {
        $value = pack('C*', array_slice($this->bytesArray, $this->index, $index));
        $value = str_replace("\n", '\n', $value);

        return str_replace('"', '\"', $value);
    }

    private function decodeHead(): void
    {
        $headerBytes = $this->getMultipleBytes(3);
        $header = '';

        if ([58, 41, 10] !== $headerBytes) { // Smile header should be ":)\n", so the decimal bytes must be 58, 41, 10.
            foreach ($headerBytes as $byte) {
                $header .= chr($byte);
            }

            throw new UnexpectedValueException(sprintf('Error while decoding the smile header. Smile header should be ":)\n" but "%s" was found.', $header));
        }
    }

    private function decodeBody(): void
    {
        $byte = $this->getNextByte();

        if (null === $byte) {
            return;
        }

        if ($this->depthLevel) {
            if ($this->currentStructure[$this->depthLevel]) {
                if ($byte !== Bytes::LITERAL_ARRAY_END) {
                    $this->decodedString .= ',';
                }
            }
        }

        $this->decodedString .= match (true) {
            $byte === Bytes::NULL_BYTE => null,
            $byte < Bytes::THRESHOLD_SIMPLE_LITERALS => $this->writeSharedString(), // 1 > 31 values are string shared values.
            $byte < Bytes::THRESHOLD_INTEGERS => $this->writeSimpleLitteral($byte), // 32 > 35 are simple litterals.
            $byte < Bytes::THRESHOLD_FLOATS => $this->writeInteger($byte), // 36 > 39 are integers.
            $byte < Bytes::THRESHOLD_RESERVED => $this->writeFloat($byte), // 40 > 42 are floats.
            $byte < Bytes::THRESHOLD_TINY_ASCII => '', // 43 > 63 is reserved for future use.
            $byte < Bytes::THRESHOLD_SMALL_ASCII => $this->writeTinyAsciiOrUnicode($byte), // 64 > 96 are tiny ASCII.
            $byte < Bytes::THRESHOLD_TINY_UNICODE => $this->writeSmallAsciiOrUnicode($byte), // 97 > 127 are small ASCII.
            $byte < Bytes::THRESHOLD_SMALL_UNICODE => $this->writeTinyAsciiOrUnicode($byte), // 128 > 159 are tiny Unicode.
            $byte < Bytes::THRESHOLD_SMALL_INT => $this->writeSmallAsciiOrUnicode($byte), // 160 > 191 are small Unicode.
            $byte < Bytes::THRESHOLD_LONG_ASCII => $this->writeSmallInt($byte), // 192 > 223 are small int.
            $byte < Bytes::THRESHOLD_LONG_UNICODE => $this->writeLongASCII(),  // 224 > 227 are long ASCII.
            $byte < Bytes::THRESHOLD_7BITS => $this->writeLongUnicode(), // 228 > 231 are long unicode.
            $byte < Bytes::THRESHOLD_LONG_SHARED_STRING => $this->write7BitsEncoded(), // 232 > 235 is for 7 bits encoded values.
            $byte < Bytes::THRESHOLD_HEADER_BIT_VERSION => $this->writeLongSharedString(), // 235 > 239 is for long strings shared values.
            $byte < Bytes::THRESHOLD_STRUCTURE_LITERALS => '', // 240 > 247 is reserved for future use.
            $byte === Bytes::LITERAL_ARRAY_START => $this->writeArrayStart(), // 248 is array start
            $byte === Bytes::LITERAL_ARRAY_END => $this->writeArrayEnd(), // 249 is array end
            $byte === Bytes::LITERAL_OBJECT_START => $this->writeObjectStart(), // 250 is object start
            $byte === Bytes::LITERAL_OBJECT_END => $this->writeObjectEnd(), // 251 is object end
            $byte === Bytes::MARKER_END_OF_STRING => throw new UnexpectedValueException('An end of string byte was found while decoding the body.'), // 252 is end of string marker
            $byte === Bytes::RAW_BINARY => '', // TODO: implement Raw Binary
            $byte === Bytes::MARKER_END_OF_CONTENT => $this->endBodyDecoding(), // 254 is end of content marker
            default => throw new UnexpectedValueException(sprintf('Given byte does\'t exist. Given byte has value %s but decimal bytes range from 0 to 254.', $byte))
        };

        // Not sure at all about this thing, looks suspicious (and it is currently buggy so... get commented)
        // if (!$this->currentStructure[$this->depthLevel]) {
        //     $this->isDecodingKey = true;
        // }
    }

    private function decodeKey(): void
    {
        $byte = $this->getNextByte();

        if (null === $byte) {
            return;
        }

        if ($byte !== Bytes::LITERAL_OBJECT_END) {
            $this->decodedString .= ',';
        }

        $this->decodedString .= match (true) {
            $byte < Bytes::THRESHOLD_SIMPLE_LITERALS => '', // 0 > 31 are reserved
            $byte === Bytes::LITERAL_EMPTY_STRING => '""',
            $byte < Bytes::THRESHOLD_LONG_SHARED_KEY => '', // 33 > 37 are reserved
            $byte < Bytes::KEY_LONG_KEY_NAME => '', // TODO: implement long shared key references
            $byte === Bytes::KEY_LONG_KEY_NAME => '', // TODO: implement long key name
            $byte < Bytes::KEY_FORBIDDEN_KEY => '', // 53 > 57 are reserved
            $byte === Bytes::KEY_FORBIDDEN_KEY => throw new UnexpectedValueException('Byte of decimal value 58 was found while decoding a key but this byte is forbidden for keys.'),
            $byte < Bytes::KEY_SHORT_SHARED_REFERENCE => '', // 59 > 63 are reserved
            $byte < Bytes::KEY_SHORT_ASCII => $this->writeShortSharedKey(), // 64 > 127 are short shared keys
            $byte < Bytes::KEY_SHORT_UNICODE => $this->writeShortAsciiKey($byte), // 128 > 191 are short ASCII
            $byte < Bytes::LITERAL_ARRAY_START => $this->writeShortUnicodeKey($byte), // 192 > 247 are short Unicodes
            $byte < Bytes::LITERAL_ARRAY_END => '', // 248 > 250 are reserved
            $byte === Bytes::LITERAL_OBJECT_END => $this->writeObjectEnd(),
            default => '', // We do nothing for 251 > 254
        };
    }

    private function writeSharedString(): string
    {
        if (array_key_exists($this->bytesArray[$this->index - 1] - 1, $this->sharedValueStrings)) {
            return sprintf(
                '"%s"',
                $this->sharedValueStrings[$this->bytesArray[$this->index - 1] - 1]
            );
        }

        return '';
    }

    private function writeSimpleLitteral(int $byte): string
    {
        return match ($byte) {
            Bytes::LITERAL_EMPTY_STRING => '""',
            Bytes::LITERAL_NULL => 'null',
            Bytes::LITERAL_FALSE => 'false',
            Bytes::LITERAL_TRUE => 'true'
        };
    }

    private function writeInteger(int $byte): ?string
    {
        return match ($length = $byte & 3) {
            $length < 2 => $this->zigzagDecode($this->decodeInt()),
            $length === 2 => null, // TODO : implement BigIntegers
            default => null, // Reserved for future use
        };
    }

    private function writeFloat(int $byte): string
    {
        if ($byte === Bytes::FLOAT_32) {
            $floatBytes = $this->getMultipleBytes(5);
            $byte1 = $floatBytes[0];
            $byte2 = $floatBytes[1] << 7;
            $byte3 = $floatBytes[2] << 7 << 7;
            $byte4 = $floatBytes[3] << 7 << 7 << 7;
            $byte5 = $floatBytes[4] << 7 << 7 << 7 << 7;
            $byte = ($byte1 | $byte2 | $byte3 | $byte4 | $byte5);

            return ''; // Skipping this for now. Will see when we have an encoder.
        } elseif ($byte === Bytes::FLOAT_64) {
            $floatBytes = $this->getMultipleBytes(9);
            $byte1 = $floatBytes[0];
            $byte2 = $floatBytes[1] << 7;
            $byte3 = $floatBytes[2] << 7 << 7;
            $byte4 = $floatBytes[3] << 7 << 7 << 7;
            $byte5 = $floatBytes[4] << 7 << 7 << 7 << 7;
            $byte6 = $floatBytes[4] << 7 << 7 << 7 << 7 << 7;
            $byte7 = $floatBytes[4] << 7 << 7 << 7 << 7 << 7 << 7;
            $byte8 = $floatBytes[4] << 7 << 7 << 7 << 7 << 7 << 7 << 7;
            $byte9 = $floatBytes[4] << 7 << 7 << 7 << 7 << 7 << 7 << 7 << 7;
            $byte = ($byte1 | $byte2 | $byte3 | $byte4 | $byte5 | $byte6 | $byte7 | $byte8 | $byte9);

            return ''; // Skipping this for now. Will see when we have an encoder.
        } elseif (Bytes::BIG_DECIMAL) {
            return ''; // TODO : implement BigDecimals
        }
    }

    private function writeTinyAsciiOrUnicode(int $byte): string
    {
        $length = ($byte & 31) + 1;

        return sprintf('"%s"', $this->copyStringValue($length));
    }

    private function writeSmallAsciiOrUnicode(int $byte): string
    {
        $length = ($byte & 31) + 33;

        return sprintf('"%s"', $this->copyStringValue($length));
    }

    private function writeSmallInt(int $byte): int
    {
        $zigzagDecoded = $this->zigzagDecode($byte & 31);

        return $zigzagDecoded;
    }

    private function writeLongASCII(): string
    {
        $index = array_search(252, array_slice($this->bytesArray, $this->index));
        $escaped = $this->escape($index);

        $this->index = $index + 1;

        return sprintf('"%s"', $escaped);
    }

    private function writeLongUnicode(): string
    {
        return ''; // TODO: Implement long unicode
    }

    private function write7BitsEncoded(): string
    {
        return ''; // TODO: Implement 7 bits encoding
    }

    private function writeLongSharedString(): string
    {
        return ''; // TODO: Implement long shared string
    }

    private function writeArrayStart(): string
    {
        ++$this->depthLevel;
        $this->currentStructure[$this->depthLevel] = true;

        return '[';
    }

    private function writeArrayEnd(): string
    {
        --$this->depthLevel;

        return ']';
    }

    private function writeObjectStart(): string
    {
        ++$this->depthLevel;
        $this->currentStructure[$this->depthLevel] = false;

        $this->isDecodingKey = true;

        return '{';
    }

    private function writeObjectEnd(): string
    {
        --$this->depthLevel;
        $this->isDecodingKey = array_key_exists($this->depthLevel, $this->currentStructure);

        return '}';
    }

    private function endBodyDecoding(): ?string
    {
        $this->isFullyDecoded = true;

        return null;
    }

    private function writeShortSharedKey(): string
    {
        if (array_key_exists($this->bytesArray[$this->index - 1] - 64, $this->sharedKeyStrings)) {
            return sprintf(
                '"%s"',
                $this->sharedKeyStrings[$this->bytesArray[$this->index - 1] - 64]
            );
        }

        return '';
    }

    private function writeShortAsciiKey(int $byte): string
    {
        $length = ($byte & 31) + 1;
        $key = $this->escape($this->index + $length);

        $this->sharedKeyStrings[] = $key;
        $this->index += $length;

        return sprintf('"%s"', $key);
    }

    private function writeShortUnicodeKey(int $byte): string
    {
        $length = ($byte - 192) + 2;
        $key = $this->escape($this->index + $length);

        $this->sharedKeyStrings[] = $key;
        $this->index += $length;

        return sprintf('"%s"', $key);
    }
}

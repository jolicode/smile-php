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

    /** @var int[] */
    private array $bytesArray = [];
    private string $outputFile = __DIR__ . '/../../files/decode/output';

    private int $index = 1; // index is 1 because unpack method returns a 1 indexed array

    private int $depthLevel = 0; // Used to know how far we are in a nested structure

    /** @var bool[] */
    private array $currentStructure = [];

    /** @var string[] */
    private array $sharedKeyStrings = [];

    /** @var string[] */
    private array $sharedValueStrings = [];

    public function decode(string $smileData): string
    {
        file_put_contents($this->outputFile, null);
        $this->bytesArray = unpack('C*', $smileData);

        $this->decodeHead();

        while (!$this->isFullyDecoded) {
            if ($this->isDecodingKey) {
                $this->decodeKey();
            } else {
                $this->decodeBody();
            }
        }

        return file_get_contents($this->outputFile);
    }

    private function output(mixed $data): void
    {
        file_put_contents($this->outputFile, $data, \FILE_APPEND);
    }

    private function getNextByte(): ?int
    {
        if ($this->index + 1 > \count($this->bytesArray)) {
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
        $result = 0;

        while (true) {
            $byte = $this->getNextByte();

            if (null === $byte) {
                throw new UnexpectedValueException('Not enough bytes left to decode an integer.');
            }

            if ($byte & 128) {
                $result <<= 6;
                $result |= ($byte & 63);

                break;
            }

            $result <<= 7;
            $result |= $byte;
        }

        return $result;
    }

    private function decodeBigInt(): int
    {
        $int = $this->decodeInt();

        $bytesAmount = round((($int * 8) / 7));

        $binaryString = '';

        foreach (range(1, $bytesAmount) as $index) {
            $binaryString .= sprintf('%07b', $this->bytesArray[$this->index + $index]);

            if ($index === $bytesAmount - 1) {
                $trailing = \strlen($binaryString) % 8;
                $binaryString = substr($binaryString, 0, \strlen($binaryString) - $trailing);
            }
        }

        $this->index += $bytesAmount;

        return bindec($binaryString);
    }

    private function decodeFloat(int $bytesAmount): int
    {
        $intBits = $this->getNextByte();

        foreach (range(1, $bytesAmount) as $index) {
            $intBits = ($intBits << 7) + $this->getNextByte();
        }

        return $intBits;
    }

    private function zigZagDecode(int $int): int
    {
        return ($int >> 1) ^ -($int & 1);
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
        return pack('C*', ...\array_slice($this->bytesArray, $this->index, $index));
    }

    private function decodeHead(): void
    {
        $headerBytes = $this->getMultipleBytes(3);
        $header = '';

        if ([58, 41, 10] !== $headerBytes) { // Smile header should be ":)\n", so the decimal bytes must be 58, 41, 10.
            foreach ($headerBytes as $byte) {
                $header .= \chr($byte);
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
                if (Bytes::LITERAL_ARRAY_END !== $byte) {
                    $this->output(',');
                }
            }
        }

        $this->output(match (true) {
            Bytes::NULL_BYTE === $byte => null,
            $byte < Bytes::THRESHOLD_SIMPLE_LITERALS => $this->writeSharedString($byte), // 1 > 31 values are string shared values.
            $byte < Bytes::THRESHOLD_INTEGERS => $this->writeSimpleLitteral($byte), // 32 > 35 are simple litterals.
            $byte < Bytes::THRESHOLD_FLOATS => $this->writeInteger($byte), // 36 > 39 are integers.
            $byte < Bytes::THRESHOLD_RESERVED => $this->writeFloat($byte), // 40 > 42 are floats.
            $byte < Bytes::THRESHOLD_TINY_ASCII => null, // 43 > 63 is reserved for future use.
            $byte < Bytes::THRESHOLD_SMALL_ASCII => $this->writeTinyAsciiOrUnicode($byte), // 64 > 96 are tiny ASCII.
            $byte < Bytes::THRESHOLD_TINY_UNICODE => $this->writeSmallAsciiOrUnicode($byte), // 97 > 127 are small ASCII.
            $byte < Bytes::THRESHOLD_SMALL_UNICODE => $this->writeTinyAsciiOrUnicode($byte), // 128 > 159 are tiny Unicode.
            $byte < Bytes::THRESHOLD_SMALL_INT => $this->writeSmallAsciiOrUnicode($byte), // 160 > 191 are small Unicode.
            $byte < Bytes::THRESHOLD_LONG_ASCII => $this->writeSmallInt($byte), // 192 > 223 are small int.
            $byte < Bytes::THRESHOLD_LONG_UNICODE => $this->writeLongASCII(),  // 224 > 227 are long ASCII.
            $byte < Bytes::THRESHOLD_7BITS => $this->writeLongUnicode(), // 228 > 231 are long unicode.
            $byte < Bytes::THRESHOLD_LONG_SHARED_STRING => $this->write7BitsEncoded(), // 232 > 235 is for 7 bits encoded values.
            $byte < Bytes::THRESHOLD_HEADER_BIT_VERSION => $this->writeLongSharedString(), // 235 > 239 is for long strings shared values.
            $byte < Bytes::THRESHOLD_STRUCTURE_LITERALS => null, // 240 > 247 is reserved for future use.
            Bytes::LITERAL_ARRAY_START === $byte => $this->writeArrayStart(), // 248 is array start
            Bytes::LITERAL_ARRAY_END === $byte => $this->writeArrayEnd(), // 249 is array end
            Bytes::LITERAL_OBJECT_START === $byte => $this->writeObjectStart(), // 250 is object start
            Bytes::LITERAL_OBJECT_END === $byte => $this->writeObjectEnd(), // 251 is object end
            Bytes::MARKER_END_OF_STRING === $byte => throw new UnexpectedValueException('An end of string byte was found while decoding the body.'), // 252 is end of string marker
            Bytes::RAW_BINARY === $byte => null, // TODO: implement Raw Binary
            Bytes::MARKER_END_OF_CONTENT === $byte => $this->endBodyDecoding(), // 254 is end of content marker
            default => throw new UnexpectedValueException(sprintf('Given byte does\'t exist. Given byte has value %d but decimal bytes range from 0 to 254.', $byte))
        });

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

        if (Bytes::LITERAL_OBJECT_END !== $byte) {
            $this->output(',');
        }

        $this->output(match (true) {
            $byte < Bytes::THRESHOLD_SIMPLE_LITERALS => null, // 0 > 31 are reserved
            Bytes::LITERAL_EMPTY_STRING === $byte => '""',
            $byte < Bytes::THRESHOLD_LONG_SHARED_KEY => null, // 33 > 37 are reserved
            $byte < Bytes::KEY_LONG_KEY_NAME => null, // TODO: implement long shared key references
            Bytes::KEY_LONG_KEY_NAME === $byte => null, // TODO: implement long key name
            $byte < Bytes::KEY_FORBIDDEN_KEY => null, // 53 > 57 are reserved
            Bytes::KEY_FORBIDDEN_KEY === $byte => throw new UnexpectedValueException('Byte of decimal value 58 was found while decoding a key but this byte is forbidden for keys.'),
            $byte < Bytes::KEY_SHORT_SHARED_REFERENCE => null, // 59 > 63 are reserved
            $byte < Bytes::KEY_SHORT_ASCII => $this->writeShortSharedKey(), // 64 > 127 are short shared keys
            $byte < Bytes::KEY_SHORT_UNICODE => $this->writeShortAsciiKey($byte), // 128 > 191 are short ASCII
            $byte < Bytes::LITERAL_ARRAY_START => $this->writeShortUnicodeKey($byte), // 192 > 247 are short Unicodes
            $byte < Bytes::LITERAL_ARRAY_END => null, // 248 > 250 are reserved
            Bytes::LITERAL_OBJECT_END === $byte => $this->writeObjectEnd(),
            default => null, // We do nothing for 251 > 254
        });
    }

    private function writeSharedString(int $byte): string
    {
        if (\array_key_exists($byte & 31, $this->sharedValueStrings)) {
            return sprintf(
                '"%s"',
                $this->sharedValueStrings[$byte & 31]
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
        $result = match ($byte) {
            Bytes::INT_32 => $this->zigZagDecode($this->decodeInt()),
            Bytes::INT_64 => $this->zigZagDecode($this->decodeInt()),
            Bytes::INT_BIG => $this->decodeBigInt(),
        };

        return $result;
    }

    private function writeFloat(int $byte): string
    {
        return match ($byte) {
            Bytes::FLOAT_32 => $this->decodeFloat(5),
            Bytes::FLOAT_64 => $this->decodeFloat(10),
            Bytes::BIG_DECIMAL => '', // TODO: implement big decimals
        };
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
        $zigZagDecoded = $this->zigZagDecode($byte & 31);

        return $zigZagDecoded;
    }

    private function writeLongASCII(): string
    {
        $index = array_search(252, \array_slice($this->bytesArray, $this->index));
        $escaped = $this->escape($index);

        $this->index = $index + 1;

        return sprintf('"%s"', $escaped);
    }

    private function writeLongUnicode(): string
    {
        return null; // TODO: Implement long unicode
    }

    private function write7BitsEncoded(): string
    {
        return null; // TODO: Implement 7 bits encoding
    }

    private function writeLongSharedString(): string
    {
        return null; // TODO: Implement long shared string
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
        $this->isDecodingKey = \array_key_exists($this->depthLevel, $this->currentStructure);

        return '}';
    }

    private function endBodyDecoding(): ?string
    {
        $this->isFullyDecoded = true;

        return null;
    }

    private function writeShortSharedKey(): string
    {
        if (\array_key_exists($this->bytesArray[$this->index - 1] - 64, $this->sharedKeyStrings)) {
            return sprintf(
                '"%s"',
                $this->sharedKeyStrings[$this->bytesArray[$this->index - 1] - 64]
            );
        }

        return null;
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

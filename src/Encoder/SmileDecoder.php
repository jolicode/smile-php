<?php

/*
 * This file is part of the Smile PHP project, a project by JoliCode.
 */

namespace Jolicode\SmilePhp\Encoder;

use Jolicode\SmilePhp\Enum\Bytes;
use Jolicode\SmilePhp\Exception\ShouldBeSkippedException;
use Jolicode\SmilePhp\Exception\UnexpectedValueException;

class SmileDecoder
{
    private bool $isFullyDecoded = false;

    /** @var int[] */
    private array $bytesArray = [];

    /** @var mixed[] */
    private array $resultArray = [];

    private string $outputFile = __DIR__ . '/../../files/decode/output.json';

    private int $index = 1; // index is 1 because unpack method returns a 1 indexed array
    private int $depthLevel = 0; // Used to know how far we are in a nested structure

    /** @var mixed[] */
    private array $nestedArrays = [];  // Used to construct arrays.

    /** @var mixed[] */
    private array $nestedObjects = [];  // Used to construct objects.
    private ?string $currentKey = null; // The current object key we are decoding.
    private bool $isDecodingKey = false; // A flag used to know if we are decoding an object or an array.

    /** @var string[] */
    private array $sharedKeyStrings = [];

    /** @var string[] */
    private array $sharedValueStrings = [];

    public function decode(string $smileData): array
    {
        file_put_contents($this->outputFile, null);
        $this->bytesArray = unpack('C*', $smileData);

        $this->decodeHead();

        while (!$this->isFullyDecoded) {
            if ($this->isDecodingKey) {
                $this->decodeKey();
            } else {
                $this->decodeValue();
            }
        }

        return $this->resultArray;
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

    // This method uses the BCMath extension, which returns a string, because the numbers it deals with may be too large for PHP.
    // See https://www.php.net/manual/en/language.types.integer.php#language.types.integer.overflow
    private function decodeFloat(int $bytesAmount): string
    {
        $result = $this->getNextByte();

        foreach (range(1, $bytesAmount) as $index) {
            $byte = $this->getNextByte();

            // These two lines actually simply do `$result = ($result << 7) + $byte` but compatible with very large numbers
            $pow = bcpow(2, 7);
            $result = bcadd(bcmul($result, $pow), $byte);
        }
        $unpack = unpack('d*', $result);
        // Not working for now :(.
        dd($unpack);

        return $result;
    }

    private function decodeBigDecimal(): int
    {
        $scale = $this->zigZagDecode($this->decodeInt());
        $magnitude = $this->decodeBigInt();

        return $magnitude * (10 ** $scale);
    }

    private function zigZagDecode(int $int): int
    {
        return ($int >> 1) ^ -($int & 1);
    }

    private function decodeStringValue(int $length): string
    {
        $result = '';
        $string = \array_slice($this->bytesArray, $this->index, $length);

        foreach ($string as $char) {
            $result .= mb_chr($char);
        }

        $this->index += $length;

        return $result;
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

        $this->bytesArray = \array_slice($this->bytesArray, 4);
        $this->index = 0;
    }

    private function decodeValue(): void
    {
        $byte = $this->getNextByte();

        if (null === $byte) {
            return;
        }

        try {
            $value = match (true) {
                Bytes::NULL_BYTE === $byte => throw new ShouldBeSkippedException(),
                $byte < Bytes::THRESHOLD_SIMPLE_LITERALS => $this->writeSharedString($byte), // 1 > 31 values are string shared values.
                $byte < Bytes::THRESHOLD_INTEGERS => $this->writeSimpleLiteral($byte), // 32 > 35 are simple literals.
                $byte < Bytes::THRESHOLD_FLOATS => $this->writeInteger($byte), // 36 > 39 are integers.
                $byte < Bytes::THRESHOLD_RESERVED => $this->writeFloat($byte), // 40 > 42 are floats.
                $byte < Bytes::THRESHOLD_TINY_ASCII => throw new ShouldBeSkippedException(), // 43 > 63 is reserved for future use.
                $byte < Bytes::THRESHOLD_SMALL_ASCII => $this->writeAsciiOrUnicode($byte, 1), // 64 > 96 are tiny ASCII.
                $byte < Bytes::THRESHOLD_TINY_UNICODE => $this->writeAsciiOrUnicode($byte, 33), // 97 > 127 are small ASCII.
                $byte < Bytes::THRESHOLD_SMALL_UNICODE => $this->writeAsciiOrUnicode($byte, 2), // 128 > 159 are tiny Unicode.
                $byte < Bytes::THRESHOLD_SMALL_INT => $this->writeAsciiOrUnicode($byte, 34), // 160 > 191 are small Unicode.
                $byte < Bytes::THRESHOLD_LONG_ASCII => $this->writeSmallInt($byte), // 192 > 223 are small int.
                $byte < Bytes::THRESHOLD_LONG_UNICODE => $this->writeLongASCII(),  // 224 > 227 are long ASCII.
                $byte < Bytes::THRESHOLD_7BITS => $this->writeLongUnicode(), // 228 > 231 are long unicode.
                $byte < Bytes::THRESHOLD_LONG_SHARED_STRING => $this->write7BitsEncoded(), // 232 > 235 is for 7 bits encoded values.
                $byte < Bytes::THRESHOLD_HEADER_BIT_VERSION => $this->writeLongSharedString(), // 235 > 239 is for long strings shared values.
                $byte < Bytes::THRESHOLD_STRUCTURE_LITERALS => throw new ShouldBeSkippedException(), // 240 > 247 is reserved for future use.
                Bytes::LITERAL_ARRAY_START === $byte => $this->writeArrayStart(), // 248 is array start
                Bytes::LITERAL_ARRAY_END === $byte => $this->writeArrayEnd(), // 249 is array end
                Bytes::LITERAL_OBJECT_START === $byte => $this->writeObjectStart(), // 250 is object start
                Bytes::LITERAL_OBJECT_END === $byte => $this->writeObjectEnd(), // 251 is object end
                Bytes::MARKER_END_OF_STRING === $byte => throw new UnexpectedValueException('An end of string byte was found while decoding the body.'), // 252 is end of string marker
                Bytes::RAW_BINARY === $byte => throw new ShouldBeSkippedException(), // TODO: implement Raw Binary
                Bytes::MARKER_END_OF_CONTENT === $byte => $this->endBodyDecoding(), // 254 is end of content marker
                default => throw new UnexpectedValueException(sprintf('Given byte does\'t exist. Given byte has value %d but decimal bytes range from 0 to 254.', $byte))
            };
        } catch (ShouldBeSkippedException $exception) {
            return;
        }

        // If not in a nested structure, we write the value directly in the results array
        if (!$this->depthLevel) {
            if ($this->currentKey) {
                $this->resultArray[$this->currentKey] = $value;
                $this->isDecodingKey = true;
                $this->currentKey = null;
            } else {
                $this->resultArray[] = $value;
            }
        // Else we write the value in the corresponding nested structure
        } else {
            if ($this->currentKey) {
                $this->nestedObjects[$this->depthLevel][$this->currentKey] = $value;
                $this->isDecodingKey = true;
                $this->currentKey = null;
            } else {
                $this->nestedArrays[$this->depthLevel][] = $value;
            }
        }
    }

    private function decodeKey(): void
    {
        $byte = $this->getNextByte();

        if (null === $byte) {
            return;
        }

        $this->currentKey = (match (true) {
            $byte < Bytes::THRESHOLD_SIMPLE_LITERALS => null, // 0 > 31 are reserved
            Bytes::LITERAL_EMPTY_STRING === $byte => '""',
            $byte < Bytes::THRESHOLD_LONG_SHARED_KEY => null, // 33 > 37 are reserved
            $byte < Bytes::KEY_LONG_KEY_NAME => null, // TODO: implement long shared key references
            Bytes::KEY_LONG_KEY_NAME === $byte => null, // TODO: implement long key name
            $byte < Bytes::KEY_FORBIDDEN_KEY => null, // 53 > 57 are reserved
            Bytes::KEY_FORBIDDEN_KEY === $byte => throw new UnexpectedValueException('Byte of decimal value 58 was found while decoding a key but this byte is forbidden for keys.'),
            $byte < Bytes::KEY_SHORT_SHARED_REFERENCE => null, // 59 > 63 are reserved
            $byte < Bytes::KEY_SHORT_ASCII => $this->writeShortSharedKey(), // 64 > 127 are short shared keys
            $byte < Bytes::KEY_SHORT_UNICODE => $this->writeAsciiOrUnicode($byte, 1, false), // 128 > 191 are short ASCII
            $byte < Bytes::LITERAL_ARRAY_START => $this->writeAsciiOrUnicode($byte, 2, false), // 192 > 247 are short Unicodes
            $byte < Bytes::LITERAL_ARRAY_START => $this->writeShortUnicodeKey($byte), // 192 > 247 are short Unicodes
            $byte < Bytes::LITERAL_ARRAY_END => null, // 248 > 250 are reserved
            Bytes::LITERAL_OBJECT_END === $byte => $this->writeObjectEnd(),
            default => null, // We do nothing for 252 > 254
        });

        $this->isDecodingKey = false;
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

    private function writeSimpleLiteral(int $byte): mixed
    {
        return match ($byte) {
            Bytes::LITERAL_EMPTY_STRING => '""',
            Bytes::LITERAL_NULL => null,
            Bytes::LITERAL_FALSE => false,
            Bytes::LITERAL_TRUE => true
        };
    }

    private function writeInteger(int $byte): int
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
            Bytes::FLOAT_32 => $this->decodeFloat(4),
            Bytes::FLOAT_64 => $this->decodeFloat(9),
            Bytes::BIG_DECIMAL => $this->decodeBigDecimal()
        };
    }

    private function writeAsciiOrUnicode(int $byte, int $bits, bool $isKey = false): string
    {
        $length = ($byte & 31) + $bits;
        $result = $this->decodeStringValue($length);

        if ($isKey) {
            $this->sharedKeyStrings[$byte & 31] = $result;
        } else {
            $this->sharedValueStrings[$byte & 31] = $result;
        }

        return $result;
    }

    private function writeSmallInt(int $byte): int
    {
        $zigZagDecoded = $this->zigZagDecode($byte & 31);

        return $zigZagDecoded;
    }

    private function writeLongASCII(): string
    {
        $stringLength = array_search(Bytes::MARKER_END_OF_STRING, \array_slice($this->bytesArray, $this->index + 1));
        $string = implode('', \array_slice($this->bytesArray, $this->index + 1, $stringLength));

        $this->index = $stringLength + 1;

        return $string;
    }

    private function writeLongUnicode()
    {
        // /!\ WARNING: The Go library does the same thing for long ASCII and for long Unicode. /!\
        // Because it's the most easy we'll do this as well but further testing needed since it's the only one to do this.
        // If we keep it we'll merge this method with WriteLongASCII().
        $stringLength = array_search(Bytes::MARKER_END_OF_STRING, \array_slice($this->bytesArray, $this->index + 1));
        $string = implode('', \array_slice($this->bytesArray, $this->index + 1, $stringLength));

        $this->index = $stringLength + 1;

        return sprintf('"%s"', $string);
    }

    private function write7BitsEncoded()
    {
        // Not sure about this so commenting for now. Moreover, this still misses this : https://github.com/ngyewch/smile-js/blob/3bee0ad72e4843e30268e101888073b5cab33983/src/main/js/decoder.js#L120
        // $length = $this->decodeInt();
        // $round = round($length * 8 / 7, 0, PHP_ROUND_HALF_DOWN);

        // $endIndex = min(count($this->bytesArray), $this->index + $round);
        // $array = array_slice($this->bytesArray, $this->index, count($this->bytesArray) - $endIndex);

        return null; // TODO: Implement 7 bits encoding
    }

    private function writeLongSharedString()
    {
        // JS and Go seem a bit different on this one so we'll need to double check on this. Using Go for now.
        $sharedKeyReference = (($this->bytesArray[$this->index] & 3) << 8) | ($this->bytesArray[$this->index + 1] & 255);
        $value = $this->sharedValueStrings[$sharedKeyReference];

        $this->index += 2;

        return $value;
    }

    private function writeArrayStart(): void
    {
        // All smile files start with either an array or an object and we dont want to count these as nested structures.
        if ($this->index > 1) {
            ++$this->depthLevel;
            $this->nestedArrays[$this->depthLevel] = [];
        }
    }

    /** @throws ShouldBeSkippedException */
    private function writeArrayEnd(): void
    {
        // Same as above : we skip the main structure.
        if (count($this->bytesArray) === $this->index) {
            throw new ShouldBeSkippedException();
        }

        if ($this->depthLevel - 1) {
            $this->nestedArrays[$this->depthLevel - 1][] = $this->nestedArrays[$this->depthLevel];
            unset($this->nestedArrays[$this->depthLevel]);
        } else {
            $this->resultArray[] = $this->nestedArrays[$this->depthLevel];
        }

        --$this->depthLevel;
    }

    private function writeObjectStart(): void
    {
        // All smile files start with either an array or an object and we dont want to count these as nested structures.
        if ($this->index > 1) {
            ++$this->depthLevel;

            if ($this->depthLevel) {
                $this->resultArray[$this->currentKey] = [];
            } else {
                $this->nestedObjects[$this->depthLevel][$this->currentKey] = [];
            }
        }

        $this->isDecodingKey = true;

        // We want to skip writing a value when we start a nested object.
        throw new ShouldBeSkippedException();
    }

    private function writeObjectEnd(): void
    {
        // Same as above : we skip the main structure.
        if (count($this->bytesArray) === $this->index) {
            throw new ShouldBeSkippedException();
        }

        if ($this->depthLevel - 1) {
            $previousArray = $this->nestedObjects[$this->depthLevel - 1];
            $previousArray[array_key_last($previousArray)] = $this->nestedObjects[$this->depthLevel];
            unset($this->nestedObjects[$this->depthLevel]);
        } else {
            $this->resultArray[array_key_last($this->resultArray)] = $this->nestedObjects[$this->depthLevel];
        }

        --$this->depthLevel;
    }

    private function endBodyDecoding(): ?string
    {
        $this->isFullyDecoded = true;

        return null;
    }

    private function writeShortSharedKey(): ?string
    {
        if (\array_key_exists($this->bytesArray[$this->index - 1] - 64, $this->sharedKeyStrings)) {
            return $this->sharedKeyStrings[$this->bytesArray[$this->index - 1] - 64];
        }

        return null;
    }

    private function writeShortAsciiKey(int $byte): string
    {
        $length = ($byte & 31) + 1;
        $result = $this->decodeStringValue($length);

        $this->sharedKeyStrings[$byte & 31] = $result;

        return $result;
    }

    private function writeShortUnicodeKey(int $byte): string
    {
        dd('ok');
        $length = ($byte & 192) + 2;
        $result = $this->decodeStringValue($length);

        $this->sharedKeyStrings[$byte & 192] = $result;

        return $result;
    }
}

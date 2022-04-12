<?php

/*
 * This file is part of the Smile PHP project, a project by JoliCode.
 */

namespace Jolicode\SmilePhp\Encoder;

use Jolicode\SmilePhp\Enum\Bytes;
use Jolicode\SmilePhp\Exception\ShouldBeSkippedException;
use Jolicode\SmilePhp\Exception\UnexpectedValueException;
use stdClass;

class SmileDecoder
{
    private bool $isFullyDecoded = false;

    /** @var int[] */
    private array $bytesArray = [];

    private string $outputFile = __DIR__ . '/../../files/decode/output.json';

    private int $index = 0;

    /** @var string[] */
    private array $sharedKeyStrings = [];

    /** @var string[] */
    private array $sharedValueStrings = [];

    public function decode(string $smileData): array|stdClass
    {
        file_put_contents($this->outputFile, null);
        $this->bytesArray = unpack('C*', $smileData);

        $this->decodeHead();

        $nextStructure = $this->getNextByte();

        if ($nextStructure === Bytes::LITERAL_ARRAY_START) {
            $result = $this->decodeArray();
        } elseif ($nextStructure === Bytes::LITERAL_OBJECT_START) {
            $result = $this->decodeObject();
        } else {
            throw new UnexpectedValueException('The smile file seems invalid since it doesn\'t start with an array or an object.');
        }

        return $result;
    }

    private function decodeHead(): void
    {
        $header = '';

        if ([58, 41, 10] !== array_slice($this->bytesArray, 0, 3)) { // Smile header should be ":)\n", so the decimal bytes must be 58, 41, 10.
            foreach (array_slice($this->bytesArray, 0, 3) as $byte) {
                $header .= mb_chr($byte);
            }

            throw new UnexpectedValueException(sprintf('Error while decoding the smile header. Smile header should be ":)\n" but "%s" was found.', $header));
        }

        $this->bytesArray = array_slice($this->bytesArray, 4); // TODO: remove the index 4 slice and handle the 4th header byte
        $this->index = 0;
    }

    /** @return mixed[] */
    private function decodeArray(): array
    {
        $array = [];

        while (true) {
            $byte = $this->getNextByte();

            if ($byte === Bytes::LITERAL_ARRAY_END || $this->isFullyDecoded) {
                break;
            }

            try {
                $array[] = $this->decodeValue($byte);
            } catch (ShouldBeSkippedException $exception) {
                continue;
            }
        }

        return $array;
    }

    private function decodeObject(): stdClass
    {
        $object = new stdClass();

        while (true) {
            $byte = $this->getNextByte();

            if ($byte === Bytes::LITERAL_OBJECT_END || $this->isFullyDecoded) {
                break;
            }

            try {
                $key = $this->decodeKey($byte);

                $byte = $this->getNextByte();
                $value = $this->decodeValue($byte);

                $object->$key = $value;
            } catch (ShouldBeSkippedException $exception) {
                continue;
            }
        }

        return $object;
    }

    private function decodeValue(int $byte): mixed
    {
        return match (true) {
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
            Bytes::LITERAL_ARRAY_START === $byte => $this->decodeArray(), // 248 is array start
            Bytes::LITERAL_ARRAY_END === $byte => throw new UnexpectedValueException(sprintf('An end of array byte was found while decoding a value but it should be skipped. Found when index was %d.', $this->index)), // 249 is array end
            Bytes::LITERAL_OBJECT_START === $this->decodeObject(), // 250 is object start
            Bytes::LITERAL_OBJECT_END === throw new UnexpectedValueException(sprintf('An end of object byte was found while decoding a value but it should be skipped. Found when index was %d.', $this->index)), // 251 is object end
            Bytes::MARKER_END_OF_STRING === $byte => throw new UnexpectedValueException(sprintf('An end of string byte was found while decoding a value. Found when index was %d.', $this->index)), // 252 is end of string marker
            Bytes::RAW_BINARY === $byte => throw new ShouldBeSkippedException(), // TODO: implement Raw Binary
            Bytes::MARKER_END_OF_CONTENT === $byte => $this->endBodyDecoding(), // 254 is end of content marker
            default => throw new UnexpectedValueException(sprintf('Given byte does\'t exist. Given byte has value %d but decimal bytes range from 0 to 254. Found when index was %d.', $byte, $this->index))
        };
    }

    private function decodeKey(int $byte): mixed
    {
        return match (true) {
            $byte < Bytes::THRESHOLD_SIMPLE_LITERALS => throw new ShouldBeSkippedException(), // 0 > 31 are reserved
            Bytes::LITERAL_EMPTY_STRING === $byte => '""',
            $byte < Bytes::THRESHOLD_LONG_SHARED_KEY => throw new ShouldBeSkippedException(), // 33 > 37 are reserved
            $byte < Bytes::KEY_LONG_KEY_NAME => throw new ShouldBeSkippedException(), // TODO: implement long shared key references
            Bytes::KEY_LONG_KEY_NAME === $byte => throw new ShouldBeSkippedException(), // TODO: implement long key name
            $byte < Bytes::KEY_FORBIDDEN_KEY => throw new ShouldBeSkippedException(), // 53 > 57 are reserved
            Bytes::KEY_FORBIDDEN_KEY === $byte => throw new UnexpectedValueException(sprintf('Byte of decimal value 58 was found while decoding a key but this byte is forbidden for keys. Found when index was %d.', $this->index)),
            $byte < Bytes::KEY_SHORT_SHARED_REFERENCE => throw new ShouldBeSkippedException(), // 59 > 63 are reserved
            $byte < Bytes::KEY_SHORT_ASCII => $this->writeShortSharedKey(), // 64 > 127 are short shared keys
            $byte < Bytes::KEY_SHORT_UNICODE => $this->writeAsciiOrUnicode($byte, 1, true), // 128 > 191 are short ASCII
            $byte < Bytes::LITERAL_ARRAY_START => $this->writeShortUnicodeKey($byte), // 192 > 247 are short Unicodes
            $byte < Bytes::LITERAL_OBJECT_END => throw new ShouldBeSkippedException(), // 248 > 250 are reserved
            Bytes::LITERAL_OBJECT_END === $byte => throw new UnexpectedValueException(sprintf('An end of object byte was found while decoding a key but it should be skipped. Found when index was %d.', $this->index)),
            $byte < 255 => throw new ShouldBeSkippedException(), // We do nothing for 252 > 254
            default => throw new UnexpectedValueException(sprintf('Given byte does\'t exist. Given byte has value %d but decimal bytes range from 0 to 254. Found when index was %d.', $byte, $this->index))
        };
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

    private function writeLongUnicode(): string
    {
        // /!\ WARNING: The Go library does the same thing for long ASCII and for long Unicode. /!\
        // Because it's the most easy we'll do this as well but further testing needed since it's the only one to do this.
        // If we keep it we'll merge this method with WriteLongASCII().
        $stringLength = array_search(Bytes::MARKER_END_OF_STRING, \array_slice($this->bytesArray, $this->index + 1));
        $string = implode('', \array_slice($this->bytesArray, $this->index + 1, $stringLength));

        $this->index = $stringLength + 1;

        return $string;
    }

    private function write7BitsEncoded()
    {
        // Not sure about this so commenting for now. Moreover, this still misses this : https://github.com/ngyewch/smile-js/blob/3bee0ad72e4843e30268e101888073b5cab33983/src/main/js/decoder.js#L120
        // $length = $this->decodeInt();
        // $round = round($length * 8 / 7, 0, PHP_ROUND_HALF_DOWN);

        // $endIndex = min(count($this->bytesArray), $this->index + $round);
        // $array = array_slice($this->bytesArray, $this->index, count($this->bytesArray) - $endIndex);

        throw new ShouldBeSkippedException(); // TODO: Implement 7 bits encoding
    }

    private function writeLongSharedString(): string
    {
        // JS and Go seem a bit different on this one so we'll need to double check on this. Using Go for now.
        $sharedKeyReference = (($this->bytesArray[$this->index] & 3) << 8) | ($this->bytesArray[$this->index + 1] & 255);
        $value = $this->sharedValueStrings[$sharedKeyReference];

        $this->index += 2;

        return $value;
    }

    private function endBodyDecoding(): string
    {
        $this->isFullyDecoded = true;

        throw new ShouldBeSkippedException();
    }

    private function writeShortSharedKey(): string
    {
        if (\array_key_exists($this->bytesArray[$this->index - 1] - 64, $this->sharedKeyStrings)) {
            return $this->sharedKeyStrings[$this->bytesArray[$this->index - 1] - 64];
        }

        throw new ShouldBeSkippedException();
    }

    private function writeShortUnicodeKey(int $byte): string
    {
        $length = ($byte & 31) + 2;

        $result = '';
        $bytes = \array_slice($this->bytesArray, $this->index, $length);
        $i = 0;

        while ($i < $length) {
            $char = $bytes[$i++];
            $msb4 = $char >> 4;

            if (($msb4 >= 0) && ($msb4 <= 7)) {
                $result .= mb_chr($char);
            } elseif (($msb4 >= 12) && ($msb4 <= 13)) {
                $nextChar = $bytes[$i++];
                $result .= mb_chr((($char & 31) << 6) | ($nextChar & 63));
            } else {
                $nextChar = $bytes[$i++];
                $nextNextChar = $bytes[$i++];
                $result .= mb_chr((($byte & 15) << 12) | (($nextChar & 62) << 6) | (($nextNextChar & 62) << 0));
            }
        }

        $this->sharedKeyStrings[$byte & 31] = $result;
        $this->index += $length;

        return $result;
    }
}

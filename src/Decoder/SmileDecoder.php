<?php

/*
 * This file is part of the Smile PHP project, a project by JoliCode.
 */

namespace Jolicode\SmilePhp\Decoder;

use Jolicode\SmilePhp\Enum\Bytes;
use Jolicode\SmilePhp\Decoder\DecodingResult;
use Jolicode\SmilePhp\Exception\UnexpectedValueException;

class SmileDecoder
{
    private SmileDecoderContext $context;

    public function decode(string $smileData): array|\stdClass
    {
        $this->context = new SmileDecoderContext(unpack('C*', $smileData));
        $this->decodeHead();

        return match ($this->getNextByte()) {
            Bytes::LITERAL_ARRAY_START => $this->decodeArray(),
            Bytes::LITERAL_OBJECT_START => $this->decodeObject(),
            default => throw new UnexpectedValueException('The smile file seems invalid since it doesn\'t start with an array or an object.'),
        };
    }

    private function decodeHead(): void
    {
        $header = '';

        if ([58, 41, 10] !== \array_slice($this->context->getBytesArray(), 0, 3)) { // Smile header should be ":)\n", so the decimal bytes must be 58, 41, 10.
            foreach (\array_slice($this->context->getBytesArray(), 0, 3) as $byte) {
                $header .= mb_chr($byte);
            }

            throw new UnexpectedValueException(sprintf('Error while decoding the smile header. Smile header should be ":)\n" but "%s" was found.', $header));
        }

        $optionsByte = $this->context->getSpecificByte(4);
        $version = $optionsByte & 240;
        $sharedKeys = (bool) ($optionsByte & 1);
        $sharedValues = (bool) (($optionsByte & 2) >> 1);
        $rawBinary = (bool) (($optionsByte & 4) >> 2);

        $this->context->setOptions($version, $sharedKeys, $sharedValues, $rawBinary);
        $this->context->increaseIndex(4);
    }

    /** @return mixed[] */
    private function decodeArray(): array
    {
        $array = [];

        while (true) {
            $byte = $this->getNextByte();

            if (Bytes::LITERAL_ARRAY_END === $byte || $this->context->isFullyDecoded()) {
                break;
            }

            $value = $this->decodeValue($byte);

            if (!$value->shouldBeSkipped) {
                $array[] = $value->data;
            }
        }

        return $array;
    }

    private function decodeObject(): \stdClass
    {
        $object = new \stdClass();

        while (true) {
            $byte = $this->getNextByte();

            if (Bytes::LITERAL_OBJECT_END === $byte || $this->context->isFullyDecoded()) {
                break;
            }

            $key = $this->decodeKey($byte);

            $byte = $this->getNextByte();
            $value = $this->decodeValue($byte);

            if (!$key->shouldBeSkipped && !$value->shouldBeSkipped) {
                $object->{$key->data} = $value->data;
            }
        }

        return $object;
    }

    private function decodeValue(int $byte): DecodingResult
    {
        return match (true) {
            $byte === Bytes::NULL_BYTE => new DecodingResult(null, true),
            $byte < Bytes::THRESHOLD_SIMPLE_LITERALS => new DecodingResult($this->writeSharedString($byte)), // 1 > 31 values are string shared values.
            $byte < Bytes::THRESHOLD_INTEGERS => new DecodingResult($this->writeSimpleLiteral($byte)), // 32 > 35 are simple literals.
            $byte < Bytes::THRESHOLD_FLOATS => new DecodingResult($this->writeInteger($byte)), // 36 > 39 are integers.
            $byte < Bytes::THRESHOLD_RESERVED => new DecodingResult($this->writeFloat($byte)), // 40 > 42 are floats.
            $byte < Bytes::THRESHOLD_TINY_ASCII => new DecodingResult(null, true), // 43 > 63 is reserved for future use.
            $byte < Bytes::THRESHOLD_SMALL_ASCII => new DecodingResult($this->writeAsciiOrUnicode($byte, 1)), // 64 > 96 are tiny ASCII.
            $byte < Bytes::THRESHOLD_TINY_UNICODE => new DecodingResult($this->writeAsciiOrUnicode($byte, 33)), // 97 > 127 are small ASCII.
            $byte < Bytes::THRESHOLD_SMALL_UNICODE => new DecodingResult($this->writeAsciiOrUnicode($byte, 2)), // 128 > 159 are tiny Unicode.
            $byte < Bytes::THRESHOLD_SMALL_INT => new DecodingResult($this->writeAsciiOrUnicode($byte, 34)), // 160 > 191 are small Unicode.
            $byte < Bytes::THRESHOLD_LONG_ASCII => new DecodingResult($this->writeSmallInt($byte)), // 192 > 223 are small int.
            $byte < Bytes::THRESHOLD_LONG_UNICODE => new DecodingResult($this->writeLongASCII()),  // 224 > 227 are long ASCII.
            $byte < Bytes::THRESHOLD_7BITS => new DecodingResult($this->writeLongUnicode()), // 228 > 231 are long unicode.
            $byte < Bytes::THRESHOLD_LONG_SHARED_STRING => new DecodingResult($this->write7BitsEncoded()), // 232 > 235 is for 7 bits encoded values.
            $byte < Bytes::THRESHOLD_HEADER_BIT_VERSION => new DecodingResult($this->writeLongSharedString()), // 235 > 239 is for long strings shared values.
            $byte < Bytes::THRESHOLD_STRUCTURE_LITERALS => new DecodingResult(null, true), // 240 > 247 is reserved for future use.
            $byte === Bytes::LITERAL_ARRAY_START => new DecodingResult($this->decodeArray()), // 248 is array start
            $byte === Bytes::LITERAL_ARRAY_END => throw new UnexpectedValueException(sprintf('An end of array byte was found while decoding a value but it should be skipped. Found when index was %d.', $this->context->getIndex())), // 249 is array end
            $byte === Bytes::LITERAL_OBJECT_START => new DecodingResult($this->decodeObject()), // 250 is object start
            $byte === Bytes::LITERAL_OBJECT_END => throw new UnexpectedValueException(sprintf('An end of object byte was found while decoding a value but it should be skipped. Found when index was %d.', $this->context->getIndex())), // 251 is object end
            $byte === Bytes::MARKER_END_OF_STRING => throw new UnexpectedValueException(sprintf('An end of string byte was found while decoding a value. Found when index was %d.', $this->context->getIndex())), // 252 is end of string marker
            $byte === Bytes::RAW_BINARY => new DecodingResult(null, true), // TODO: implement Raw Binary
            $byte === Bytes::MARKER_END_OF_CONTENT => new DecodingResult($this->endBodyDecoding(), true), // 254 is end of content marker
            default => throw new UnexpectedValueException(sprintf('Given byte does\'t exist. Given byte has value %d but decimal bytes range from 0 to 254. Found when index was %d.', $byte, $this->context->getIndex()))
        };
    }

    private function decodeKey(int $byte): DecodingResult
    {
        return match (true) {
            $byte < Bytes::THRESHOLD_SIMPLE_LITERALS => new DecodingResult(null, true), // 0 > 31 are reserved
            $byte === Bytes::LITERAL_EMPTY_STRING => new DecodingResult('""'),
            $byte < Bytes::THRESHOLD_LONG_SHARED_KEY => new DecodingResult(null, true), // 33 > 37 are reserved
            $byte < Bytes::KEY_LONG_KEY_NAME => new DecodingResult(null, true), // TODO: implement long shared key references
            $byte === Bytes::KEY_LONG_KEY_NAME => new DecodingResult(null, true), // TODO: implement long key name
            $byte < Bytes::KEY_FORBIDDEN_KEY => new DecodingResult(null, true), // 53 > 57 are reserved
            $byte === Bytes::KEY_FORBIDDEN_KEY => throw new UnexpectedValueException(sprintf('Byte of decimal value 58 was found while decoding a key but this byte is forbidden for keys. Found when index was %d.', $this->context->getIndex())),
            $byte < Bytes::KEY_SHORT_SHARED_REFERENCE => new DecodingResult(null, true), // 59 > 63 are reserved
            $byte < Bytes::KEY_SHORT_ASCII => new DecodingResult($this->writeShortSharedKey()), // 64 > 127 are short shared keys
            $byte < Bytes::KEY_SHORT_UNICODE => new DecodingResult($this->writeAsciiOrUnicode($byte, 1, true)), // 128 > 191 are short ASCII
            $byte < Bytes::LITERAL_ARRAY_START => new DecodingResult($this->writeShortUnicodeKey($byte)), // 192 > 247 are short Unicodes
            $byte < Bytes::LITERAL_OBJECT_END => new DecodingResult(null, true), // 248 > 250 are reserved
            $byte === Bytes::LITERAL_OBJECT_END => throw new UnexpectedValueException(sprintf('An end of object byte was found while decoding a key but it should be skipped. Found when index was %d.', $this->context->getIndex())),
            $byte < 255 => new DecodingResult(null, true), // We do nothing for 252 > 254
            default => throw new UnexpectedValueException(sprintf('Given byte does\'t exist. Given byte has value %d but decimal bytes range from 0 to 254. Found when index was %d.', $byte, $this->context->getIndex()))
        };
    }

    private function getNextByte(): ?int
    {
        if ($this->context->getIndex() + 1 > $this->context->getBytesArrayCount()) {
            $this->context->setFullyDecoded();

            return null;
        }

        $byte = $this->context->getSpecificByte($this->context->getIndex());
        $this->context->increaseIndex(1);

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
            $binaryString .= sprintf('%07b', $this->context->getSpecificByte($this->context->getIndex() + $index));

            if ($index === $bytesAmount - 1) {
                $trailing = \strlen($binaryString) % 8;
                $binaryString = substr($binaryString, 0, \strlen($binaryString) - $trailing);
            }
        }

        $this->context->increaseIndex($bytesAmount);

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
        $string = \array_slice($this->context->getBytesArray(), $this->context->getIndex() - 1, $length);

        foreach ($string as $char) {
            $result .= mb_chr($char);
        }

        $this->context->increaseIndex($length);

        return $result;
    }

    /** @throws UnexpectedValueException */
    private function writeSharedString(int $byte): ?string
    {
        if (!$this->context->hasSharedValues()) {
            throw new UnexpectedValueException('Trying to read a shared value but shared values are disabled.');
        }

        if (\array_key_exists($byte & 31, $this->context->getSharedValues())) {
            return $this->context->getSharedValue($byte & 31);
        }

        // We probably want to throw an exception here
        return null;
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

        if ($isKey && $this->context->hasSharedKeys()) {
            $this->context->addSharedKey($byte & 31, $result);
        } elseif ($this->context->hasSharedValues()) {
            $this->context->addSharedValue($byte & 31, $result);
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
        $stringLength = array_search(Bytes::MARKER_END_OF_STRING, \array_slice($this->context->getBytesArray(), $this->context->getIndex() + 1));
        $string = implode('', \array_slice($this->context->getBytesArray(), $this->context->getIndex() + 1, $stringLength));

        $this->context->increaseIndex($stringLength + 1);

        return $string;
    }

    private function writeLongUnicode(): string
    {
        // /!\ WARNING: The Go library does the same thing for long ASCII and for long Unicode. /!\
        // Because it's the most easy we'll do this as well but further testing needed since it's the only one to do this.
        // If we keep it we'll merge this method with WriteLongASCII().
        $stringLength = array_search(Bytes::MARKER_END_OF_STRING, \array_slice($this->context->getBytesArray(), $this->context->getIndex() + 1));
        $string = implode('', \array_slice($this->context->getBytesArray(), $this->context->getIndex() + 1, $stringLength));

        $this->context->increaseIndex($stringLength + 1);

        return $string;
    }

    private function write7BitsEncoded()
    {
        // Not sure about this so commenting for now. Moreover, this still misses this : https://github.com/ngyewch/smile-js/blob/3bee0ad72e4843e30268e101888073b5cab33983/src/main/js/decoder.js#L120
        // $length = $this->decodeInt();
        // $round = round($length * 8 / 7, 0, PHP_ROUND_HALF_DOWN);

        // $endIndex = min(count($this->context->getBytesArray()), $this->context->getIndex() + $round);
        // $array = array_slice($this->context->getBytesArray(), $this->context->getIndex(), count($this->context->getBytesArray()) - $endIndex);

        return null; // TODO: Implement 7 bits encoding
    }

    /** @throws UnexpectedValueException */
    private function writeLongSharedString(): string
    {
        if (!$this->context->hasSharedValues()) {
            throw new UnexpectedValueException('Trying to read a shared value but shared values are disabled.');
        }

        // JS and Go seem a bit different on this one so we'll need to double check on this. Using Go for now.
        $sharedValueReference = (($this->context->getSpecificByte($this->context->getIndex()) & 3) << 8) | ($this->context->getSpecificByte($this->context->getIndex() + 1) & 255);
        $value = $this->context->getSharedValue($sharedValueReference);

        $this->context->increaseIndex(2);

        return $value;
    }

    private function endBodyDecoding()
    {
        $this->context->setFullyDecoded();

        return null;
    }

    /** @throws UnexpectedValueException */
    private function writeShortSharedKey(): ?string
    {
        if (!$this->context->hasSharedKeys()) {
            throw new UnexpectedValueException('Trying to read a shared key but shared keys are disabled.');
        }

        $searchedKey = $this->context->getSpecificByte($this->context->getIndex() - 1) - 64;

        if (\array_key_exists($searchedKey, $this->context->getSharedKeys())) {
            return $this->context->getSharedKey($searchedKey);
        }

        return null;
    }

    private function writeShortUnicodeKey(int $byte): string
    {
        $length = ($byte & 31) + 2;

        $result = '';
        $bytes = \array_slice($this->context->getBytesArray(), $this->context->getIndex() - 1, $length);
        $i = 0;

        while ($i < $length) {
            $char = $bytes[$i++];
            $msb4 = $char >> 4;

            if (($msb4 >= 0) && ($msb4 <= 7)) {
                $result .= mb_chr($char);
            } elseif ($msb4 === 12 || $msb4 === 13) {
                $nextChar = $bytes[$i++];
                $result .= mb_chr((($char & 31) << 6) | ($nextChar & 63));
            } else {
                $nextChar = $bytes[$i++];
                $nextNextChar = $bytes[$i++];
                $result .= mb_chr((($byte & 15) << 12) | (($nextChar & 62) << 6) | (($nextNextChar & 62) << 0));
            }
        }

        if ($this->context->hasSharedKeys()) {
            $this->context->addSharedKey($byte & 31, $result);
        }

        $this->context->increaseIndex($length);

        return $result;
    }
}

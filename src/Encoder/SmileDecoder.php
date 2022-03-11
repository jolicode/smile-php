<?php

/*
 * This file is part of the Smile PHP project, a project by JoliCode.
 */

namespace Jolicode\SmilePhp\Encoder;

use Jolicode\SmilePhp\Enum\Byte;
use Jolicode\SmilePhp\Exception\UnexpectedValueException;

class SmileDecoder
{
    private const MODE_HEAD = 0; // Currently decoding header
    private const MODE_ROOT = 1; // Currently decoding main file
    private const MODE_ARRAY = 2; // Currently inside an array
    private const MODE_VALUE = 3; // Currently decoding an array value
    private const MODE_KEY = 4; // Currently decoding an array key
    private const MODE_DONE = 5; // Well...

    /** @var int[] $bytesArray */
    private array $bytesArray = [];
    private string $decodedString = '';

    private int $mode = self::MODE_HEAD;
    private int $index = 1; // index is 1 because unpack method returns a 1 indexed array

    private int $depthLevel = 0; // Used to know how far we are in a nested structure

    /** @var bool[] $currentStructure */
    private array $currentStructure = [];

    /** @var string[] $sharedValueKeys */
    private array $sharedValueKeys = [];

    /** @var string[] $sharedValueStrings */
    private array $sharedValueStrings = [];

    public function decode(string $smileData): string
    {
        $this->bytesArray = unpack('C*', $smileData);

        while (self::MODE_DONE !== $this->mode) {
            match ($this->mode) {
                self::MODE_HEAD => $this->decodeHead(),
                self::MODE_ROOT,
                self::MODE_ARRAY,
                self::MODE_VALUE => $this->decodeBody(),
            };
        }

        return $this->decodedString;
    }

    private function getNextByte(): ?int
    {
        if ($this->index + 1 > \count($this->bytesArray)) {
            $this->mode = self::MODE_DONE;

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

    private function decodeInt() {
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

    private function zigzagDecode(int $encoded) {
        return ($encoded >> 1) ^ (-($encoded - 1));
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

        $this->mode = self::MODE_ROOT;
    }

    private function decodeBody(): void
    {
        $byte = $this->getNextByte();

        if (null === $byte) {
            return;
        }

        if ($byte === Byte::NULL) { // Null byte, we skip
            return;
        } elseif ($byte <= 31) { // 1 > 31 are string shared values
            $value = $this->sharedValueStrings[$this->bytesArray[$this->index - 1] - 1];
            $this->decodedString .= sprintf('"%s"', $value);
        } elseif ($byte <= Byte::LITTERAL_TRUE) { // 32 > 35 are litterals
            $this->decodedString .= match ($byte) {
                Byte::LITTERAL_EMPTY_STRING => '""',
                Byte::LITTERAL_NULL => 'null',
                Byte::LITTERAL_FALSE => 'false',
                Byte::LITTERAL_TRUE => 'true'
            };
        } elseif ($byte < Byte::PREFIX_FP) { // 36 > 40 are integers
            $this->decodedString .= match ($length = $byte & 3) {
                $length < 2 => $this->zigzagDecode($this->decodeInt()),
                $length === 2 => null, // TODO : implement BigIntegers
                default => null, // Reserved for future use
            };

        }
    }
}

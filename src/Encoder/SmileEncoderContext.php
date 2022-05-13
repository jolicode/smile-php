<?php

namespace Jolicode\SmilePhp\Encoder;

class SmileEncoderContext
{
        /** @var int[] */
    private readonly array $bytesArray;
    private readonly int $bytesArrayCount;

    private array $smileBytes = [];

    private readonly bool $sharedKeysOption;
    private readonly bool $sharedValuesOption;
    private readonly bool $rawBinaryOption;

    private bool $isFullyEncoded = false;
    private int $index = 1;

    /** @var string[] */
    private array $sharedKeys = [];

    /** @var string[] */
    private array $sharedValues = [];

    public function __construct(array $bytesArray)
    {
        $this->bytesArray = $bytesArray;
        $this->bytesArrayCount = \count($bytesArray);
    }

    public function setOptions(bool $sharedKeys, bool $sharedValues, bool $rawBinary): void
    {
        $this->sharedKeysOption = $sharedKeys;
        $this->sharedValuesOption = $sharedValues;
        $this->rawBinaryOption = $rawBinary;
    }

    public function hasSharedKeys(): bool
    {
        return $this->sharedKeysOption;
    }

    public function hasSharedValues(): bool
    {
        return $this->sharedValuesOption;
    }

    public function hasRawBinary(): bool
    {
        return $this->rawBinaryOption;
    }

    public function setFullyEncoded(): void
    {
        $this->isFullyEncoded = true;
    }

    public function isFullyEncoded(): bool
    {
        return $this->isFullyEncoded;
    }

    public function getSmileBytes()
    {
        return $this->smileBytes;
    }

    public function addSmileBytes(array|int $bytes)
    {
        if (is_array($bytes)) {
            $this->smileBytes = [...$this->smileBytes, ...$bytes];
        } else {
            $this->smileBytes[] = $bytes;
        }
    }

    public function getBytesArray(): array
    {
        return $this->bytesArray;
    }

    public function getSpecificByte(int $index): int
    {
        return $this->bytesArray[$index];
    }

    public function getBytesArrayCount(): int
    {
        return $this->bytesArrayCount;
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function increaseIndex(int $amount): void
    {
        $this->index += $amount;
    }

    public function getSharedKeys(): array
    {
        return $this->sharedKeys;
    }

    public function getSharedKey(int $key): mixed
    {
        return $this->sharedKeys[$key];
    }

    public function addSharedKey(string $value): void
    {
        if (1024 === \count($this->getSharedKeys())) {
            $this->sharedKeys = [];
        }

        $this->sharedKeys[] = $value;
    }

    public function getSharedValues(): array
    {
        return $this->sharedValues;
    }

    public function getSharedValue(int $key): string
    {
        return $this->sharedValues[$key];
    }

    public function addSharedValue(string $value): void
    {
        if (1024 === \count($this->getSharedValues())) {
            $this->sharedValues = [];
        }

        $this->sharedValues[] = $value;
    }
}

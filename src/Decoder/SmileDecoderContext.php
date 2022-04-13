<?php

namespace Jolicode\SmilePhp\Decoder;

class SmileDecoderContext
{
    private bool $isFullyDecoded = false;

    /** @var int[] */
    private array $bytesArray = [];
    private int $bytesArrayCount = 0;

    private int $index = 1;

    /** @var string[] */
    private array $sharedKeys = [];

    /** @var string[] */
    private array $sharedValues = [];

    private int $version;
    private bool $sharedKeysOption;
    private bool $sharedValuesOption;
    private bool $rawBinaryOption;

    public function __construct(array $bytesArray)
    {
        $this->bytesArray = $bytesArray;
        $this->bytesArrayCount = \count($bytesArray);
    }

    public function setOptions(int $version, bool $sharedKeys, bool $sharedValues, bool $rawBinary): void
    {
        $this->version = $version;
        $this->sharedKeysOption = $sharedKeys;
        $this->sharedValuesOption = $sharedValues;
        $this->rawBinaryOption = $rawBinary;
    }

    public function getVersion(): int
    {
        return $this->version;
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

    public function setFullyDecoded(): void
    {
        $this->isFullyDecoded = true;
    }

    public function isFullyDecoded(): bool
    {
        return $this->isFullyDecoded;
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

    public function addSharedKey(int $key, mixed $value): void
    {
        $this->sharedKeys[$key] = $value;
    }

    public function getSharedValues(): array
    {
        return $this->sharedValues;
    }

    public function getSharedValue(int $key): mixed
    {
        return $this->sharedValues[$key];
    }

    public function addSharedValue(int $key, mixed $value): void
    {
        $this->sharedValues[$key] = $value;
    }
}

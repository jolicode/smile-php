<?php

/*
 * This file is part of the Smile PHP project, a project by JoliCode.
 */

namespace Jolicode\SmilePhp\Decoder;

class SmileDecoderContext
{
    /** @var int[] */
    private readonly array $bytesArray;
    private readonly int $bytesArrayCount;

    private readonly int $version;
    private readonly bool $sharedKeysOption;
    private readonly bool $sharedValuesOption;
    private readonly bool $rawBinaryOption;

    private bool $isFullyDecoded = false;
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

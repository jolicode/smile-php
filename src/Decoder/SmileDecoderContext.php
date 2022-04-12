<?php

namespace Jolicode\SmilePhp\Decoder;

class SmileDecoderContext
{
    private bool $isFullyDecoded = false;

    /** @var int[] */
    private array $bytesArray = [];
    private int $bytesArrayCount = 0;

    private int $index = 0;

    /** @var string[] */
    private array $sharedKeys = [];

    /** @var string[] */
    private array $sharedValues = [];

    public function __construct(array $bytesArray)
    {
        $this->bytesArray = $bytesArray;
        $this->bytesArrayCount = \count($bytesArray);
    }

    public function setFullyDecoded()
    {
        $this->isFullyDecoded = true;
    }

    public function isFullyDecoded()
    {
        return $this->isFullyDecoded;
    }

    public function getBytesArray()
    {
        return $this->bytesArray;
    }

    public function getSpecificByte(int $index)
    {
        return $this->bytesArray[$index];
    }

    public function getBytesArrayCount()
    {
        return $this->bytesArrayCount;
    }

    public function getIndex()
    {
        return $this->index;
    }

    public function increaseIndex(int $amount)
    {
        $this->index += $amount;
    }

    public function getSharedKeys()
    {
        return $this->sharedKeys;
    }

    public function getSharedKey(int $key)
    {
        return $this->sharedKey[$key];
    }

    public function addSharedKey(int $key, mixed $value)
    {
        $this->sharedKeys[$key] = $value;
    }

    public function getSharedValues()
    {
        return $this->sharedValues;
    }

    public function getSharedValue(int $key)
    {
        return $this->sharedValues[$key];
    }

    public function addSharedValue(int $key, mixed $value)
    {
        $this->sharedValues[$key] = $value;
    }
}

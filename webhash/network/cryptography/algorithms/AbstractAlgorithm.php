<?php

namespace WebHash\Network\Cryptography\Algorithms;

abstract class AbstractAlgorithm
{
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public abstract function hash(string $data) : string;
    public abstract function verify(string $data, string $hash) : bool;

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
}

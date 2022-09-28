<?php

namespace WebHash\Network\Cryptography\Algorithms;

class AlgorithmArgon2id extends AbstractAlgorithm
{

    private array $options;

    public function __construct()
    {
        parent::__construct("argon2id");
        $this->options = [
            "memory_cost" => 2048,
            "time_cost" => 4,
            "threads" => 3
        ];
    }

    public function hash(string $data): string
    {
        return password_hash($data, $this->getName(), $this->options);
    }

    public function verify(string $data, string $hash): bool
    {
        return password_verify($data, $hash);
    }
}

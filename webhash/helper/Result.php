<?php

namespace WebHash\Helper;

//the result object returns data to the client
class Result
{
    /*
     * @var mixed
     */
    public $data = "";
    public int $status = 1;
    public string $message = "";
    public int $cache = 0;
    public string $contentType = "application/json";

    public function __construct($status)
    {
        $this->status = $status;
    }

    /**
     * @return bool
     */
    public function isSuccessful() : bool
    {
        return $this->status == 1;
    }

    /**
     * @param string $message
     */
    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function setCache($cache)
    {
        $this->cache = $cache;
    }

    public function setContentType($contentType)
    {
        $this->contentType = $contentType;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return int
     */
    public function getCache(): int
    {
        return $this->cache;
    }

    /**
     * @return string
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * @param String $message
     * @param String|null $data
     * @return Result
     */
    public static function error(String $message, ?String $data = null): Result
    {
        $result = new Result(-1);
        $result->setMessage($message);
        if($data != null) {
            $result->setData($data);
        }
        return $result;
    }

    public static function success($data = null, ?String $message = null): Result
    {
        $result = new Result(1);
        if($data != null) {
            $result->setData($data);
        }
        if($message != null) {
            $result->setMessage($message);
        }
        return $result;
    }
}

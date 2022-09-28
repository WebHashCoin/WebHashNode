<?php
namespace WebHash\Annotation;

/** @Annotation */
class Route
{
    /*
     * @var string
     */
    public string $route;
    /*
     * @var string
     */
    public string $method;
    /*
     * @var string
     */
    public string $controller;
    /*
     * @var string
     */
    private string $action;
    /*
     * @var array
     */
    public array $params = [];
    /*
     * @var string
     */
    public string $type = "JSON";

    /**
     * @param string $controller
     */
    public function setController(string $controller): void
    {
        $this->controller = $controller;
    }

    /**
     * @return string
     */
    public function getRoute(): string
    {
        return $this->route;
    }

    /**
     * @return string
     */
    public function getController(): string
    {
        return $this->controller;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * @param string $action
     */
    public function setAction(string $action): void
    {
        $this->action = $action;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }
}

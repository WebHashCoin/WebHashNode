<?php

namespace WebHash;

use Doctrine\Common\Annotations\AnnotationReader;
use ReflectionClass;
use ReflectionException;
use WebHash\Annotation\Route;
use WebHash\Helper\Result;

class Router
{

    /**
     * @var array|false
     */
    protected $controllers;

    public function __construct()
    {
        //load the controllers from /webhash/controller
        $this->controllers = $this->loadControllers();
    }

    private function loadControllers(): array
    {
        //get the controllers from the namespace WebHash\Controller
        $controllers = glob(__DIR__ . '/Controller/*Controller.php');
        $namespace = 'WebHash\Controller\\';
        return array_map(function ($controller) use ($namespace) {
            return str_replace('.php', '', str_replace(__DIR__ . '/Controller/', $namespace, $controller));
        }, $controllers);
    }

    public function startRouting() : Result
    {
        //get route from url
        $route = $this->getRoute();
        //get controller from route
        $annotation = $this->getAnnotationFromRoute($route);
        if(!$annotation) {
            $result = new Result(-1);
            $result->setMessage("Route not found");
            return $result;
        }
        //get controller
        $controller = $annotation->getController();
        //get action
        $action = $annotation->getAction();
        //get method
        $method = $annotation->getMethod();
        //get type
        $type = $annotation->getType();
        //get arguments
        $parameters = $annotation->getParams();
        //check if method is allowed
        if($method !== $_SERVER['REQUEST_METHOD']) {
            $result = new Result(-1);
            $result->setMessage("Method not allowed");
            return $result;
        }
        //check if controller exists
        if(!in_array($controller, $this->controllers)) {
            $result = new Result(-1);
            $result->setMessage("Controller not found");
            return $result;
        }
        //check if action exists
        if(!method_exists($controller, $action)) {
            $result = new Result(-1);
            $result->setMessage("Action not found");
            return $result;
        }
        //check if arguments are set
        if(count($parameters) != 0) {
            $data = [];
            if($method === "GET") {
                $data = $_GET;
            } else if($method === "POST") {
                $data = $_POST;
                if(count($data) === 0) {
                    $data = json_decode(file_get_contents('php://input'), true);
                }
            }
            //check if all arguments are set
            foreach ($parameters as $parameter) {
                if(!isset($data[$parameter])) {
                    $result = new Result(-1);
                    $result->setMessage("Parameter (".$parameter.") not set.");
                    return $result;
                }
            }
        }
        //call controller
        $controller = new $controller();
        $result = $controller->$action();
        //set header to Result Content-Type
        if(!$result instanceof Result) {
            $result = new Result(-1);
            $result->setMessage("no result");
        }
        return $result;
    }

    public function printResult(Result $result) {
        header("Content-Type: " . $result->getContentType());
        if($result->getCache() && $result->getCache() > 0) {
            $cache = $result->getCache();
            header("Cache-Control: max-age=$cache");
            header("Expires: " . gmdate('D, d M Y H:i:s', time() + $cache) . ' GMT');
            header("Pragma: cache");
        }
        if($result->getContentType() === "application/json") {
            $resultObject = [
                "status" => $result->getStatus()
            ];
            //add message if not empty
            if($result->getMessage() !== "") {
                $resultObject["message"] = $result->getMessage();
            }
            //add data if not empty
            if($result->getData() !== "") {
                $resultObject["data"] = $result->getData();
            }
            //add cache if not empty
            if($result->getCache() != 0) {
                $resultObject["cache"] = $result->getCache();
            }
            echo json_encode($resultObject);
        } else {
            header("HTTP/1.1 " . $result->getStatus());
            header("Status: " . $result->getStatus());
            echo $result->getData();
        }
    }

    private function getRoute(): string
    {
        //route is everything after the domain name
        $route = $_SERVER['REQUEST_URI'];
        //remove the domain name from the route
        $route = str_replace($_SERVER['HTTP_HOST'], '', $route);
        //remove the first slash from the route
        //return the route
        return '/'.ltrim($route, '/');
    }

    private function getAnnotationFromRoute($route)
    {
        //get all the annotations from the controllers
        $controllers = $this->controllers;
        //loop through the controllers
        foreach ($controllers as $controller) {
            //get the annotations from the controller
            $annotations = $this->getAnnotations($controller);
            if(!$annotations) {
                continue;
            }
            //loop through the annotations
            foreach ($annotations as $annotationData) {
                $annotation = $annotationData["annotations"];
                $method = $annotationData["method"];
                //if the annotation is a route annotation
                if ($annotation instanceof Route) {
                    //if the route annotation matches the route
                    //allow regex in route
                    $routeRegex = str_replace('*', '.*', $annotation->getRoute());
                    if (preg_match('#^' . $routeRegex . '$#', $route)) {
                        //return the annotation
                        $annotation->setAction($method);
                        $annotation->setController($controller);
                        return $annotation;
                    }
                }
            }
        }
        return false;
    }

    private function getAnnotations($controller)
    {
        //get annotations from the controller using doctrine/annotations
        $reader = new AnnotationReader();
        try {
            $reflectionClass = new ReflectionClass($controller);
            //get the annotations from the methods
            $methods = $reflectionClass->getMethods();
            $annotations = [];
            foreach ($methods as $method) {
                $methodAnnotations = $reader->getMethodAnnotations($method);
                if(count($methodAnnotations) > 0) {
                    $annotations[] = [
                        "method" => $method->getName(),
                        "annotations" => $methodAnnotations[0]
                    ];
                }
            }
            return $annotations;
        } catch (ReflectionException $e) {
            return false;
        }
    }
}

<?php

namespace tests;

use PHPUnit_Framework_TestCase;
use Drips\Routing\Router;
use Drips\Routing\Error404Exception;
use Drips\HTTP\Request;

$_SERVER["SCRIPT_FILENAME"] = "";

class RoutingTest extends PHPUnit_Framework_TestCase
{
    /**
     * @runInSeparateProcess
     * @dataProvider routeProvider
     */
    public function testRouting($route, $url, $result) {
        $request = Request::getInstance();
        $request->server->set('REQUEST_URI', $url);
        $router = Router::getInstance();
        $router->add("test", $route, function() {});
        try {
            $res = $router->route();
            $this->assertEquals($res, $result);
        } catch(Error404Exception $e) {
            if($result){
                $this->fail();
            } else {
                $this->assertFalse($result);
            }
        }
    }

    public function testMatchingRouter() {
        $request = Request::getInstance();
        $request->server->set('REQUEST_URI', "/users/admin");
        $router = Router::getInstance();
        $router->add("users", "/users/{name}", function() {}, array("pattern" => ["name" => "([A-Z]+)"]));
        try {
            $router->route();
            $this->fail();
        } catch(Error404Exception $e){
            $this->assertTrue(true);
        }
        $router->add("users2", "/users/{name}", function() {}, array("pattern" => ["name" => "([a-z]+)"]));
        $this->assertTrue($router->route());
        $this->assertFalse($router->add("users2", "/userz/{name}", function() {}, array("pattern" => ["name" => "([a-z]+)"])));
    }

    /**
     * @runInSeparateProcess
     */
    public function testSecureRoute() {
        $request = Request::getInstance();
        $request->server->set('REQUEST_URI', "/secusers/asdf");
        $router = Router::getInstance();
        $router->add("secure", "/secusers/{name}", function() {}, array("pattern" => ["name" => "([a-z]+)"], "https" => true));
        try {
            $router->route();
            $this->fail();
        } catch(Error404Exception $e) {
            $this->assertTrue(true);
        }
    }

    public function testAsset() {
        $router = Router::getInstance();
        $result = $router->getRoot()."images/rei.jpg";
        $this->assertEquals($router->asset("images/rei.jpg"), $result);
    }

    public function testAssetFunc(){
        $this->assertEquals(asset("images/rei.jpg"), Router::getInstance()->getRoot()."images/rei.jpg");
    }

    /**
     * @runInSeparateProcess
     * @preserverGlobalState
     */
    public function testCurrentRoute(){
        $_SERVER["REQUEST_URI"] = "/current_route_test";
        $router = Router::getInstance();
        $router->add("current_route_test", "/current_route_test", function(){});
        $router->route();
        $this->assertEquals($router->getCurrent(), "current_route_test");
    }

    /**
     * @runInSeparateProcess
     * @dataProvider verbProvider
     * @preserveGlobalState
     */
    public function testValidVerb($route_url, $params, $url, $verb, $expected) {
        $_SERVER['REQUEST_METHOD'] = $verb;
        $request = Request::getInstance();
        $request->server->set('REQUEST_URI',$url);
        $router = Router::getInstance();
        $router->add("verb", $route_url, function() {}, $params);
        try {
            $this->assertEquals($router->route(), $expected);
        } catch(Error404Exception $e) {
            if($expected){
                $this->fail();
            } else {
                $this->assertFalse($expected);
            }
        }
    }

    /**
     * @runInSeparateProcess
     * @dataProvider linkProvider
     */
    public function testRedirectWithHeadersAlreadySent($route_url, $params, $url) {
        $result = dirname($_SERVER["SCRIPT_FILENAME"]).$url;
        ob_start();
        $router = Router::getInstance();
        $router->add("redirectTo", $route_url, function() {});
        $expected = "<meta http-equiv='refresh' content='0, URL=".$result."'>";
        $router->redirect("redirectTo", $params);
        $result = ob_get_clean();
        $this->assertEquals($expected, $result);
    }


    /**
     * @runInSeparateProcess
     * @dataProvider linkProvider
     */
    public function testRedirectFunc($route_url, $params, $url) {
        $result = dirname($_SERVER["SCRIPT_FILENAME"]).$url;
        ob_start();
        Router::getInstance()->add("redirectTo", $route_url, function() {});
        $expected = "<meta http-equiv='refresh' content='0, URL=".$result."'>";
        redirect("redirectTo", $params);
        $result = ob_get_clean();
        $this->assertEquals($expected, $result);
    }

    /**
     * @runInSeparateProcess
     * @dataProvider linkProvider
     */
    public function testLink($route_url, $params, $url) {
        $result = dirname($_SERVER["SCRIPT_FILENAME"]).$url;
        $request = Request::getInstance();
        $request->server->set('REQUEST_URI', "/");
        $router = Router::getInstance();
        $router->add("users", $route_url, function() {});
        $this->assertEquals($router->link("users", $params), $result);
    }

    /**
     * @runInSeparateProcess
     * @dataProvider linkProvider
     */
    public function testLinkFunc($route_url, $params, $url) {
        $result = dirname($_SERVER["SCRIPT_FILENAME"]).$url;
        $request = Request::getInstance();
        $request->server->set('REQUEST_URI', "/");
        $router = Router::getInstance();
        $router->add("users", $route_url, function() {});
        $this->assertEquals(route("users", $params), $result);
    }

    /**
     * @runInSeparateProcess
     * @dataProvider linkProvider
     */
    public function testHasRoutesAndGetRoutes()
    {
        $router = Router::getInstance();
        $this->assertFalse($router->hasRoutes());
        $this->assertEmpty($router->getRoutes());
        $router->add("test", "/test", function(){});
        $this->assertTrue($router->hasRoutes());
        $this->assertEquals(count($router->getRoutes()), 1);
    }

    public function routeProvider() {
        return array(
            ["/users", "/users", true],
            ["/users", "/users/", true],
            ["/users", "/users/?user=abc", true],
            ["/users/{username}", "/users", false],
            ["/users/{username}", "/users/", false],
            ["/users/{username}", "/users/asdf", true],
            ["/users/{username}", "/users/123", true],
            ["/", "", true],
            ["/", "/", true],
            ["/{lang}/home", "/de/home", true],
            ["/{lang}/home", "/de/home/", true],
            ["/{lang}/home", "de/home/", true],
            ["/{lang}/home", "de/home", true],
            ["/test/(a|b|c)", "/test/a", true],
            ["/test/(a|b|c)", "/test/b", true],
            ["/test/(a|b|c)", "/test/c", true],
            ["/test/(a|b|c)", "/test/d", false],
            ["/", "/das/ist/sicher/nicht/home", false],
            ["/test", "/test/falsch", false]
        );
    }

    public function linkProvider() {
        return array(
            ["/users/{name}", ["name" => "Loas"], "/users/Loas"],
            ["/users/{name}/dashboard", ["name" => "Loas"], "/users/Loas/dashboard"],
            ["/messages", [], "/messages"]
        );
    }

    public function verbProvider() {
        return array(
            ["/verb/{name}", ["name" => "get", "verb" => "GET"], "/verb/get", "GET", true],
            ["/verb/{name}", ["name" => "post", "verb" => "POST"], "/verb/post", "POST", true],
            ["/test", ["verb" => "GET"], "/test", "GET", true],
            ["/verb/{name}", ["name" => "get", "verb" => "GET"], "/verb/get", "POST", false],
            ["/verb/{name}", ["name" => "post", "verb" => "POST"], "/verb/post", "GET", false],
            ["/test", ["verb" => "GET"], "/test", "PUT", false],
            ["/test", [], "/test", "GET", true],
            ["/test", [], "/test", "POST", true],
            ["/test", [], "/test", "PATCH", true],
            ["/test", [], "/test", "PUT", true],
            ["/test", [], "/test", "DELETE", true],
            ["/test", ["verb" => array("get", "post")], "/test", "ABCD", false]
        );
    }

}

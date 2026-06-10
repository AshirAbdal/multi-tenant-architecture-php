<?php
class Router {
    private static array $routes = [
        'POST /wl/auth/login'        => [AuthController::class,      'login'],
        'POST /wl/forms/contact'     => [ContactController::class,   'store'],
        'GET /wl/admin/submissions'  => [SubmissionController::class,'index'],
    ];

    public static function dispatch(string $method, string $path): void {
        $key = "$method $path";
        if (!isset(self::$routes[$key])) {
            respond(404, ['error' => 'Not found']);
        }
        [$class, $action] = self::$routes[$key];
        (new $class)->$action();
    }
}
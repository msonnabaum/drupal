<?php

namespace Drupal\Core\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class Base implements HttpKernelInterface {
  public function __construct($kernel) {
    $this->kernel = $kernel;
  }

  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true) {
    return $this->kernel->handle($request, $type, $catch);
  }

  public function __call($method, $args) {
    return call_user_func_array(array($this->kernel, $method), $args);
  }
}


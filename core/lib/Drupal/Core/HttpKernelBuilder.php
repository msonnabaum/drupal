<?php

namespace Drupal\Core;

use Symfony\Component\HttpFoundation\Request;

class HttpKernelBuilder {
  private $kernels = array();

  public function add($class, $args = array()) {
    $this->kernels[] = function ($next_kernel) use ($class, $args) {
      $reflect = new \ReflectionClass($class);
      array_unshift($args, $next_kernel);
      return $reflect->newInstanceArgs($args);
    };
  }

  public function build($main_kernel) {
    return array_reduce(array_reverse($this->kernels),
      function ($a, $e) use ($main_kernel) {
        $a = $a ?: $main_kernel;
        return $e($a);
      }
    );
  }
}

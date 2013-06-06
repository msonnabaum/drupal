<?php

namespace Drupal\Core\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Drupal\Component\Utility\Timer as TimerComponent;

class Timer extends Base {
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true) {
    TimerComponent::start('page');
    $response = $this->kernel->handle($request, $type, $catch);
    TimerComponent::stop('page');
    return $response;
  }
}



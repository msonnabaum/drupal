<?php

namespace Drupal\Core\Middleware;

use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class Configuration extends Base {
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true) {
    require_once DRUPAL_ROOT . '/core/includes/config.inc';

    // Set the Drupal custom error handler. (requires config())
    set_error_handler('_drupal_error_handler');
    set_exception_handler('_drupal_exception_handler');

    // Redirect the user to the installation script if Drupal has not been
    // installed yet (i.e., if no $databases array has been defined in the
    // settings.php file) and we are not already installing.
    if (!Database::hasConnectionInfo() && !drupal_installation_attempted()) {
      include_once __DIR__ . '/install.inc';
      return new RedirectResponse('/core/install.php');
    }
    return $this->kernel->handle($request, $type, $catch);
  }
}



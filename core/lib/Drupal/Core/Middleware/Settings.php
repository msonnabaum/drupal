<?php

namespace Drupal\Core\Middleware;

use Drupal\Component\Utility\Settings as DrupalSettings;
use Drupal\views\Plugin\views\argument_validator\Php;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Database\Database;
use Drupal\Core\Config\Config;
use Drupal\Core\EventSubscriber\ConfigGlobalOverrideSubscriber;
use Drupal\Core\KeyValueStore\KeyValueFactory;
use Drupal\Component\PhpStorage\PhpStorageFactory;

/**
 * Sets the base URL, cookie domain, and session name from configuration.
 */
class Settings extends Base {
  public $confPath;
  public $drupalRoot;

  public function __construct($kernel, $drupal_root) {
    $this->drupalRoot = $drupal_root;
    parent::__construct($kernel);
  }

  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE) {
    // @todo: Look at removing after #1888424 goes in.
    global $base_url, $base_path, $script_path;

    // Export these settings.php variables to the global namespace.
    global $conf;
    $conf = array();

    // Make conf_path() available as local variable in settings.php.
    $conf_path = $this->confPath($request);
    if (is_readable($this->drupalRoot . '/' . $conf_path . '/settings.php')) {
      include_once $this->drupalRoot . '/' . $conf_path . '/settings.php';
    }

    Database::setConnectionInfo($databases);
    Config::setDirectories($config_directories);
    //ConfigGlobalOverrideSubscriber::setConfOverrides($conf);
    \Drupal::setHashSalt($drupal_hash_salt ?: $this->defaultHashSalt($databases));
    //PhpStorageFactory::setConfiguration($conf, $conf_path);
    //KeyValueFactory::setConfiguration($conf, $conf_path);

    require_once $this->drupalRoot . '/core/lib/Drupal/Component/Utility/Settings.php';
    $set = new DrupalSettings(isset($settings) ? $settings : array());

    if (isset($base_url)) {
      list($base_path, $base_root) = $this->parseFixedBaseUrl($base_url);
    }
    else {
      list($base_root, $base_url, $base_path) = $this->createBaseUrl($request);
    }

    // @todo: Remove once #1888424 goes in.
    global $base_secure_url, $base_insecure_url;
    $base_secure_url = str_replace('http://', 'https://', $base_url);
    $base_insecure_url = str_replace('https://', 'http://', $base_url);

    // Determine the path of the script relative to the base path, and add a
    // trailing slash. This is needed for creating URLs to Drupal pages.
    if (!isset($script_path)) {
      $script_path = $this->scriptPath($request->getScriptName(), $request->getRequestUri(), $base_path);
    }

    /*
    $blah = array(
      $request->getUri(),
      $request->getSchemeAndHttpHost(),
      $request->getBaseUrl(),
      $request->getRequestUri(),
      $request->getHttpHost(),
      $request->getPathInfo(),
      $request->getBaseUrl(),
      $request->getBasePath(),
      $request->getQueryString(),
      $request->getScriptName()
    );
    */
    $base_root = $request->getSchemeAndHttpHost();
    $cookie_domain = isset($cookie_domain) ? $cookie_domain : NULL;
    session_name($this->sessionName($request->getHttpHost(), $request->isSecure(), $cookie_domain, $base_url));

    return $this->kernel->handle($request, $type, $catch);
  }

  protected function confPath(Request $request, $require_settings = TRUE, $reset = FALSE) {
    if ($this->confPath && !$reset) {
      return $this->confPath;
    }

    // Check for a simpletest override.
    if ($simpletest_conf_path = _drupal_simpletest_conf_path()) {
      $this->confPath = $simpletest_conf_path;
      return $this->confPath;
    }

    // Otherwise, use the normal $conf_path.
    $script_name = $request->getScriptName();
    if (!$script_name) {
      $script_name = $request->server->get('SCRIPT_FILENAME');
    }
    return $this->findConfPath($request->getHttpHost(), $script_name, $require_settings);
  }

  /**
   * Finds the appropriate configuration directory for a given host and path.
   *
   * Finds a matching configuration directory file by stripping the website's
   * hostname from left to right and pathname from right to left. By default,
   * the directory must contain a 'settings.php' file for it to match. If the
   * parameter $require_settings is set to FALSE, then a directory without a
   * 'settings.php' file will match as well. The first configuration
   * file found will be used and the remaining ones will be ignored. If no
   * configuration file is found, returns a default value '$confdir/default'. See
   * default.settings.php for examples on how the URL is converted to a directory.
   *
   * If a file named sites.php is present in the $confdir, it will be loaded
   * prior to scanning for directories. That file can define aliases in an
   * associative array named $sites. The array is written in the format
   * '<port>.<domain>.<path>' => 'directory'. As an example, to create a
   * directory alias for http://www.drupal.org:8080/mysite/test whose configuration
   * file is in sites/example.com, the array should be defined as:
   * @code
   * $sites = array(
   *   '8080.www.drupal.org.mysite.test' => 'example.com',
   * );
   * @endcode
   *
   * @param $http_host
   *   The hostname and optional port number, e.g. "www.example.com" or
   *   "www.example.com:8080".
   * @param $script_name
   *   The part of the URL following the hostname, including the leading slash.
   * @param $require_settings
   *   Defaults to TRUE. If TRUE, then only match directories with a
   *   'settings.php' file. Otherwise match any directory.
   *
   * @return
   *   The path of the matching configuration directory.
   *
   * @see default.settings.php
   * @see example.sites.php
   * @see conf_path()
   */
  protected function findConfPath($http_host, $script_name, $require_settings = TRUE) {
    // Determine whether multi-site functionality is enabled.
    if (!file_exists($this->drupalRoot . '/sites/sites.php')) {
      return 'sites/default';
    }

    $sites = array();
    include $this->drupalRoot . '/sites/sites.php';

    $uri = explode('/', $script_name);
    $server = explode('.', implode('.', array_reverse(explode(':', rtrim($http_host, '.')))));
    for ($i = count($uri) - 1; $i > 0; $i--) {
      for ($j = count($server); $j > 0; $j--) {
        $dir = implode('.', array_slice($server, -$j)) . implode('.', array_slice($uri, 0, $i));
        if (isset($sites[$dir]) && file_exists($this->drupalRoot . '/sites/' . $sites[$dir])) {
          $dir = $sites[$dir];
        }
        if (file_exists($this->drupalRoot . '/sites/' . $dir . '/settings.php') || (!$require_settings && file_exists($this->drupalRoot . '/sites/' . $dir))) {
          return "sites/$dir";
        }
      }
    }
    return 'sites/default';
  }

  /**
   * @param Request $request
   * @param $cookie_domain
   * @param $base_url
   * @return string
   *   The name to use for the current session.
   */
  public function sessionName($http_host, $is_secure, $cookie_domain, $base_url) {
    if ($cookie_domain) {
      // If the user specifies the cookie domain, also use it for session name.
      $session_name = $cookie_domain;
    }
    else {
      // Otherwise use $base_url as session name, without the protocol
      // to use the same session identifiers across HTTP and HTTPS.
      list(, $session_name) = explode('://', $base_url, 2);
      // HTTP_HOST can be modified by a visitor, but we already sanitized it
      // in drupal_settings_initialize().
      if (!empty($http_host)) {
        $cookie_domain = $http_host;
        // Strip leading periods, www., and port numbers from cookie domain.
        $cookie_domain = ltrim($cookie_domain, '.');
        if (strpos($cookie_domain, 'www.') === 0) {
          $cookie_domain = substr($cookie_domain, 4);
        }
        $cookie_domain = explode(':', $cookie_domain);
        $cookie_domain = '.' . $cookie_domain[0];
      }
    }
    // Per RFC 2109, cookie domains must contain at least one dot other than the
    // first. For hosts such as 'localhost' or IP Addresses we don't set a cookie domain.
    if (count(explode('.', $cookie_domain)) > 2 && !is_numeric(str_replace('.', '', $cookie_domain))) {
      ini_set('session.cookie_domain', $cookie_domain);
    }
    // To prevent session cookies from being hijacked, a user can configure the
    // SSL version of their website to only transfer session cookies via SSL by
    // using PHP's session.cookie_secure setting. The browser will then use two
    // separate session cookies for the HTTPS and HTTP versions of the site. So we
    // must use different session identifiers for HTTPS and HTTP to prevent a
    // cookie collision.
    if ($is_secure) {
      ini_set('session.cookie_secure', TRUE);
    }
    $prefix = ini_get('session.cookie_secure') ? 'SSESS' : 'SESS';
    return $prefix . substr(hash('sha256', $session_name), 0, 32);
  }

  /**
   * @param Request $request
   * @param $script_path
   * @param $base_path
   * @return string
   */
  public function scriptPath($script_name, $request_uri, $base_path) {
    $script_path = '';
    // We don't expect scripts outside of the base path, but sanity check
    // anyway.
    if (strpos($script_name, $base_path) === 0) {
      $script_path = substr($script_name, strlen($base_path)) . '/';
      // If the request URI does not contain the script name, then clean URLs
      // are in effect and the script path can be similarly dropped from URL
      // generation. For servers that don't provide $_SERVER['REQUEST_URI'], we
      // do not know the actual URI requested by the client, and request_uri()
      // returns a URI with the script name, resulting in non-clean URLs unless
      // there's other code that intervenes.
      if (strpos($request_uri . '/', $base_path . $script_path) !== 0) {
        $script_path = '';
      }
      // @todo Temporary BC for install.php, update.php, and other scripts.
      //   - http://drupal.org/node/1547184
      //   - http://drupal.org/node/1546082
      if ($script_path !== 'index.php/') {
        $script_path = '';
      }
    }
    return $script_path;
  }

  /**
   * Create base URL
   *
   * @param Request $request
   * @return array
   */
  protected function createBaseUrl(Request $request) {
    $base_root = $base_url = $request->getSchemeAndHttpHost();

    // For a request URI of '/index.php/foo', $_SERVER['SCRIPT_NAME'] is
    // '/index.php', whereas $_SERVER['PHP_SELF'] is '/index.php/foo'.
    if ($dir = rtrim(dirname($request->getScriptName()), '\/')) {
      // Remove "core" directory if present, allowing install.php, update.php,
      // and others to auto-detect a base path.
      $core_position = strrpos($dir, '/core');
      if ($core_position !== FALSE && strlen($dir) - 5 == $core_position) {
        $base_path = substr($dir, 0, $core_position);
      }
      else {
        $base_path = $dir;
      }
      $base_url .= $base_path;
      $base_path .= '/';
    }
    else {
      $base_path = '/';
    }
    return array($base_root, $base_url, $base_path);
  }

  /**
   * Parse fixed base URL from settings.php.
   *
   * @param $base_url
   * @return array
   */
  protected function parseFixedBaseUrl($base_url) {
    $parts = parse_url($base_url);
    if (!isset($parts['path'])) {
      $parts['path'] = '';
    }
    $base_path = $parts['path'] . '/';
    // Build $base_root (everything until first slash after "scheme://").
    $base_root = substr($base_url, 0, strlen($base_url) - strlen($parts['path']));
    return array($base_path, $base_root);
  }

  /**
   * If the $drupal_hash_salt variable is empty, a hash of the serialized
   * database credentials is used as a fallback salt.
   * @param $drupal_hash_salt
   * @param $databases
   * @return string
   */
  protected function defaultHashSalt($databases) {
    return hash('sha256', serialize($databases));
  }
}

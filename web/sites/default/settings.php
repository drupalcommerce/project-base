<?php
/**
 * @file
 * Platform.sh example settings.php file for Drupal 8.
 */

// Install with the 'standard' profile for this example.
$settings['install_profile'] = 'commerce_base';
// You should modify the hash_salt so that it is specific to your application.
$settings['hash_salt'] = '4946c1912834b8477cc70af309a2c30dcec24c2103c724ff30bf13b4c10efd82';

/**
 * Default Drupal 8 settings.
 *
 * These are already explained with detailed comments in Drupal's
 * default.settings.php file.
 *
 * See https://api.drupal.org/api/drupal/sites!default!default.settings.php/8
 */
$databases = array();
$config_directories = array();
$settings['update_free_access'] = FALSE;
$settings['container_yamls'][] = __DIR__ . '/services.yml';

// Override paths for config files in Platform.sh.
// Define a config sync directory outside the document root.
if (isset($_ENV['PLATFORM_APP_DIR'])) {
  $config_directories[CONFIG_SYNC_DIRECTORY] = $_ENV['PLATFORM_APP_DIR'] . '/config/sync';
}

// Set trusted hosts based on real Platform.sh routes.
if (isset($_ENV['PLATFORM_ROUTES'])) {
  $routes = json_decode(base64_decode($_ENV['PLATFORM_ROUTES']), TRUE);
  $settings['trusted_host_patterns'] = array();
  foreach ($routes as $url => $route) {
    $host = parse_url($url, PHP_URL_HOST);
    if ($host !== FALSE && $route['type'] == 'upstream' && $route['upstream'] == $_ENV['PLATFORM_APPLICATION_NAME']) {
      $settings['trusted_host_patterns'][] = '^' . preg_quote($host) . '$';
    }
  }
  $settings['trusted_host_patterns'] = array_unique($settings['trusted_host_patterns']);
}
// Because we're using the Composer toolstack, we replicate the necessary local
// settings file for Drupal here.
if (getenv("PLATFORM_RELATIONSHIPS")) {
  // Configure relationships.
  $relationships = json_decode(base64_decode($_ENV['PLATFORM_RELATIONSHIPS']), TRUE);
  if (empty($databases['default']['default'])) {
    foreach ($relationships['database'] as $endpoint) {
      $database = array(
        'driver' => $endpoint['scheme'],
        'database' => $endpoint['path'],
        'username' => $endpoint['username'],
        'password' => $endpoint['password'],
        'host' => $endpoint['host'],
      );
      if (!empty($endpoint['query']['compression'])) {
        $database['pdo'][PDO::MYSQL_ATTR_COMPRESS] = TRUE;
      }
      if (!empty($endpoint['query']['is_master'])) {
        $databases['default']['default'] = $database;
      }
      else {
        $databases['default']['slave'][] = $database;
      }
    }
  }
  $routes = json_decode(base64_decode($_ENV['PLATFORM_ROUTES']), TRUE);
  if (!isset($conf['file_private_path'])) {
    if(!$application_home = getenv('PLATFORM_APP_DIR')) {
      $application_home = '/app';
    }
    $conf['file_private_path'] = $application_home . '/private';
    $conf['file_temporary_path'] = $application_home . '/tmp';
  }
  $variables = json_decode(base64_decode($_ENV['PLATFORM_VARIABLES']), TRUE);
  $prefix_len = strlen('drupal:');
  foreach ($variables as $name => $value) {
    if (substr($name, 0, $prefix_len) == 'drupal:') {
      $conf[substr($name, $prefix_len)] = $value;
    }
  }
  // Default PHP settings.
  ini_set('session.gc_probability', 1);
  ini_set('session.gc_divisor', 100);
  ini_set('session.gc_maxlifetime', 200000);
  ini_set('session.cookie_lifetime', 2000000);
  ini_set('pcre.backtrack_limit', 200000);
  ini_set('pcre.recursion_limit', 200000);
}

// Force Drupal not to check for HTTP connectivity until we fixed the self test.
$conf['drupal_http_request_fails'] = FALSE;

// Local settings. These allow local development environments to use their own
// database connections rather than the Platform-only settings above.
if (file_exists(__DIR__ . '/settings.local.php')) {
  include __DIR__ . '/settings.local.php';
}

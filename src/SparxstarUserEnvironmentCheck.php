<?php
namespace Starisian\SparxstarUEC;

if (!defined('ABSPATH')) {
  exit;
}

// We load the component files here, right before the class that will use them.
require_once SPX_ENV_CHECK_PLUGIN_PATH . 'src/api/SparxstarUECAPI.php';
require_once SPX_ENV_CHECK_PLUGIN_PATH . 'src/AssetManager.php';

/**
 * Main plugin class for SPARXSTAR User Environment Check.
 * This class handles the initialization and setup of the plugin's components.
 * It acts as the "Orchestrator" for the plugin.
 */
class SparxstarUserEnvironmentCheck
{

  private static ?self $instance = null;
  private api\SparxstarUECAPI $api;

  public static function get_instance(): self
  {
    if (null === self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Constructor - This is where the orchestration happens.
   */
  private function __construct()
  {
    // Initialize the API (which sets up REST routes and cron jobs).
    $this->api = api\SparxstarUECAPI::init();

    // Initialize the Asset Manager (which sets up script enqueuing).
    new AssetManager();

    // Register the hooks that are managed directly by this main class.
    $this->register_hooks();
  }

  /**
   * Registers the main plugin's hooks.
   */
  private function register_hooks(): void
  {
    // Hook for loading translation files.
    add_action('init', [$this, 'load_textdomain']);

    // Hook for adding the Client Hints header to front-end requests.
    add_action('send_headers', [$this, 'add_client_hints_header']);
  }

  /**
   * Loads the plugin's translated strings.
   */
  public function load_textdomain(): void
  {
    load_plugin_textdomain(
      SPX_ENV_CHECK_TEXT_DOMAIN,
      false,
      dirname(plugin_basename(SPX_ENV_CHECK_PLUGIN_FILE)) . '/languages'
    );
  }

  /**
   * Adds the Accept-CH header to tell browsers to send Client Hints.
   */
  public function add_client_hints_header(): void
  {
    if (is_admin()) {
      return;
    }
    header("Accept-CH: Sec-CH-UA, Sec-CH-UA-Mobile, Sec-CH-UA-Platform, Sec-CH-UA-Model, Sec-CH-UA-Full-Version, Sec-CH-UA-Platform-Version, Sec-CH-UA-Bitness");
  }

  /**
   * Public accessor for the API instance.
   * This is needed so other parts of our plugin (like StarUserUtils) can get data from the database.
   */
  public function get_api(): api\SparxstarUECAPI
  {
    return $this->api;
  }
}
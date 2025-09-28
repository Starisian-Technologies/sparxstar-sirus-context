<?php
/**
 * Environment Check REST API Handler
 *
 * Handles the collection and storage of environment diagnostics with enhanced
 * security, session awareness, and a database-first architecture. It also
 * intelligently handles both full snapshots and partial 'delta' updates.
 *
 * @package SparxstarUserEnvironmentCheck
 * @version 7.0.0
 */
namespace Starisian\SparxstarUEC\api;

if (!defined('ABSPATH')) {
	exit;
}

class SparxstarUECAPI
{

	private static ?self $instance = null;

	private const RATE_LIMIT_WINDOW_SECONDS = 300;
	private const RATE_LIMIT_MAX_REQUESTS = 15;
	private const TABLE_NAME = 'sparxstar_env_snapshots';
	private const SNAPSHOT_RETENTION_DAYS = 30;
	private const CLEANUP_HOOK = 'sparxstar_env_cleanup_snapshots';

	public static function init(): self
	{
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct()
	{
		\add_action('rest_api_init', [$this, 'register_rest_route']);
		\add_action('init', [$this, 'schedule_cleanup_action']);
		\add_action(self::CLEANUP_HOOK, [$this, 'cleanup_old_snapshots']);
	}

	public function create_db_table(): void
	{
		global $wpdb;
		$table_name = $wpdb->base_prefix . self::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
			session_id VARCHAR(128) NULL DEFAULT NULL,
			snapshot_hash VARCHAR(64) NOT NULL,
			client_ip_hash VARCHAR(64) NOT NULL,
			server_side_data JSON NOT NULL,
			client_side_data JSON NOT NULL,
			client_hints_data JSON NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY snapshot_hash (snapshot_hash),
			KEY user_session (user_id, session_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);
	}

	public function register_rest_route()
	{
		\register_rest_route(
			'sparxstar-env/v1',
			'/log',
			[
				'methods' => 'POST',
				'callback' => [$this, 'handle_log_request'],
				'permission_callback' => '__return_true',
			]
		);
	}

	public function handle_log_request(\WP_REST_Request $request)
	{
		$nonce = $request->get_header('X-WP-Nonce');
		if (!$nonce || !\wp_verify_nonce($nonce, 'wp_rest')) {
			return new \WP_Error('invalid_nonce', \__('Invalid security token.', SPX_ENV_CHECK_TEXT_DOMAIN), ['status' => 403]);
		}
		if (!$this->check_rate_limit()) {
			return new \WP_Error('rate_limited', \__('Too many requests.', SPX_ENV_CHECK_TEXT_DOMAIN), ['status' => 429]);
		}

		$payload = $request->get_json_params();
		if (empty($payload) || !is_array($payload)) {
			return new \WP_Error('invalid_data', \__('Invalid JSON payload.', SPX_ENV_CHECK_TEXT_DOMAIN), ['status' => 400]);
		}

		if (isset($payload['delta'])) {
			$session_id = !empty($payload['sessionId']) ? sanitize_text_field($payload['sessionId']) : null;
			$latest_snapshot = $this->get_latest_snapshot(null, $session_id);

			if (!$latest_snapshot) {
				return new \WP_Error('no_snapshot_for_delta', \__('Cannot apply a delta without an existing snapshot.', SPX_ENV_CHECK_TEXT_DOMAIN), ['status' => 409]);
			}
			$client_data_for_update = array_replace_recursive($latest_snapshot['client_side_data'], $payload['delta']);
			$server_data_for_update = $latest_snapshot['server_side_data'];
			$hints_data_for_update = $latest_snapshot['client_hints_data'];
		} else {
			$client_data_for_update = $this->sanitize_array_recursively($payload);
			$server_data_for_update = $this->collect_server_side_data();
			$hints_data_for_update = $this->collect_client_hints();
		}

		$user_id = get_current_user_id() ?: null;
		$session_id = $client_data_for_update['sessionId'] ?? null;
		$client_ip = $this->get_client_ip();
		$client_ua = $client_data_for_update['device']['userAgent'] ?? $this->get_user_agent();

		$env_hash_data = [
			'user_id' => $user_id,
			'session_id' => $session_id,
			'server_side' => $server_data_for_update,
			'client_side' => $client_data_for_update,
			'client_hints' => $hints_data_for_update,
		];
		unset($env_hash_data['client_side']['network']['rtt'], $env_hash_data['client_side']['battery']);
		$snapshot_hash = hash('sha256', wp_json_encode($env_hash_data));

		$result = $this->store_diagnostic_snapshot(
			$user_id,
			$session_id,
			hash('sha256', $client_ip),
			$snapshot_hash,
			$server_data_for_update,
			$client_data_for_update,
			$hints_data_for_update
		);

		if (!is_wp_error($result) && class_exists('\Starisian\SparxstarUEC\StarUserUtils')) {
			\Starisian\SparxstarUEC\StarUserUtils::flush_cache();
		}

		if (is_wp_error($result))
			return $result;
		return new \WP_REST_Response(['status' => 'ok', 'action' => $result['status'], 'id' => $result['id']], 200);
	}

	public function get_latest_snapshot(?int $user_id = null, ?string $session_id = null): ?array
	{
		global $wpdb;
		$table_name = $wpdb->base_prefix . self::TABLE_NAME;
		$user_id = $user_id ?? \get_current_user_id();
		$where_clauses = [];
		$params = [];

		if ($user_id) {
			$where_clauses[] = 'user_id = %d';
			$params[] = $user_id;
		} else {
			$where_clauses[] = 'client_ip_hash = %s';
			$params[] = \hash('sha256', $this->get_client_ip());
		}
		if ($session_id) {
			$where_clauses[] = 'session_id = %s';
			$params[] = $session_id;
		}
		if (empty($where_clauses))
			return null;

		$sql = $wpdb->prepare("SELECT * FROM {$table_name} WHERE " . implode(' AND ', $where_clauses) . ' ORDER BY updated_at DESC LIMIT 1', ...$params);
		$snapshot = $wpdb->get_row($sql, ARRAY_A);

		if (!$snapshot)
			return null;

		$snapshot['server_side_data'] = \json_decode($snapshot['server_side_data'], true);
		$snapshot['client_side_data'] = \json_decode($snapshot['client_side_data'], true);
		$snapshot['client_hints_data'] = \json_decode($snapshot['client_hints_data'], true);
		return $snapshot;
	}

	private function store_diagnostic_snapshot(?int $user_id, ?string $session_id, string $client_ip_hash, string $snapshot_hash, array $server_data, array $client_data, array $client_hints): array|\WP_Error
	{
		global $wpdb;
		$table_name = $wpdb->base_prefix . self::TABLE_NAME;

		$existing_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table_name} WHERE snapshot_hash = %s", $snapshot_hash));
		if ($existing_id) {
			$wpdb->update($table_name, ['updated_at' => \current_time('mysql')], ['id' => $existing_id]);
			return ['status' => 'updated', 'id' => $existing_id];
		}

		$result = $wpdb->insert(
			$table_name,
			[
				'user_id' => $user_id,
				'session_id' => $session_id,
				'snapshot_hash' => $snapshot_hash,
				'client_ip_hash' => $client_ip_hash,
				'server_side_data' => \wp_json_encode($server_data),
				'client_side_data' => \wp_json_encode($client_data),
				'client_hints_data' => !empty($client_hints) ? \wp_json_encode($client_hints) : null,
				'created_at' => \current_time('mysql'),
				'updated_at' => \current_time('mysql'),
			],
			['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
		);

		if (false === $result) {
			return new \WP_Error('db_insert_error', \__('Could not write snapshot to the database.', SPX_ENV_CHECK_TEXT_DOMAIN), ['status' => 500]);
		}
		return ['status' => 'inserted', 'id' => $wpdb->insert_id];
	}

	public function schedule_cleanup_action(): void
	{
		if (!function_exists('as_schedule_recurring_action'))
			return;
		if (false === as_next_scheduled_action(self::CLEANUP_HOOK)) {
			as_schedule_recurring_action(time(), DAY_IN_SECONDS, self::CLEANUP_HOOK);
		}
	}

	public function cleanup_old_snapshots(): void
	{
		global $wpdb;
		$table_name = $wpdb->base_prefix . self::TABLE_NAME;
		$retention_days = (int) \apply_filters('sparxstar_env_retention_days', self::SNAPSHOT_RETENTION_DAYS);

		if ($retention_days > 0) {
			$wpdb->query($wpdb->prepare("DELETE FROM {$table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)", $retention_days));
		}
	}

	private function collect_server_side_data(): array
	{
		return [
			'ipAddress' => $this->get_client_ip(),
			'language' => \get_locale(),
			'serverTimeUTC' => gmdate('c'),
		];
	}

	private function collect_client_hints(): array
	{
		$client_hints = [];
		$hint_headers = \apply_filters(
			'sparxstar_env_client_hint_headers',
			[
				'Sec-CH-UA',
				'Sec-CH-UA-Mobile',
				'Sec-CH-UA-Platform',
				'Sec-CH-UA-Platform-Version',
				'Sec-CH-UA-Arch',
				'Sec-CH-UA-Bitness',
				'Sec-CH-UA-Model',
				'Sec-CH-UA-Full-Version',
			]
		);

		foreach ($hint_headers as $header) {
			$server_key = 'HTTP_' . \strtoupper(\str_replace('-', '_', $header));
			if (!empty($_SERVER[$server_key])) {
				$client_hints[$header] = \sanitize_text_field(\wp_unslash($_SERVER[$server_key]));
			}
		}
		return $client_hints;
	}

	private function check_rate_limit(): bool
	{
		$rate_key = 'sparxstar_env_rate_' . \hash('md5', $this->get_client_ip());
		$current_requests = (int) \get_transient($rate_key);
		if ($current_requests >= self::RATE_LIMIT_MAX_REQUESTS) {
			return false;
		}
		\set_transient($rate_key, $current_requests + 1, self::RATE_LIMIT_WINDOW_SECONDS);
		return true;
	}

	private function sanitize_array_recursively(array $array): array
	{
		$sanitized = [];
		foreach ($array as $key => $value) {
			$key = \sanitize_key($key);
			if (is_array($value)) {
				$sanitized[$key] = $this->sanitize_array_recursively($value);
			} elseif (is_scalar($value)) {
				$sanitized[$key] = \sanitize_text_field((string) $value);
			} else {
				$sanitized[$key] = $value;
			}
		}
		return $sanitized;
	}

	private function get_client_ip(): string
	{
		$ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
		foreach ($ip_keys as $key) {
			if (!empty($_SERVER[$key])) {
				$ip = \explode(',', \sanitize_text_field(\wp_unslash($_SERVER[$key])))[0];
				if (\filter_var(\trim($ip), FILTER_VALIDATE_IP)) {
					return \trim($ip);
				}
			}
		}
		return 'unknown';
	}

	private function get_user_agent(): string
	{
		return \sanitize_text_field(\wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
	}
}
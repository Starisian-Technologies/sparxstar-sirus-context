# StarLogger

**Namespace:** `Starisian\SparxstarUEC\helpers`

**Full Class Name:** `Starisian\SparxstarUEC\helpers\StarLogger`

## Description

Centralized, extensible error and debug logger for Star.
Retains full backward compatibility while adding:
 - JSON mode for structured logs
 - Correlation ID support
 - Execution timers
 - PII masking for safe logs
 - Alert hooks for external integrations
 - Log rotation & maintenance helpers
@version 0.8.5

## Methods

### `init()`

Centralized, extensible error and debug logger for Star.
Retains full backward compatibility while adding:
 - JSON mode for structured logs
 - Correlation ID support
 - Execution timers
 - PII masking for safe logs
 - Alert hooks for external integrations
 - Log rotation & maintenance helpers
@version 0.8.5
/
class StarLogger
{
    // --- Existing log level constants ---
    public const DEBUG     = 100;

    public const INFO      = 200;

    public const NOTICE    = 250;

    public const WARNING   = 300;

    public const ERROR     = 400;

    public const CRITICAL  = 500;

    public const ALERT     = 550;

    public const EMERGENCY = 600;

    protected static ?string $log_file_path = null;

    protected static int $min_log_level     = self::INFO;

    protected static bool $initialized      = false;

    protected static array $levels = [
        'debug'     => self::DEBUG,
        'info'      => self::INFO,
        'notice'    => self::NOTICE,
        'warning'   => self::WARNING,
        'error'     => self::ERROR,
        'critical'  => self::CRITICAL,
        'alert'     => self::ALERT,
        'emergency' => self::EMERGENCY,
    ];

    // --- New features ---
    protected static bool $json_mode         = false;

    protected static ?string $correlation_id = null;

    protected static array $timers           = [];

    /*==============================================================
INITIALIZATION
=============================================================


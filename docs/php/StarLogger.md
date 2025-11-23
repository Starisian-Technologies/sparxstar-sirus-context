# StarLogger

**Namespace:** `Starisian\SparxstarUEC\helpers`

**Full Class Name:** `Starisian\SparxstarUEC\helpers\StarLogger`

## Description

Centralized logger for SPARXSTAR UEC.
Version 1.0.0: Standardized. Writes strictly to wp-content/debug.log via error_log().

## Properties

### `$min_log_level`

Centralized logger for SPARXSTAR UEC.
Version 1.0.0: Standardized. Writes strictly to wp-content/debug.log via error_log().
/
class StarLogger
{
    public const DEBUG = 100;

    public const INFO = 200;

    public const NOTICE = 250;

    public const WARNING = 300;

    public const ERROR = 400;

    public const CRITICAL = 500;

    public const ALERT = 550;

    public const EMERGENCY = 600;

    /**
Minimum log level to record.

## Methods

### `setMinLogLevel(string $level_name)`

Centralized logger for SPARXSTAR UEC.
Version 1.0.0: Standardized. Writes strictly to wp-content/debug.log via error_log().
/
class StarLogger
{
    public const DEBUG = 100;

    public const INFO = 200;

    public const NOTICE = 250;

    public const WARNING = 300;

    public const ERROR = 400;

    public const CRITICAL = 500;

    public const ALERT = 550;

    public const EMERGENCY = 600;

    /**
Minimum log level to record.
/
    protected static int $min_log_level = self::INFO;

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

    protected static bool $json_mode = false;

    protected static ?string $correlation_id = null;

    protected static array $timers = [];

    /*==============================================================
CONFIGURATION
=============================================================

### `setLogFilePath(string $path)`

Legacy method kept for backward compatibility.
Does nothing as we now rely on standard WP debug.log.

### `log(string $context, $msg, string $level = 'error', array $extra = [])`

Main logging method.
Writes directly to PHP error_log (standard WP debug.log).


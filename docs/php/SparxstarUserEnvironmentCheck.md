# SparxstarUserEnvironmentCheck

**Namespace:** `Starisian\SparxstarUEC`

**Full Class Name:** `Starisian\SparxstarUEC\SparxstarUserEnvironmentCheck`

## Description

Legacy orchestrator — maintained for backward compatibility with
SPARXSTAR Sirus — Context Engine.
@package Starisian\SparxstarUEC
/

declare(strict_types=1);

namespace Starisian\SparxstarUEC;

if (! defined('ABSPATH')) {
    exit;
}

use Throwable;
use LogicException;
use Starisian\SparxstarUEC\helpers\StarLogger;
use Starisian\SparxstarUEC\core\SparxstarUECKernel;
use Starisian\SparxstarUEC\api\SparxstarUECRESTController;

/**
Orchestrates plugin services and exposes shared dependencies.
This is a thin WordPress integration layer - the Kernel handles service construction.

## Properties

### `$instance`

Legacy orchestrator — maintained for backward compatibility with
SPARXSTAR Sirus — Context Engine.
@package Starisian\SparxstarUEC
/

declare(strict_types=1);

namespace Starisian\SparxstarUEC;

if (! defined('ABSPATH')) {
    exit;
}

use Throwable;
use LogicException;
use Starisian\SparxstarUEC\helpers\StarLogger;
use Starisian\SparxstarUEC\core\SparxstarUECKernel;
use Starisian\SparxstarUEC\api\SparxstarUECRESTController;

/**
Orchestrates plugin services and exposes shared dependencies.
This is a thin WordPress integration layer - the Kernel handles service construction.
/
final class SparxstarUserEnvironmentCheck
{
    /**
Shared singleton instance.

### `$api`

REST API handler used to persist environment snapshots.

## Methods

### `spx_uec_get_instance()`

Legacy orchestrator — maintained for backward compatibility with
SPARXSTAR Sirus — Context Engine.
@package Starisian\SparxstarUEC
/

declare(strict_types=1);

namespace Starisian\SparxstarUEC;

if (! defined('ABSPATH')) {
    exit;
}

use Throwable;
use LogicException;
use Starisian\SparxstarUEC\helpers\StarLogger;
use Starisian\SparxstarUEC\core\SparxstarUECKernel;
use Starisian\SparxstarUEC\api\SparxstarUECRESTController;

/**
Orchestrates plugin services and exposes shared dependencies.
This is a thin WordPress integration layer - the Kernel handles service construction.
/
final class SparxstarUserEnvironmentCheck
{
    /**
Shared singleton instance.
/
    private static ?SparxstarUserEnvironmentCheck $instance = null;

    /**
REST API handler used to persist environment snapshots.
/
    private ?SparxstarUECRESTController $api = null;

    /**
Retrieve the singleton instance and bootstrap the plugin.

### `__construct()`

Wire the plugin components together via the Kernel.

### `register_hooks()`

Attach WordPress hooks owned by the bootstrapper.

### `load_textdomain()`

Load the plugin translation files.

### `add_client_hints_header()`

Advertise the client hints required by the diagnostics pipeline.

### `get_api()`

Expose the REST API handler for dependent services.

### `__clone()`

Prevents cloning of the singleton instance.
@since 0.1.0
@throws LogicException If someone tries to clone the object.

### `__wakeup()`

Prevents unserializing of the singleton instance.
@since 0.1.0
@throws LogicException If someone tries to unserialize the object.

### `__sleep()`

Prevents serializing of the singleton instance.
@since 0.1.0
@throws LogicException If someone tries to serialize the object.

### `__serialize()`

Prevents serializing of the singleton instance.
@since 0.1.0
@throws LogicException If someone tries to serialize the object.

### `__unserialize(array $data)`

Prevents serializing of the singleton instance.
@since 0.1.0
@throws LogicException If someone tries to serialize the object.


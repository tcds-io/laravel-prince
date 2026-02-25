<?php

declare(strict_types=1);

// Loaded via --prepend before the autoloader runs.
// Suppresses E_DEPRECATED from vendor packages (Mockery 1.7.x on PHP 8.4
// triggers "Implicitly marking parameter as nullable is deprecated").
// Deprecations in our own source are caught by phpstan at max level.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

<?php

use App\Kernel;

// the E2E tests' own database behind their DDEV hostname (`ddev e2e-reset`); set before .env*
// is loaded, which doesn't override it
if ('true' === getenv('IS_DDEV_PROJECT') && str_starts_with($_SERVER['HTTP_HOST'] ?? '', 'e2e.')) {
    $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = 'mysql://db:db@db:3306/e2e?charset=utf8mb4';
}

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};

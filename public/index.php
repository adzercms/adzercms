<?php

use App\Kernel;

require_once dirname(__DIR__).'/config/hold.php';
require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static fn (array $context): Kernel => new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);

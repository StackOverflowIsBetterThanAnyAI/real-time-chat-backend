<?php

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    if (file_exists(dirname(__DIR__).'/.env')) {
        (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
    }

    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
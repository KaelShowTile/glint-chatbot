<?php
session_start();

use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

// Add Routing Middleware
$app->addRoutingMiddleware();

// Add Body Parsing Middleware
$app->addBodyParsingMiddleware();

// Add Error Middleware
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// Add Cron Middleware
$app->add(new \App\CronMiddleware());

// Register routes
$routes = require __DIR__ . '/../src/App/routes.php';
$routes($app);

$app->run();

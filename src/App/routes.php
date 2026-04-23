<?php
use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Database;

return function (App $app) {
    $app->get('/', function (Request $request, Response $response) {
        // Test database connection on load
        try {
            $db = Database::getConnection();
            $response->getBody()->write("Welcome to AI Customer Service Backend. DB Connected.");
        } catch (\Exception $e) {
            $response->getBody()->write("Welcome to AI Customer Service Backend. DB Error: " . $e->getMessage());
        }
        return $response;
    });

    $app->get('/admin/login', \App\Controllers\AdminController::class . ':showLogin');
    $app->post('/admin/login', \App\Controllers\AdminController::class . ':processLogin');
    $app->get('/admin/logout', \App\Controllers\AdminController::class . ':logout');
    $app->get('/admin/init', \App\Controllers\AdminController::class . ':showInit');
    $app->post('/admin/init', \App\Controllers\AdminController::class . ':processInit');

    $app->get('/admin/settings', \App\Controllers\SettingsController::class . ':show');
    $app->post('/admin/settings', \App\Controllers\SettingsController::class . ':update');

    $app->get('/admin/api/models', \App\Controllers\ApiController::class . ':listModels');

    $app->get('/admin/text', \App\Controllers\KnowledgeController::class . ':listText');
    $app->post('/admin/text', \App\Controllers\KnowledgeController::class . ':saveText');
    $app->get('/admin/text/delete/{id}', \App\Controllers\KnowledgeController::class . ':deleteText');

    $app->get('/admin/qa', \App\Controllers\KnowledgeController::class . ':listQa');
    $app->post('/admin/qa', \App\Controllers\KnowledgeController::class . ':saveQa');
    $app->get('/admin/qa/delete/{id}', \App\Controllers\KnowledgeController::class . ':deleteQa');

    $app->get('/admin/products', \App\Controllers\KnowledgeController::class . ':listProducts');
    $app->post('/admin/products/sync', \App\Controllers\KnowledgeController::class . ':triggerSync');

    $app->post('/api/chat', \App\Controllers\ChatController::class . ':handleChat');
};

<?php
// DIC configuration

$container = $app->getContainer();

// view renderer
$container['renderer'] = function (\Psr\Container\ContainerInterface $c) {
    $settings = $c->get('settings')['renderer'];
    return new Slim\Views\PhpRenderer($settings['template_path']);
};

// monolog
$container['logger'] = function (\Psr\Container\ContainerInterface $c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    return $logger;
};

// database
$container['db'] = function (\Psr\Container\ContainerInterface $c) {
    $db = $c['settings']['db'];
    $pdo = new PDO("mysql:host=" . $db['host'] . ";dbname=" . $db['dbname'],
        $db['user'], $db['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
};

// Recipe repository with db class dependency
$container['recipe'] = function (\Psr\Container\ContainerInterface $c) {
    return new \Bbc\Features\Recipe($c->get('db'));
};

// Stars repository with db class dependency
$container['star'] = function (\Psr\Container\ContainerInterface $c) {
    return new \Bbc\Features\Star($c->get('db'));
};

// query params processor to convert them into db query params array
$container['params_processor'] = function (\Psr\Container\ContainerInterface $c) {
    return new \Bbc\Features\SearchParamsProcessor();
};

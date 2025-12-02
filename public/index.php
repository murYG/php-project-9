<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use DI\Container;
use PageAnalyzer\{
    Entity\Url,
    Repository\UrlRepository,
    Repository\UrlCheckRepository,
    Service\UrlValidator,
    Service\UrlCheck
};

session_start();

$container = new Container();

$container->set('twig', function ($container) {
    return function ($request) use ($container) {
        $view = Twig::fromRequest($request);
        $environment = $view->getEnvironment();
        $environment->addGlobal('flash', $container->get('flash'));

        return $view;
    };
});

$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$container->set(\PDO::class, function () {
    $dataBaseUrl = getenv('DATABASE_URL');
    if (empty($dataBaseUrl)) {
        throw new \Exception('Не задана строка подключения');
    }

    $databaseUrl = parse_url((string)$dataBaseUrl);
    $username = $databaseUrl['user'];
    $password = $databaseUrl['pass'];
    $host = $databaseUrl['host'];
    $dbName = ltrim($databaseUrl['path'], '/');

    $conn = new \PDO("pgsql:dbname=$dbName;host=$host", $username, $password);
    $conn->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    return $conn;
});

AppFactory::setContainer($container);
$app = AppFactory::create();

$twig = Twig::create(__DIR__ . '/../templates', ['cache' => false]);
$app->add(TwigMiddleware::create($app, $twig));

$router = $app->getRouteCollector()->getRouteParser();

$customErrorHandler = function ($request, $exception) use ($app, $router) {
    $response = $app->getResponseFactory()->createResponse();

    if ($exception instanceof HttpNotFoundException) {
        return $this->get('twig')($request)->render($response->withStatus(404), '404.twig', []);
    }

    $this->get('flash')->addMessage('errors', $exception->getMessage());
    return $response->withStatus(302)->withHeader('Location', $router->urlFor('main'));
};

$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler($customErrorHandler);

$app->get('/', function ($request, $response) {
    $url = $request->getParsedBodyParam('url', ['name' => '']);
    return $this->get('twig')($request)->render($response, 'index.twig', ['url' => $url]);
})->setName('main'); //главная, форма нового url

$app->get('/urls', function ($request, $response) {
    $urls = $this->get(UrlRepository::class)->getEntities();
    $checks = $this->get(UrlCheckRepository::class)->findLatestChecks();

    return $this->get('twig')($request)->render($response, 'urls/index.twig', ['urls' => $urls, 'checks' => $checks]);
})->setName('urls'); //список urls

$app->get('/urls/{id:[0-9]+}', function ($request, $response, array $args) {
    $url = $this->get(UrlRepository::class)->getById($args['id']);
    if ($url === null) {
        throw new HttpNotFoundException($request);
    }
    $check = $this->get(UrlCheckRepository::class)->getEntities($url->getId());

    $params = ['url' => $url, 'checks' => $check];
    return $this->get('twig')($request)->render($response, 'urls/show.twig', $params);
})->setName('url'); //элемент url

$app->post('/urls', function ($request, $response) use ($router) {
    $arUrl = $request->getParsedBodyParam('url');

    $validator = new UrlValidator();
    if (!$validator->validate($arUrl)) {
        $this->get('flash')->addMessageNow('validation', implode("\n", $validator->errors()));
        return $this->get('twig')($request)->render($response->withStatus(422), "index.twig", ['url' => $arUrl]);
    }

    $parseResult = parse_url($arUrl['name']);
    $name = "{$parseResult['scheme']}://{$parseResult['host']}";

    $urlRepository = $this->get(UrlRepository::class);
    $url = $urlRepository->getByName($name);
    if ($url === null) {
        $url = new Url($name);
        $urlRepository->save($url);
        $this->get('flash')->addMessage('result', 'Страница успешно добавлена');
    } else {
        $this->get('flash')->addMessage('result', 'Страница уже существует');
    }

    return $response->withRedirect($router->urlFor('url', ['id' => $url->getId()]), 302);
})->setName('create_url'); //создание url

$app->post('/urls/{url_id:[0-9]+}/checks', function ($request, $response, array $args) use ($router) {
    $url = $this->get(UrlRepository::class)->getById($args['url_id']);
    $urlCheck = new UrlCheck($url, $this->get(UrlCheckRepository::class));

    try {
        $urlCheck->check();
        $this->get('flash')->addMessage('result', 'Страница успешно проверена');
    } catch (\RuntimeException $e) {
        $this->get('flash')->addMessage('errors', $e->getMessage());
    }

    return $response->withRedirect($router->urlFor('url', ['id' => $url->getId()]), 302);
})->setName('create_check'); //создание проверки url

$app->run();

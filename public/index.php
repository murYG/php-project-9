<?php

$autoloadPath1 = __DIR__ . '/../../../autoload.php';
$autoloadPath2 = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath1)) {
    require_once $autoloadPath1;
} else {
    require_once $autoloadPath2;
}

use Slim\Factory\AppFactory;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use DI\Container;
use Url\{
    Url,
    UrlRepository,
    UrlValidator,
    UrlCheckRepository
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
    $DATABASE_URL = getenv('DATABASE_URL');
    if (empty($DATABASE_URL)) {
        throw new \Exception('Не задана строка подключения');
    }

    $databaseUrl = parse_url((string)$DATABASE_URL);
    $username = $databaseUrl['user'];
    $password = $databaseUrl['pass'];
    $host = $databaseUrl['host'];
    $dbName = ltrim($databaseUrl['path'], '/');

    $conn = new \PDO("pgsql:dbname=$dbName;host=$host", $username, $password);
    $conn->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    return $conn;
});

$initFilePath = implode('/', [dirname(__DIR__), 'database.sql']);
$initSql = file_get_contents($initFilePath);
$container->get(\PDO::class)->exec($initSql);

AppFactory::setContainer($container);
$app = AppFactory::create();

$twig = Twig::create(__DIR__ . '/../templates', ['cache' => false]);
$app->add(TwigMiddleware::create($app, $twig));

$router = $app->getRouteCollector()->getRouteParser();

$customErrorHandler = function ($request, $exception) use ($app, $router) {
    $response = $app->getResponseFactory()->createResponse();

    if ($exception instanceof HttpNotFoundException) {
        return $this->get('twig')($request)->render($response->withStatus(404), '404.phtml', []);
    }

    $this->get('flash')->addMessage('errors', $exception->getMessage());
    return $response->withStatus(302)->withHeader('Location', $router->urlFor('main'));
};

$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler($customErrorHandler);

$app->get('/', function ($request, $response) {
    $url = $request->getParsedBodyParam('url', ['name' => '']);
    return $this->get('twig')($request)->render($response, 'index.phtml', ['url' => $url]);
})->setName('main'); //главная, форма нового url

$app->get('/urls', function ($request, $response) {
    $urls = $this->get(UrlRepository::class)->getEntities();
    return $this->get('twig')($request)->render($response, 'urls/index.phtml', ['urls' => $urls]);
})->setName('urls'); //список urls

$app->get('/urls/{id}', function ($request, $response, array $args) {
    $url = $this->get(UrlRepository::class)->getById($args['id']);
    if ($url === null) {
        throw new HttpNotFoundException($request);
    }

    $params = [
        'url' => $url,
        'checks' => $url->getCheckList($this->get(UrlCheckRepository::class))
    ];
    return $this->get('twig')($request)->render($response, 'urls/url.phtml', $params);
})->setName('url'); //элемент url

$app->post('/urls', function ($request, $response) use ($router) {
    $arUrl = $request->getParsedBodyParam('url');

    $validator = new UrlValidator();
    $errors = $validator->validate($arUrl);
    if (count($errors) > 0) {
        foreach ($errors as $error) {
            $this->get('flash')->addMessageNow('validation', $error);
        }
        return $this->get('twig')($request)->render($response->withStatus(422), "index.phtml", ['url' => $arUrl]);
    }

    $parseResult = parse_url($arUrl['name']);
    $name = "{$parseResult['scheme']}://{$parseResult['host']}";

    $urlRepository = $this->get(UrlRepository::class);
    $url = $urlRepository->getByName($name);
    if ($url === null) {
        $url = new Url($name);
        $urlRepository->save($url);

        $result = 'Страница успешно добавлена';
    } else {
        $result = 'Страница уже существует';
    }

    $this->get('flash')->addMessage('result', $result);
    return $response->withRedirect($router->urlFor('url', ['id' => $url->getId()]), 302);
})->setName('create_url'); //создание url

$app->post('/urls/{url_id}/checks', function ($request, $response, array $args) use ($router) {
    $url = $this->get(UrlRepository::class)->getById($args['url_id']);

    try {
        $url->check($this->get(UrlCheckRepository::class));
        $this->get('flash')->addMessage('result', 'Страница успешно проверена');
    } catch (\RuntimeException $e) {
        $this->get('flash')->addMessage('errors', $e->getMessage());
    }

    return $response->withRedirect($router->urlFor('url', ['id' => $url->getId()]), 302);
})->setName('create_check'); //создание проверки url

$app->run();

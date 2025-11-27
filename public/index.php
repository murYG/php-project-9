<?php

$autoloadPath1 = __DIR__ . '/../../../autoload.php';
$autoloadPath2 = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath1)) {
    require_once $autoloadPath1;
} else {
    require_once $autoloadPath2;
}

use Slim\Factory\AppFactory;
use DI\Container;
use Url\{
    Url,
    UrlRepository,
    UrlValidator
};

session_start();

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$container->set(\PDO::class, function () {
    $databaseUrl = parse_url(getenv('DATABASE_URL'));
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
$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) use ($router) {
    $url = $request->getParsedBodyParam('url', ['name' => '']);

    $params = [
        'url' => $url,
        'router' => $router,
        'flash' => $this->get('flash')->getMessages()
    ];

    return $this->get('renderer')->render($response, 'index.phtml', $params);
})->setName('main'); //главная, форма нового url

$app->get('/urls', function ($request, $response) use ($router) {
    $urlRepository = $this->get(UrlRepository::class);
    $urls = $urlRepository->getEntities();

    $params = [
        'urls' => $urls,
        'router' => $router,
        'flash' => $this->get('flash')->getMessages()
    ];

    return $this->get('renderer')->render($response, 'urls/index.phtml', $params);
})->setName('urls'); //список urls

$app->get('/urls/{id}', function ($request, $response, array $args) use ($router) {
    $urlRepository = $this->get(UrlRepository::class);
    $url = $urlRepository->getById($args['id']);

    if ($url === null) {
        $this->get('flash')->addMessage('result', "Сайт c идентификатором {$args['id']} не найден");
        return $response->withRedirect($router->urlFor('urls'));
    }

    $params = [
        'url' => $url,
        'router' => $router,
        'flash' => $this->get('flash')->getMessages()
    ];

    return $this->get('renderer')->render($response, 'urls/url.phtml', $params);
})->setName('url'); //элемент url

$app->post('/', function ($request, $response) use ($router) {
    $arUrl = $request->getParsedBodyParam('url');

    $validator = new UrlValidator();
    $errors = $validator->validate($arUrl);

    if (count($errors) > 0) {
        $params = [
            'url' => $arUrl,
            'router' => $router,
            'flash' => ['validation' => $errors]
        ];
        return $this->get('renderer')->render($response, "index.phtml", $params);
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
    return $response->withRedirect($router->urlFor('url', ['id' => $url->getId()]));
}); //создание url

$app->run();

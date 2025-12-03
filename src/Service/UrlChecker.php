<?php

namespace PageAnalyzer\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use DiDom\Document;
use PageAnalyzer\Entity\Url;
use PageAnalyzer\Entity\UrlCheck;
use PageAnalyzer\Repository\UrlChecksRepository;

class UrlChecker
{
    private Url $url;
    private UrlChecksRepository $repo;

    public function __construct(Url $url, UrlChecksRepository $repo)
    {
        if (!$url->exists()) {
            throw new \Exception('url не записан');
        }

        $this->url = $url;
        $this->repo = $repo;
    }

    public function check(): void
    {
        $client = new Client(['base_uri' => $this->url->getName()]);

        try {
            $response = $client->request('GET', '');
        } catch (RequestException $e) {
            if (!$e->hasResponse() || $e->getResponse() === null) {
                throw new \RuntimeException("Произошла ошибка при проверке, не удалось подключиться");
            }
            $response = $e->getResponse();
        } catch (ConnectException $e) {
            throw new \RuntimeException("Произошла ошибка при проверке, не удалось подключиться");
        }

        $checkResult = ['status_code' => $response->getStatusCode()];

        $document = new Document($response->getBody()->getContents());

        $h1 = $document->first('h1');
        $checkResult['h1'] = optional($h1)->text();

        $title = $document->first('title');
        $checkResult['title'] = optional($title)->text();

        $description = $document->first('meta[name="description"]');
        if ($description) {
            $checkResult['description'] = $description->getAttribute('content');
        }

        $check = new UrlCheck((int)$this->url->getId(), null, $checkResult);
        $this->repo->save($check);
    }
}

<?php

namespace Url;

class UrlValidator
{
    public function validate(array $url): array
    {
        $errors = [];
        if (empty($url['name'])) {
            $errors[] = "URL не должен быть пустым";
            return $errors;
        }

        $pattern = "/^(https?:\/\/(?:[a-zа-я0-9\-]+\.)+[a-zа-я]{2,})(?:\/.*)?$/i";
        $matches = [];
        if (!preg_match($pattern, $url['name'], $matches) || strlen($matches[1]) > 255) {
            $errors[] = "Некорректный URL";
        }

        return $errors;
    }
}

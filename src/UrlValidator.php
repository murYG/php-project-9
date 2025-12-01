<?php

namespace PageAnalyzer;

use Valitron\Validator;

class UrlValidator
{
    private Validator $validator;

    public function validate(array $url): bool
    {
        $this->validator = new Validator($url);
        $this->validator->stopOnFirstFail(true);
        $this->validator->rule('required', 'name')->message("URL не должен быть пустым");
        $this->validator->rule('lengthMax', 'name', 255)->message("Некорректный URL");
        $this->validator->rule('url', 'name')->message("Некорректный URL");

        return $this->validator->validate();
    }

    public function errors()
    {
        return $this->validator->errors('name');
    }
}

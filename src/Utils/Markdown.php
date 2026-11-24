<?php

namespace App\Utils;

class Markdown
{
    private $parser;
    private $purifier;

    public function __construct()
    {
        $this->parser = new \Parsedown();

        $purifierConfig = \HTMLPurifier_Config::create([
            'Cache.DefinitionImpl' => null,
        ]);
        $this->purifier = new \HTMLPurifier($purifierConfig);
    }

    public function toHtml(string $text): string
    {
        $html = $this->parser->text($text);

        return $this->purifier->purify($html);
    }
}

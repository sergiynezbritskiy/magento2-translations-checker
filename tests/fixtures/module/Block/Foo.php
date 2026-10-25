<?php

namespace SergiyNezbritskiy\Dummy\Block;

use Magento\Framework\Phrase;

class Foo
{
    public function phrases(): array
    {
        return [
            __('Hello %1', 'world'),
            new Phrase('Phrase object'),
            __('It\'s escaped'),
        ];
    }
}

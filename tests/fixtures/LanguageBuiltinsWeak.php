<?php
namespace AML\View\Tests\Language;

function weakBuiltins(): array
{
    return [strlen(123), trim(null), substr(12345, 1.9), round('1.25', '1'), number_format('1234.50', 2), str_replace(1, 2, 111), ctype_digit(49), ctype_digit(true)];
}

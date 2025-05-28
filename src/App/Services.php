<?php

declare(strict_types=1);

if (!function_exists('dd')) {
    function dd(...$vars) {
        foreach ($vars as $var) {
            var_dump($var);
        }
        die(1);
    }
}
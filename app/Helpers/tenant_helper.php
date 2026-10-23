<?php

use Spatie\Multitenancy\Contracts\IsTenant;

if (! function_exists('tenant')) {
    function tenant()
    {
        return app(IsTenant::class)::current();
    }
}

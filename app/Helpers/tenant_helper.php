<?php

if (! function_exists('tenant')) {
    function tenant()
    {
        return app(\Spatie\Multitenancy\Contracts\IsTenant::class)::current();
    }
}

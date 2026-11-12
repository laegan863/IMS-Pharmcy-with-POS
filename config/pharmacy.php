<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Two-factor authentication for owners and pharmacists
    |--------------------------------------------------------------------------
    |
    | Owners and pharmacists must turn on two-factor authentication before
    | they can use the app. Only switch this off on a local development copy.
    |
    */

    'require_two_factor' => (bool) env('REQUIRE_TWO_FACTOR', true),

];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | Argon2id rather than bcrypt (docs/06 and docs/21). It is memory-hard, which
    | is what actually raises the cost of an offline attack against a stolen dump —
    | bcrypt's work factor only buys CPU time, and GPUs have plenty of that.
    |
    */

    'driver' => env('HASH_DRIVER', 'argon2id'),

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => true,
        'limit' => null,
    ],

    /*
    | Tuned for ~250ms on the production droplet's CPU. Raising `memory` is the
    | single most effective knob here; `threads` above the core count buys nothing.
    */
    'argon' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME', 4),
        'verify' => true,
    ],

    'rehash_on_login' => true,

];

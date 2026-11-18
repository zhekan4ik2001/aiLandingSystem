<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CA certificate bundle (Mozilla / cURL)
    |--------------------------------------------------------------------------
    |
    | On Windows/WAMP, curl.cainfo and openssl.cafile are often empty, so HTTPS
    | from PHP fails while curl.exe in the terminal still works. Point this to
    | cacert.pem or set the same path in php.ini (see bootstrap/certs/).
    |
    */

    'ca_bundle' => env('HTTP_CA_BUNDLE', base_path('bootstrap/certs/cacert.pem')),

];

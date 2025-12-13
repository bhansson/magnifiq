<?php

use App\Models\StoreConnection;

test('friendly error returns null when no error', function () {
    $connection = new StoreConnection;
    $connection->last_error = null;

    expect($connection->getFriendlyError())->toBeNull();
});

test('friendly error handles DNS resolution errors', function () {
    $connection = new StoreConnection;
    $connection->last_error = 'cURL error 6: Could not resolve host: example.myshopify.com';

    expect($connection->getFriendlyError())
        ->toBe('Unable to connect to the store. Please check your internet connection and try again.');
});

test('friendly error handles connection timeout', function () {
    $connection = new StoreConnection;
    $connection->last_error = 'cURL error 28: Connection timed out after 30000 milliseconds';

    expect($connection->getFriendlyError())
        ->toBe('Connection timed out. The store may be temporarily unavailable.');
});

test('friendly error handles operation timeout', function () {
    $connection = new StoreConnection;
    $connection->last_error = 'cURL error 28: Operation timed out';

    expect($connection->getFriendlyError())
        ->toBe('Connection timed out. The store may be temporarily unavailable.');
});

test('friendly error handles connection refused', function () {
    $connection = new StoreConnection;
    $connection->last_error = 'cURL error 7: Connection refused';

    expect($connection->getFriendlyError())
        ->toBe('Connection refused. The store may be temporarily unavailable.');
});

test('friendly error handles SSL errors', function () {
    $connection = new StoreConnection;
    $connection->last_error = 'cURL error 60: SSL certificate problem: unable to get local issuer certificate';

    expect($connection->getFriendlyError())
        ->toBe('Secure connection failed. Please try again later.');
});

test('friendly error handles invalid API key', function () {
    $connection = new StoreConnection;
    $connection->last_error = 'Invalid API key or access token';

    expect($connection->getFriendlyError())
        ->toBe('Authentication failed. Please reconnect your store.');
});

test('friendly error handles 401 unauthorized', function () {
    $connection = new StoreConnection;
    $connection->last_error = 'HTTP 401: Unauthorized';

    expect($connection->getFriendlyError())
        ->toBe('Authentication failed. Please reconnect your store.');
});

test('friendly error handles 403 forbidden', function () {
    $connection = new StoreConnection;
    $connection->last_error = 'HTTP 403: Access denied';

    expect($connection->getFriendlyError())
        ->toBe('Access denied. The app may need additional permissions.');
});

test('friendly error handles rate limiting', function () {
    $connection = new StoreConnection;
    $connection->last_error = 'HTTP 429: Too Many Requests - rate limit exceeded';

    expect($connection->getFriendlyError())
        ->toBe('Too many requests. Please wait a moment and try again.');
});

test('friendly error handles throttled errors', function () {
    $connection = new StoreConnection;
    $connection->last_error = 'Request throttled by Shopify';

    expect($connection->getFriendlyError())
        ->toBe('Too many requests. Please wait a moment and try again.');
});

test('friendly error handles 404 not found', function () {
    $connection = new StoreConnection;
    $connection->last_error = 'HTTP 404: Store not found';

    expect($connection->getFriendlyError())
        ->toBe('Store not found. Please verify the store URL.');
});

test('friendly error handles 500 server error', function () {
    $connection = new StoreConnection;
    $connection->last_error = 'HTTP 500: Internal Server Error';

    expect($connection->getFriendlyError())
        ->toBe('The store is temporarily unavailable. Please try again later.');
});

test('friendly error handles 502 bad gateway', function () {
    $connection = new StoreConnection;
    $connection->last_error = 'HTTP 502: Bad Gateway';

    expect($connection->getFriendlyError())
        ->toBe('The store is temporarily unavailable. Please try again later.');
});

test('friendly error handles 503 service unavailable', function () {
    $connection = new StoreConnection;
    $connection->last_error = 'HTTP 503: Service Unavailable';

    expect($connection->getFriendlyError())
        ->toBe('The store is temporarily unavailable. Please try again later.');
});

test('friendly error handles generic cURL errors', function () {
    $connection = new StoreConnection;
    $connection->last_error = 'cURL error 56: Recv failure: Connection reset by peer';

    expect($connection->getFriendlyError())
        ->toBe('Unable to connect to the store. Please try again later.');
});

test('friendly error returns generic message for unknown errors', function () {
    $connection = new StoreConnection;
    $connection->last_error = 'Some completely unknown error that we have not seen before';

    expect($connection->getFriendlyError())
        ->toBe('Sync failed. Please try again or reconnect your store.');
});

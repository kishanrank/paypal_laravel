<?php

use Illuminate\Support\Facades\Route;

Route::get('/', 'PaymentController@index')->name('/');
Route::post('make/payment', 'PaymentController@payWithpaypal')->name('make.payment');
Route::get('/status', 'PaymentController@getPaymentStatus')->name('status');

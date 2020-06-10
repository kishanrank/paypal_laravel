<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Session\Session as SessionSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect as FacadesRedirect;
use Illuminate\Support\Facades\Session as FacadesSession;
use Illuminate\Support\Facades\URL as FacadesURL;
use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\Item;

/** All Paypal Details class **/

use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;
use Redirect;
use Session;
use URL;

class PaymentController extends Controller
{
    private $_api_context;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {

        /** PayPal api context **/
        $paypal_conf = Config::get('paypal');
        $this->_api_context = new ApiContext(new OAuthTokenCredential(
            $paypal_conf['client_id'],
            $paypal_conf['secret']
        ));
        $this->_api_context->setConfig($paypal_conf['settings']);
    }

    public function index()
    {
        return view('initiate');
    }

    public function payWithpaypal(Request $request)
    {
        // // echo "<pre>";
        // $session = new SessionSession();
        // print_r($session);
        // die;
        // print_r(request('amount'));
        // die;
        $payer = new Payer();
        $payer->setPaymentMethod('paypal');

        $item_1 = new Item();

        $item_1->setName('Item 1')
            /** item name **/
            ->setCurrency('INR')
            ->setQuantity(1)
            ->setPrice($request->get('amount'));
        /** unit price **/

        $item_list = new ItemList();

        $item_list->setItems(array($item_1));
        $amount = new Amount();
        $amount->setCurrency('INR')
            ->setTotal($request->get('amount'));

        $transaction = new Transaction();
        $transaction->setAmount($amount)
            ->setItemList($item_list)
            ->setDescription('Your transaction description');



        $redirect_urls = new RedirectUrls();

        $redirect_urls->setReturnUrl(route('status'))
            /** Specify return URL **/
            ->setCancelUrl(route('status'));

        $payment = new Payment();
        $payment->setIntent('Sale')
            ->setPayer($payer)
            ->setRedirectUrls($redirect_urls)
            ->setTransactions(array($transaction));

        /** dd($payment->create($this->_api_context));exit; **/
        try {

            $payment->create($this->_api_context);
        } catch (\PayPal\Exception\PayPalConnectionException $ex) {

            if (Config::get('app.debug')) {
                FacadesSession::put('error', 'Connection timeout');
                return redirect()->route('/');
            } else {
                FacadesSession::put('error', 'Some error occur, sorry for inconvenient');
                return redirect()->route('/');
            }
        }

        foreach ($payment->getLinks() as $link) {
            if ($link->getRel() == 'approval_url') {
                $redirect_url = $link->getHref();
                break;
            }
        }

        /** add payment ID to session **/
        FacadesSession::put('paypal_payment_id', $payment->getId());

        if (isset($redirect_url)) {

            /** redirect to paypal **/
            return FacadesRedirect::away($redirect_url);
        }

        FacadesSession::put('error', 'Unknown error occurred');
        return FacadesRedirect::to('/');
    }

    public function getPaymentStatus()
    {
        /** Get the payment ID before session clear **/
        $payment_id = FacadesSession::get('paypal_payment_id');

        /** clear the session payment ID **/
        FacadesSession::forget('paypal_payment_id');
        if (empty(request('PayerID')) || empty(request('token'))) {

            FacadesSession::put('error', 'Payment failed');
            return redirect()->route('/');
        }

        $payment = Payment::get($payment_id, $this->_api_context);
        $execution = new PaymentExecution();
        $execution->setPayerId(request('PayerID'));

        /**Execute the payment **/
        $result = $payment->execute($execution, $this->_api_context);

        if ($result->getState() == 'approved') {

            FacadesSession::put('success', 'Payment success');
            return redirect()->route('/');
        }

        FacadesSession::put('error', 'Payment failed');
        return FacadesRedirect::to('/');
    }
}

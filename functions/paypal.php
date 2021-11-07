<?php
function GetAccessToken():string
{
  if(
    isset($_SESSION['payPalAccessToken'])&&
    isset($_SESSION['payPalAccessTokenExpires'])&&
    $_SESSION['payPalAccessTokenExpires']>time()
    )
    {
      return $_SESSION['payPalAccessToken'];
    }

  require_once CONFIG_DIR.'/paypal.php';
  $curl=curl_init();
  $options=[
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_URL=>PAYPAL_BASE_URL.'/v1/oauth2/token',
    CURLOPT_HTTPHEADER=>[
      'Accept: application/json',
      'Accept-Language: en_US'
    ],
    CURLOPT_USERPWD=>PAYPAL_CLIENT_ID.':'.PAYPAL_SECRET,
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>'grant_type=client_credentials'
  ];
  curl_setopt_array($curl,$options);
  $result = curl_exec($curl);
  if(curl_errno($curl))
  {
    curl_close($curl);
    echo curl_error($curl);
    return'';
  }
  curl_close($curl);
  $data= json_decode($result,true);
  $accessToken= $data['access_token'];
  $_SESSION['payPalAccessToken']= $accessToken;
  $_SESSION['payPalAccessTokenExpires']= time()+$data['expires_in'];
  return $accessToken;

}
function createOrder(string $accessToken,array $purchaseUnit)
{
  require_once CONFIG_DIR.'/paypal.php';
  $amountObject = new stdClass();
  $amountObject->currency_code="EUR";
  $amountObject->value=100.00;
  $payer= new stdClass();
  $payer->address= new stdClass();
  $payer->address->address_line_1="123 asdasd";
  $payer->address->admin_area_2="asdasdyxcwesdd";
  $payer->address->postal_code="1337";
  $payer->address->admin_area_1="Deutschland";
  $payer->address->country_code="DE";
  $object= new stdClass();
  $object->amount=$amountObject;
  $object->shipping = new stdClass();
  $object->shipping->address = $payer->address;
  $applicationContext = new stdClass();
  $applicationContext->shipping_preference="SET_PROVIDED_ADDRESS";
  $applicationContext->return_url="http://localhost/shop/index.php/paymentComplete";
  $applicationContext->cancel_url="http://localhost/shop/index.php/cart";
  $data=[
    "payer"=>$payer,
    "application_context"=>$applicationContext,
    "intent"=>"CAPTURE",
    "purchase_units"=>[
      $object
    ]
  ];
  $dataString= json_encode($data);
  $curl=curl_init();
  $options=[
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_URL=>PAYPAL_BASE_URL.'/v2/checkout/orders',
    CURLOPT_HTTPHEADER=>[
      'Content-Type: application/json',
      'Authorization: Bearer '.$accessToken
    ],
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>$dataString
  ];
  curl_setopt_array($curl,$options);
  $result = curl_exec($curl);
  if(curl_errno($curl))
  {
    curl_close($curl);
    echo curl_error($curl);
    return'';
  }
  curl_close($curl);
  $data= json_decode($result,true);
  if(!isset($data) && $data['status'] !== "CREATED")
  {
    return '';
  }
  setPayPalOrderId($data['status']);
  $url='';
  foreach($data['links'] as $link)
  {
    if($link['rel'] !== "approve")
    {
      continue;
    }
    $url = $link['href'];
  }
  header("Location: ".$url);
}
function setPayPalOrderId(string $orderId):void
{
  $_SESSION['paypalOrderId'] = $orderId;
}
function getPayPalOrderId():?string
{
 return isset($_SESSION['paypalOrderId'])?$_SESSION['paypalOrderId']:null;
}
function setPayPalRequestId(string $payPalRequestId):void
{
  $_SESSION['paypalRequestId'] = $payPalRequestId;
}
function getPayPalRequestId():?string
{
  return isset($_SESSION['paypalRequestId'])?$_SESSION['paypalRequestId']:null;
}
function capturePayment(string $accessToken,string $orderId, string $token)
{
  require_once CONFIG_DIR.'/paypal.php';
  $data =new stdClass();
  $data->payment_source = new stdClass();
  $data->payment_source->token = new stdClass();
  $data->payment_source->token->id = $token;
  $data->payment_source->token->type = "BILLING_AGREEMENT";
  $dataString= json_encode($data);
  $curl=curl_init();
  $options=[
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_URL=>PAYPAL_BASE_URL.'/v2/checkout/orders/'.$orderId.'/capture',
    CURLOPT_HTTPHEADER=>[
      'Content-Type: application/json',
      'Authorization: Bearer '.$accessToken,
      //'Paypal-Request-Id: '.$payPalRequestId
    ],
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>$dataString
  ];
  curl_setopt_array($curl,$options);
  $result = curl_exec($curl);
  if(curl_errno($curl))
  {
    curl_close($curl);
    echo curl_error($curl);
    return'';
  }
  curl_close($curl);
  $data= json_decode($result,true);
}
function paypalCreateOrder(array $deliveryAddressData, array $cartProducts)
{
  $accessToken = GetAccessToken();
  createOrder($accessToken,[]);
}
function vorkasseCreateOrder(array $deliveryAddressData, array $cartProducts)
{
  header("Location ".BASE_URL." index.php/paymentComplete");
}
function paypalPaymentComplete()
{
  $accessToken=GetAccessToken();
  $orderId = getPayPalOrderId();
  $payPalRequestId = getPayPalRequestId();
  $token=filter_input(INPUT_GET,'token',FILTER_SANITIZE_STRING);
  if($accessToken && $orderId && $token)
  {
      capturePayment($accessToken,$orderId,$token);
  }
}
function vorkassePaymentComplete()
{

}
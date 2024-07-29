<?php
include $_SERVER['DOCUMENT_ROOT'] . '/common/commonPage/header.php';

foreach($_POST as $key=>$post) $_POST[$key] = allTags($post);

$paymentList = $_POST['paymentList'];
$isUseCnt = 0;
foreach ($paymentList as $key => $val) {
    $idx = $val;

    $paymentIsUse = $common['WebReservationManager']->getPaymentTCodeUseCnt($idx);
    if ($paymentIsUse) {
        $isUseCnt++;
    }
}

if ($isUseCnt > 0) {
    echo 'N1';
    exit;
}
$payParam = [
    'clientKey' => $clientKey,
    'apiSecretKey' => $apiSecretKey,
];
$success = $common['PaymentManager']->refundPayment($payParam, $_POST);

if ( $success->isResult() ) {
    echo 'Y';
} else {
    echo 'N2';
}

include $common['wwwPath'] . '/common/commonPage/footer.php';
?>
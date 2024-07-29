<?php
include $_SERVER['DOCUMENT_ROOT'] . '/common/commonPage/header.php';
$rsvnParam = array();
foreach($_POST as $key=>$post) {
    if ($key == 'result') continue;
    $_POST[$key] = allTags($post);
}
$payResult = json_decode($_POST['result'], true);

$payParam = [
    'clientKey' => $clientKey,
    'apiSecretKey' => $apiSecretKey,
];

// 온라인결제 합산
$reservationPrice = $_POST['reservationPrice'] + $_POST['oldReservationOnlinePrice'];
$oldOnlinePrice = $_POST['oldReservationOnlinePrice'];
$oldVisitPrice = $_POST['oldReservationVisitPrice'];
$onlinePrice     = $_POST['onlinePrice'];
$visitPrice     = $_POST['visitPrice'];
$diffVisitPrice = $visitPrice - $oldVisitPrice;
$totalOnlinePrice = $oldOnlinePrice + $onlinePrice;
$totalVisitPrice = $oldVisitPrice + $diffVisitPrice;
$totalResrvationPrice = $totalVisitPrice + $totalOnlinePrice;

$rsvnParam = [
    'idx'                       => $_POST['idx'],
    'reservationDate'           => $_POST['reservationDate'],
    'reservationTime'           => $_POST['reservationTime'],
    'reservationPrice'          => $totalResrvationPrice,
    'reservationVisitPrice'     => $totalVisitPrice,
    'reservationOnlinePrice'    => $totalOnlinePrice,
    'oldReservationDate'		=> $_POST['oldReservationDate'],
    'oldReservationTime'		=> $_POST['oldReservationTime'],
    'oldTreatmentCode'		    => $_POST['oldTreatmentCode'],
    'oldReservationPrice'	    => $_POST['oldReservationPrice'],
    'oldReservationVisitPrice'	=> $_POST['oldReservationVisitPrice'],
    'oldReservationOnlinePrice'	=> $_POST['oldReservationOnlinePrice'],
    'treatmentCode'             => $_POST['treatmentCode'],
    'isPrice'			        => $_POST['isPrice'],
    'state'			            => $_POST['state'],
];

$idx						=	$_POST['idx'];

$setPaymentInfo             =   $common['PaymentManager']->insertPaymentInfo($payParam, $payResult, $_POST, $idx);
if (!$setPaymentInfo->isResult()) {
    $paymentKey = $payResult['paymentKey'];

    $rollbackPayment = $common['PaymentManager']->rollbackPayment($payParam, $payResult, $paymentKey);
    echo 'N1,'.$setPaymentInfo->getMessage();
    exit;
}

$success						=	$common['WebReservationManager']->setWebPaymentReservationChangeProc($rsvnParam);
//print_r($success);
//exit();

if ($success->isResult()) {

    $data						=	$common['WebReservationManager']->getWebReservationInfo($idx);
    $data						=	$data->getData();
    $rs							=	$data[0];

    if ( $rs ) {
        if ( $isUseQrCode == 'Y' && $_POST['state'] != '7') {//	QR코드 접수 사용	2020-09-23

            $btnName			=	'qrButton_' . $idx . '.json';
            // json 파일 만들기
            $arrayData			=	array
            (
                array
                (
                    'name'					=>	'QR코드 접수하기',
                    'type'					=>	'WL',
                    'url_mobile'			=>	'https://qr.toxnfill.com/?BRANDCODE=' . $common['corpCode'] . '&QRCODE=' . $rs['qrCode'] . '&NAME=' . $rs['reservationName'] . '&PHONE=' . str_replace('-' , '', $rs['reservationMobile']) . '&RESVDATE=' . $_POST['reservationDate'] . '&RESVTIME=' . $_POST['reservationTime'],
                    'url_pc'				=>	'https://qr.toxnfill.com/?BRANDCODE=' . $common['corpCode'] . '&QRCODE=' . $rs['qrCode'] . '&NAME=' . $rs['reservationName'] . '&PHONE=' . str_replace('-' , '', $rs['reservationMobile']) . '&RESVDATE=' . $_POST['reservationDate'] . '&RESVTIME=' . $_POST['reservationTime']
                )
            );
            $arrData			=	array();
            $arrData['button']	=	$arrayData;
            $jsonData			=	json_encode($arrData, JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE + JSON_PRETTY_PRINT);
            file_put_contents($common['intraDefaultPath'] . '/smsImg/' . $btnName, iconv('UTF-8','EUC-KR', $jsonData));
            // json 파일 만들기

            if ( $common['corpCode'] == 'T00002') {
                if($rs['isFastTrack'] == 'Y') {
                    $tempIdx		=	'619';
                }else {
                    $tempIdx		=	'618';
                    $btnName		=   '';
                }
            }else if($common['corpCode'] == 'T00001'){
                if($rs['isFastTrack'] == 'Y') {
                    $tempIdx		=	'452';
                }else{
                    $tempIdx		=	'71';
                    $btnName		=  '';
                }
            } else {
                $tempIdx		=	'452';												//	예약변경
            }

            $attchFile			=	$btnName;
        } else {
            if ( $common['corpCode'] == 'T00002' ) {
                $tempIdx		=	'618';												//	예약변경
            } else {
                $tempIdx		=	'71';												//	예약변경
            }
            $attchFile			=	'';
        }

        //	알림톡 발송
        $kaData					=	array();
        $kaData['destName']		=	$rs['reservationName'];
        $kaData['destPhone']	=	$rs['reservationMobile'];
        $kaData['destDate']		=	$_POST['reservationDate'] . ' ' . $_POST['reservationTime'];
        $kaData['tempIdx']		=	$tempIdx;
        $kaData['attchFile']	=	$attchFile;

        $success1				=	$common['SMSManager']->sendKakao($kaData);
        //	알림톡 발송

        //	약도 발송
//        if ( $isSendMap == 'Y' ) {
//            $DATA				=	array();
//            $DATA['smsCate']	=	'87';
//            $DATA['destName']	=	$rs['reservationName'];
//            $DATA['destPhone']	=	$rs['reservationMobile'];
//
//            $success2			=	$common['SMSManager']->mapSendSMS($DATA);
//        }
        //	약도 발송
    }

    if( $_POST['state']	== '7') {
        if ( $isSendMap == 'Y' ) {
            $DATA = [];
            $DATA['corpCode']  = $common['corpCode'];
            $DATA['receiveName'] = $rs['reservationName'];
            $DATA['receivePhone'] = $rs['reservationMobile'];


            $success5 = $common['SMSManager']->mapSendSMS2($DATA);
        }
        if($isUseQrCode) {
            echo 'AFTER_QR, 예약 성공';
        }else {
            echo 'AFTER, 예약 성공';
        }
    }else{
        echo 'Y,예약 성공';
    }
} else {
    $paymentKey = $payResult['paymentKey'];

    $rollbackPayment = $common['PaymentManager']->rollbackPayment($payParam, $payResult, $paymentKey);
    echo 'N2,예약시 에러가 발생하였습니다. 다시 시도해 주세요.';
}

include $common['wwwPath'] . '/common/commonPage/footer.php';
?>
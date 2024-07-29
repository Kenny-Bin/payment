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

//	2019-12-16 - 휴일예약자동설정이 Y이고 토요일 진료마감시간 이후부터 일요일 24시까지의 예약(23시 59분) 인경우만 자동 접수
$isAuto							=	'N';
$thisYoil						=	date('w');
$curTime						=	DATE('H:i');
$_POST['state']					=	'1';

//	진료일 설정 정보
$data						=	$common['ReservationManager']->getModifyReservationTime();
$data						=	$data->getData();
$rs							=	$data[0];

if ( $isAutoReservation == 'Y' && ($thisYoil == '0' || $thisYoil == '6') ) {
    if ( $rs ) {
        if ( $thisYoil == '6' ) {															//	토요일
            $dayEndTime			=	$rs['day6EndTime'];

            if ( $curTime > $dayEndTime ) {
                $isAuto			=	'Y';
                $_POST['state']	=	'6';
            }
        } else if ( $thisYoil == '0' ) {													//	일요일
            $dayEndTime			=	'24:00';

            if ( $curTime < $dayEndTime ) {
                $isAuto			=	'Y';
                $_POST['state']	=	'6';
            }
        }
    }
}
//진료시간 외
$today = date("Y-m-d");
$yoilCul = $thisYoil;
if($yoilCul == 0) $yoilCul = 7;
$startTime = $rs['day'.$yoilCul.'StartTime'];
$endTime = $rs['day'.$yoilCul.'EndTime'];

if($isAfterConfirm == 'Y' && $_POST['state'] != '6') {
    $offDay = $common['ReservationManager']->getOffTreatmentDayCnt($today);
    if($offDay > 0 ){
        $_POST['state']	 =	'7';
    }else {
        if ($curTime < $startTime || $curTime > $endTime) $_POST['state'] =	'7';
    }
}

// 진료시간 내
if($isAllConfirm == 'Y' && $_POST['state'] != '6' && ($curTime >= $startTime && $curTime <= $endTime)) {
    $_POST['state']	 =	'7';
}

$treatmentDetail = $common['TreatmentManager']->getTreatmentPaymentInfo($_POST['treatmentCode']);
$isFastTrack    =   $treatmentDetail['isFastTrack'];    // 예약시술 내 패스트트랙 존재 여부
$isOnlinePay    =   $treatmentDetail['isOnlinePay'];    // 예약시술 내 온라인결제 존재 여부

$rsvnParam = [
    'gubun'             => $_POST['gubun'],
    'reservationDate'   => $_POST['reservationDate'],
    'reservationTime'   => $_POST['reservationTime'],
    'treatmentCode'     => $_POST['treatmentCode'],
    'reservationName'   => $_POST['reservationName'],
    'reservationMobile' => $_POST['reservationMobile'],
    'reservationMemo'   => $_POST['reservationMemo'],
    'reservationPrice'  => $_POST['reservationPrice'],
    'onlinePrice'       => $_POST['onlinePrice'],
    'visitPrice'        => $_POST['visitPrice'],
    'isMarketing'       => $_POST['isMarketing'],
    'isCounsel'         => $_POST['isCounsel'],
    'gender'            => $_POST['gender'],
    'agree2'            => $_POST['agree2'],
    'agree3'            => $_POST['agree3'],
    'agree4'            => $_POST['agree4'],
    'state'             => $_POST['state'],
    'odID'              => $_POST['odID'],
    'isFastTrack'       => $isFastTrack,
    'isOnlinePay'       => $isOnlinePay,
];
//	2019-12-16 - 휴일예약자동설정이 Y이고 토요일 진료마감시간 이후부터 일요일 24시까지의 예약(23시 59분) 인경우만 자동 접수

$success						=	$common['WebReservationManager']->setPaymentWebReservationProc($rsvnParam);
//print_r($success);
//exit();

if ($success->isResult()) {
    $lastIdx = $success->getMessage();

    $setPaymentInfo = $common['PaymentManager']->insertPaymentInfo($payParam, $payResult, $_POST, $lastIdx);

    if (!$setPaymentInfo->isResult()) {
        $paymentKey = $payResult['paymentKey'];

        $rollbackReservation = $common['WebReservationManager']->rollbackWebReservation($lastIdx);
        $rollbackPayment = $common['PaymentManager']->rollbackPayment($payParam, $payResult, $paymentKey);
        echo 'N1,'.$setPaymentInfo->getMessage();
        exit;
    }
    // 결제 정보 저장 ================================================================================================================

    // 결제 정보 저장 ================================================================================================================

    //	가예약	================================================================================================================
    //	알림톡 발송 - 고객
    $kakaoData					=	array();
    $kakaoData['destName']		=	$_POST['reservationName'];
    $kakaoData['destPhone']		=	$_POST['reservationMobile'];
    $kakaoData['destDate']		=	$_POST['reservationDate'] . ' ' . $_POST['reservationTime'];
    $kakaoData['tempIdx']		=	'69';
    $success1					=	$common['SMSManager']->sendKakao($kakaoData);

    //시간 외 알림톡
    if($_POST['state'] == '7') {
        // 예약확정
        if ( $corpCode == 'T00002' ) { // 2022-02-23 강남본원
            $kakaoData['tempIdx']  = '615';
        } else if ( $corpCode == 'T00066' ) {
            $kakaoData['tempIdx']  = '625';
        } else if ( $corpCode == 'T00050' ) { // 2022-08-05 홍대신촌점
            $kakaoData['tempIdx']  = '644';
        } else if ( $corpCode == 'T00022' ) { // 2022-08-05 안산점
            $kakaoData['tempIdx']  = '646';
        } else {
            $kakaoData['tempIdx'] = '70';
        }

        $confirmTime = strtotime('+10 seconds');
        $kakaoData['sendTime']	=	DATE('Y-m-d H:i:s', $confirmTime);
        $success1					=	$common['SMSManager']->sendKakao($kakaoData);
    }

    //	알림톡 발송 - 고객

    //	알림톡 발송 - 해당 지점
    //$kakaoData['tempIdx']		=	'113';													//	홈페이지예약신정알림
    //$success2					=	$common['SMSManager']->sendKakao($kakaoData);
    //	알림톡 발송 - 해당 지점

    //	임시 알림톡 대신 문자 발송
    $success2					=	$common['SMSManager']->reservationSMS($kakaoData);
    //	가예약	================================================================================================================

    if ( $isAuto == 'Y' ) {
        //	예약확정	========================================================================================================
        //	알림톡 발송 - 고객
        $timestamp				=	strtotime('+10 seconds');

        $kakaoData['tempIdx']	=	'70';													//	예약확정
        $kakaoData['sendTime']	=	DATE('Y-m-d H:i:s', $timestamp);

        $success3				=	$common['SMSManager']->sendKakao($kakaoData);
        //	알림톡 발송 - 고객

        //	임시 알림톡 대신 문자 발송
        $success4				=	$common['SMSManager']->reservationSMS($kakaoData);
        //	예약확정	========================================================================================================
    }
    if( $_POST['state']	== '7') {
        if ( $isSendMap == 'Y' ) {
            $DATA = [];
            $DATA['corpCode']  = $common['corpCode'];
            $DATA['receiveName'] = $_POST['reservationName'];
            $DATA['receivePhone'] = $_POST['reservationMobile'];

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
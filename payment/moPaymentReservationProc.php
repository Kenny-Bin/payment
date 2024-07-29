<?php
include $_SERVER['DOCUMENT_ROOT'] . '/common/commonPage/header.php';
$impSuccess = $_GET['imp_success'];
$errorMsg = $_GET['error_msg'];
$impSuccess = true;
if ($impSuccess === true) {
    foreach($_GET as $key=>$get) {
        $_GET[$key] = allTags($get);
    }
    $impParam = [
        'apiKey' => $apiKey,
        'apiSecretKey' => $apiSecretKey,
    ];

    //	2019-12-16 - 휴일예약자동설정이 Y이고 토요일 진료마감시간 이후부터 일요일 24시까지의 예약(23시 59분) 인경우만 자동 접수
    $isAuto							=	'N';
    $thisYoil						=	date('w');
    $curTime						=	DATE('H:i');
    $_GET['state']					=	'1';

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
                    $_GET['state']	=	'6';
                }
            } else if ( $thisYoil == '0' ) {													//	일요일
                $dayEndTime			=	'24:00';

                if ( $curTime < $dayEndTime ) {
                    $isAuto			=	'Y';
                    $_GET['state']	=	'6';
                }
            }
        }
    }

    $today = date("Y-m-d");
    $yoilCul = $thisYoil;
    if($yoilCul == 0) $yoilCul = 7;
    $startTime = $rs['day'.$yoilCul.'StartTime'];
    $endTime = $rs['day'.$yoilCul.'EndTime'];

    if($isAfterConfirm == 'Y' && $_GET['state'] != '6') {
        $offDay = $common['ReservationManager']->getOffTreatmentDayCnt($today);
        if($offDay > 0 ){
            $_GET['state']	 =	'7';
        }else {
            if ($curTime < $startTime || $curTime > $endTime) $_GET['state'] =	'7';
        }
    }

    // 진료시간 내
    if($isAllConfirm == 'Y' && $_GET['state'] != '6' && ($curTime >= $startTime && $curTime <= $endTime)) {
        $_GET['state']	 =	'7';
    }

    $rsvnParam = [
        'gubun'             => $_GET['gubun'],
        'reservationDate'   => $_GET['reservationDate'],
        'reservationTime'   => $_GET['reservationTime'],
        'treatmentCode'     => $_GET['treatmentCode'],
        'reservationName'   => $_GET['reservationName'],
        'reservationMobile' => $_GET['reservationMobile'],
        'reservationMemo'   => $_GET['reservationMemo'],
        'reservationPrice'  => $_GET['reservationPrice'],
        'onlinePrice'       => $_GET['onlinePrice'],
        'visitPrice'        => $_GET['visitPrice'],
        'isMarketing'       => $_GET['isMarketing'],
        'isCounsel'         => $_GET['isCounsel'],
        'gender'            => $_GET['gender'],
        'agree2'            => $_GET['agree2'],
        'agree3'            => $_GET['agree3'],
        'agree4'            => $_GET['agree4'],
        'state'             => $_GET['state'],
        'odID'              => $_GET['odID'],
    ];
    //	2019-12-16 - 휴일예약자동설정이 Y이고 토요일 진료마감시간 이후부터 일요일 24시까지의 예약(23시 59분) 인경우만 자동 접수

    $success						=	$common['WebReservationManager']->setPaymentWebReservationProc($rsvnParam);

    if ($success->isResult()) {
        $lastIdx = $common['WebReservationManager']->getWebReservationCnt();

        $setPaymentInfo = $common['PaymentManager']->moInsertPaymentInfo($impParam, $_GET, $lastIdx);

        if (!$setPaymentInfo->isResult()) {
            //        $rollbackPayment    =   $common['PaymentManager']->rollbackPayment($impParam, $payResult);
            //        echo 'N';
        }
        // 결제 정보 저장 ================================================================================================================

        // 결제 정보 저장 ================================================================================================================

        //	가예약	================================================================================================================
        //	알림톡 발송 - 고객
        $kakaoData					=	array();
        $kakaoData['destName']		=	$_GET['reservationName'];
        $kakaoData['destPhone']		=	$_GET['reservationMobile'];
        $kakaoData['destDate']		=	$_GET['reservationDate'] . ' ' . $_GET['reservationTime'];
        $kakaoData['tempIdx']		=	'69';
        $success1					=	$common['SMSManager']->sendKakao($kakaoData);

        //	가예약
        if($_GET['state'] == '7') {
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

            if ( $isSendMap == 'Y' ) {
                $DATA = [];
                $DATA['corpCode']  = $common['corpCode'];
                $DATA['receiveName'] = $_GET['reservationName'];
                $DATA['receivePhone'] = $_GET['reservationMobile'];

                $success5 = $common['SMSManager']->mapSendSMS2($DATA);
            }
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

        header('Location:'.$common['http'].$common['wwwURL'].'/m/reservationFinish.php?'.http_build_query($rsvnParam));
        echo 'Y';
    } else {
        //    $rollbackPayment    =   $common['PaymentManager']->rollbackPayment($impParam, $payResult);
        echo 'N';
    }
} else {
    header('Location:'.$common['http'].$common['wwwURL'].'/m/reservation.php');
    echo 'N';
}

include $common['wwwPath'] . '/common/commonPage/footer.php';
?>
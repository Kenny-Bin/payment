<?php

class PaymentManager
{
    private $dbm;

    public function __construct()
    {
        $this->dbm				=	new DBManager();
    }

    public function insertPaymentInfo($payParam, $payResult, $rsvnParam, $lastIdx)
    {
        global $common;
        $msg					=	new Message();
        $treatmentArr = explode(',', $rsvnParam['onlineTreatmentCode']);
        $insertStrSQL = array();

        $odID1  =   $payResult['orderId'];
        $odID2  =   $rsvnParam['odID'];

        if ($odID1 != $odID2) {
            $msg->setResult(false);
            $msg->setMessage('주문 번호 오류');
            return $msg;
        }

        $success = $this->paymentConfirm($payParam, $payResult);    //  결제 인증 정보($payResult)로 결제 승인

        if (!$success['result']) {
            $msg->setResult(false);
            $msg->setMessage($success['msg']);
            return $msg;
        }

        $response = $success['data'];

        if ($response->totalAmount == $payResult['amount']) {

            $responseCard = $response->card;
            $responseEasy = $response->easyPay;
            $cardNumber = '';
            $cardQuota = '';
            $cardName = '';
            $methodDetail = '';

            $paymentKey = $response->paymentKey;
            $lastTransactionKey = $response->lastTransactionKey;
            $orderId = $response->orderId;
            $type = $response->type;
            $payMethod = $response->method;
            $currency = $response->currency;
            $totalAmount = $response->totalAmount;
            $receipt = $response->receipt->url;
            $checkout = $response->checkout->url;

            if (is_object($responseCard)) {
                $cardInfo = get_object_vars($responseCard);
                $cardName = $this->getPayMethod($cardInfo['issuerCode']);
                $cardNumber = $cardInfo['number'];
                $cardQuota = $cardInfo['installmentPlanMonths'];
                $methodDetail = $this->getPayMethod($cardInfo['issuerCode']);
            }
            if (is_object($responseEasy)) {
                $easyPayInfo = get_object_vars($responseEasy);
                $methodDetail = $easyPayInfo['provider'];
            }

            foreach ($treatmentArr as $key => $val) {
                $strSQL = "SELECT	brandCode, treatmentCode, treatmentName, treatmentSubName, treatmentGubun, treatmentCate1, treatmentCate2,
                                standardPrice, eventPrice, treatmentMemo, useCorp, isView, regDate, modifyDate, eventStartTime, eventEndTime, timeIdx, useEvent, isOnlinePayment
                            FROM tbl_treatment
                            WHERE treatmentCode = '$val' AND isView = 'Y'";

                $msg = $this->dbm->execute($strSQL, '2');
                $msg = $msg->getData();
                $treatment = $msg[0];

                $treatmentGubun = $treatment['treatmentGubun'];
                $treatmentCode = $treatment['treatmentCode'];
                $treatmentName = $treatment['treatmentName'];
                $treatmentSubName = $treatment['treatmentSubName'];
                $standardPrice = $treatment['standardPrice'];
                $eventPrice = $treatment['eventPrice'];

                if ($treatmentGubun == '1') {
                    $price = $standardPrice;
                } else {
                    $price = $eventPrice;
                }

                $priceVat = round((floatval($price) * 0.1));

                $insertStrSQL[] = "
                                    INSERT INTO tbl_webReservationPayment(twrIdx, paymentKey, lastTransactionKey, orderId, tCode, type, orderName, payMethod, methodDetail, currency, paidAmount, paidVat, suppliedAmount, buyerName, buyerTel, cardNumber, cardQuota, cardName, isSuccess, receipt, checkout)
                                    VALUES (
                                            '$lastIdx', 
                                            '$paymentKey', 
                                            '$lastTransactionKey', 
                                            '$orderId',
                                            '$treatmentCode', 
                                            '$type', 
                                            '$treatmentName', 
                                            '$payMethod', 
                                            '$methodDetail', 
                                            '$currency', 
                                            '$totalAmount',
                                            '$priceVat', 
                                            '$price', 
                                            '$rsvnParam[reservationName]',
                                            '$rsvnParam[reservationMobile]', 
                                            '$cardNumber',
                                            '$cardQuota', 
                                            '$cardName',
                                            '1',
                                            '$receipt',
                                            '$checkout'
                                            )
                                    ";
            }
            $msg = $this->dbm->transaction($insertStrSQL, '1');

            $msg->setResult(true);
            $msg->setMessage('결제 성공');
            return $msg;
        } else {
            $msg->setResult(false);
            $msg->setMessage('위조된 결제 시도');

            return $msg;
        }
    }

    public function moInsertPaymentInfo($impParam, $data, $lastIdx)
    {
        global $common;
        $msg					=	new Message();
        $treatmentArr = explode(',', $data['onlineTreatmentCode']);
        $insertStrSQL = array();

        $accessToken = $this->getAccessToken($impParam['apiKey'], $impParam['apiSecretKey']);

        if ($accessToken === false) {
            $msg->setResult(false);
            $msg->setMessage('엑세스 토큰 오류');
            return $msg;
        }

        $paymentInfo = $this->getPaymentInfo($data['imp_uid'], $accessToken);

        $odID1  =   $data['merchant_uid'];
        $odID2  =   $paymentInfo['merchant_uid'];

        if ($odID1 != $odID2) {
            $msg->setResult(false);
            $msg->setMessage('주문 번호 오류');
            return $msg;
        }

        $payRst = $this->getMoPayResult($paymentInfo);

        if ($payRst['payAmount'] == $paymentInfo['amount']) {

            foreach ($treatmentArr as $key => $val)
            {
                $strSQL = "SELECT	brandCode, treatmentCode, treatmentName, treatmentSubName, treatmentGubun, treatmentCate1, treatmentCate2,
                                standardPrice, eventPrice, treatmentMemo, useCorp, isView, regDate, modifyDate, eventStartTime, eventEndTime, timeIdx, useEvent, isOnlinePayment
                            FROM tbl_treatment
                            WHERE treatmentCode = '$val' AND isView = 'Y'";

                $msg					=	$this->dbm->execute($strSQL, '2');
                $msg                    =   $msg->getData();
                $treatment              =   $msg[0];

                $treatmentGubun					=	$treatment['treatmentGubun'];
                $treatmentCode					=	$treatment['treatmentCode'];
                $treatmentName					=	$treatment['treatmentName'];
                $treatmentSubName				=	$treatment['treatmentSubName'];
                $standardPrice					=	$treatment['standardPrice'];
                $eventPrice						=	$treatment['eventPrice'];

                if ( $treatmentGubun == '1' ) {
                    $price						=	$standardPrice;
                } else {
                    $price						=	$eventPrice;
                }

                $priceVat = round((floatval($price)*0.1));

                $insertStrSQL[] = "
                                INSERT INTO tbl_webReservationPayment (twrIdx, impUid, merchantUid, name, tCode, payMethod, paidAmount, paidVat, paidUnit, paidAt, pgProvider, pgTid, pgType, receiptUrl, buyerName, buyerTel, buyerEmail, buyerAddr, buyerPostCode, currency, cardNumber, cardQuota, cardName, bankName, applyNum, customData, isSuccess)
                                VALUES ('$lastIdx', 
                                        '$payRst[impUid]', 
                                        '$payRst[merchantUid]', 
                                        '$treatmentName', 
                                        '$treatmentCode', 
                                        '$payRst[payMethod]', 
                                        '$payRst[payAmount]', 
                                        '$priceVat', 
                                        '$price', 
                                        '$payRst[paidAt]',
                                        '$payRst[pgProvider]', 
                                        '$payRst[pgTid]', 
                                        '$payRst[pgType]', 
                                        '$payRst[receiptUrl]', 
                                        '$payRst[buyerName]', 
                                        '$payRst[buyerTel]', 
                                        '$payRst[buyerEmail]', 
                                        '$payRst[buyerAddr]', 
                                        '$payRst[buyerPostCode]', 
                                        '$payRst[currency]',
                                        '$payRst[cardNumber]',
                                        '$payRst[cardQuota]',
                                        '$payRst[cardName]',
                                        '$payRst[bankName]',
                                        '$payRst[applyNum]',
                                        '$payRst[customData]',
                                        '$payRst[isSuccess]'
                                        )
                                    ";
            }

            $msg					=	$this->dbm->transaction($insertStrSQL, '1', 'Y');
            return $msg;
        } else {
            $msg->setResult(false);
            $msg->setMessage('위조된 결제 시도');

            return $msg;
        }
    }

    public function getOnlineTreatmentList($idx)
    {
        global $common;
        $msg					=	new Message();

        $strSQL = "
                    SELECT tCode
                    FROM tbl_webReservationPayment
                    WHERE twrIdx= '$idx'
                    ";

        $msg					=	$this->dbm->execute($strSQL, '2');

        return $msg;
    }

    public function getTreatmentPaymentInfo($idx, $treatment)
    {
        global $common;
        $msg					=	new Message();

        $treatmentCodes = implode(',', $treatment);
        $strSQL = "
                    SELECT tm.treatmentGubun, rp.idx, rp.paymentKey, rp.orderId, rp.orderName, rp.paidVat, rp.suppliedAmount, rp.payMethod, rp.methodDetail, rp.isSuccess, rp.isRefund, rp.isUse, rp.regDate, rp.modifyDate, rp.useDate
                    FROM tbl_treatment tm
                    INNER JOIN tbl_webReservationPayment rp
                    ON tm.treatmentCode = rp.tCode
                    WHERE treatmentCode IN ($treatmentCodes)
                    AND rp.twrIdx = '$idx'
                  ";

        $msg					=	$this->dbm->execute($strSQL, '2');

        return $msg;
    }

    public function getTreatmentPaymentDetail($idx)
    {
        global $common;
        $msg					=	new Message();

        $strSQL = "
                    SELECT 
                        idx, twrIdx, paymentKey, tCode, orderId, orderName, payMethod, paidAmount, paidVat, suppliedAmount,
                        receipt, buyerName, buyerTel, currency, cardNumber, cardQuota, cardName,
                        isSuccess, isRefund, isDel, modifyDateIdx, regDate, modifyDate, refundDate
                    FROM tbl_webReservationPayment 
                    WHERE idx = '$idx'
                  ";

        $msg					=	$this->dbm->execute($strSQL, '2');

        return $msg;
    }

    public function getUidPaymentInfo($uid)
    {
        global $common;
        $msg					=	new Message();

        $strSQL = "
                    SELECT *
                    FROM tbl_webReservationPayment
                    WHERE paymentKey = '$uid';
                  ";

        $msg					=	$this->dbm->execute($strSQL, '2');

        return $msg;
    }

    public function refundPayment($payParam, $data)
    {
        global $common;
        $msg					=	new Message();

        $msg->setResult(true);
        $msg->setMessage("취소 성공");

        $strSQL =   array();
        $updateSQL =   array();
        $failName = array();
        $clientKey = $payParam['clientKey'];
        $apiSceretKey = $payParam['apiSecretKey'];
        $paymentList =   $data['paymentList'];
        $twrIdx =   $data['rsvnIdx'];
        $refundDate = date('Y-m-d H:i:s');

        if (!$clientKey || !$apiSceretKey) {
            $msg->setResult(false);
            $msg->setMessage('결제에 필요한 정보가 없습니다.');
            return $msg;
        }

        //결제
        $payList = [];
        foreach ($paymentList as $idx) {
            $info = $this->getTreatmentPaymentDetail($idx);
            $info = $info->getData();
            $info = $info[0];

            if (!isset($payList[$info['orderId']])) {
                $payList[$info['orderId']] = $info;
            } else {
                $payList[$info['orderId']]['idx'] .= ",".$info['idx'];
                $payList[$info['orderId']]['tCode'] .= ",".$info['tCode'];
                $payList[$info['orderId']]['orderName'] .= ",".$info['orderName'];
                $payList[$info['orderId']]['paidVat'] += $info['paidVat'];
                $payList[$info['orderId']]['suppliedAmount'] += $info['suppliedAmount'];
            }
        }
        foreach ($payList as $item) {
            $getPaymentInfo = $this->getPaymentInfo($item['paymentKey'], $payParam);
            $getPaymentInfo = $getPaymentInfo['data'];
            if(!$getPaymentInfo) continue;

            $totalAmount = $getPaymentInfo->balanceAmount;
            $refundAmount = $item['suppliedAmount'] + $item['paidVat'];

            if($totalAmount < $refundAmount || !$getPaymentInfo){
                $msg->setMessage($item['orderName']. "에 대해 취소 실패 하였습니다.");
                $msg->setResult(false);

                return $msg;
            }else{
                //디비 업데이트
                $payData = $this->getBeforePaymentInfo($item['twrIdx']);
                $payData = $payData->getData();
                $paymentInfo = $payData[0];

                $oldTreatmentCode = explode(',', $paymentInfo['treatmentCode']);
                $newTreatmentCode = explode(',', $item['tCode']);
                $onlinePrice = $paymentInfo['onlinePrice'] - $item['suppliedAmount'];
                $reservationPrice = $paymentInfo['reservationPrice'] - $item['suppliedAmount'];

                $diffTreatmentCode = array_diff($oldTreatmentCode, $newTreatmentCode);
                $treatmentCode = implode(',', $diffTreatmentCode);

                $treatmentDetail = $common['TreatmentManager']->getTreatmentPaymentInfo($treatmentCode);
                $isFastTrack    =   $treatmentDetail['isFastTrack'];    // 예약시술 내 패스트트랙 존재 여부
                $isOnlinePay    =   $treatmentDetail['isOnlinePay'];    // 예약시술 내 온라인결제 존재 여부

                $updateSQL[]   =   "
                                    UPDATE tbl_webReservationPayment SET
                                        isRefund = 1,
                                        refundDate = '$refundDate'
                                    WHERE idx IN ($item[idx])
                                    ";

                $updateSQL[]     =   "
                                    UPDATE tbl_webReservation SET
                                        treatmentCode = '$treatmentCode',
                                        onlinePrice = '$onlinePrice',
                                        reservationPrice = '$reservationPrice',
                                        isFastTrack = '$isFastTrack',
                                        isOnlinePay = '$isOnlinePay'
                                    WHERE idx = '$twrIdx'
                                    ";

                $msg					=	$this->dbm->transaction($updateSQL, '1');
                if ($msg->isResult()) {
                    //환불
                    $refundData = [
                        "cancelReason" => $item['orderName']." 취소",
                        "cancelAmount" => (string)$refundAmount,
                    ];
                    $refundData = $this->refund($payParam, $item['paymentKey'], $refundData);

                    if(!$refundData['result'] || !$refundData['data']) {
                        $this->rollbackReservationPayment($item['idx'], $twrIdx, $paymentInfo['onlinePrice'], $paymentInfo['reservationPrice']);

                        $msg->setMessage($item['orderName']. "에 대해 취소 실패 하였습니다.");
                        $msg->setResult(false);

                        return $msg;
                    }
                } else {
                    $this->rollbackReservationPayment($item['idx'], $twrIdx, $paymentInfo['onlinePrice'], $paymentInfo['reservationPrice']);

                    return $msg;
                }
            }
        };

        return $msg;

    }

    public function getWebPaymentIdxs($idx)
    {

        global $common;
        $msg					=	new Message();

        $strSQL = " SELECT 
                        GROUP_CONCAT(idx ORDER BY idx) AS payIdx
                    FROM tbl_webReservationPayment 
                    WHERE 
                        twrIdx = '$idx'
                    AND 
                        isSuccess = 1
                    AND 
                        isRefund = 0
                  ";

        $msg					=	$this->dbm->execute($strSQL, '2');

        return $msg;
    }

    public function rollbackReservationPayment($idx, $twrIdx, $onlinePrice, $reservationPrice)
    {
        global $common;
        $msg					=	new Message();

        $strSQL[]   =   "
                        UPDATE tbl_webReservationPayment SET
                            isRefund = 0
                        WHERE idx IN ('$idx')
                        ";

        $strSQL[]     =   "
                        UPDATE tbl_webReservation SET
                            onlinePrice = '$onlinePrice',
                            reservationPrice = '$reservationPrice'
                        WHERE idx = '$twrIdx'
                        ";

        $msg					=	$this->dbm->transaction($strSQL, '1');
        return $msg;
    }

    public function rollbackPayment($payParam, $payResult, $paymentKey)
    {
        global $common;
        $msg					=	new Message();

        $clientKey = $payParam['clientKey'];
        $apiSceretKey = $payParam['apiSecretKey'];


        if (!$clientKey || !$apiSceretKey) {
            $msg->setResult(false);
            $msg->setMessage('결제에 필요한 정보가 없습니다.');
            return $msg;
        }

        //환불
        $refundData = [
            "cancelReason" => $payResult['orderName']." 취소",
        ];

        $refundData = $this->refund($payParam, $paymentKey, $refundData);

        if (!$refundData['result'] || !$refundData['data']) {
            $msg->setResult(false);
            $msg->setMessage('환불 실패');
            return $msg;
        } else {
            $msg->setResult(true);
            $msg->setMessage('성공');
            return $msg;
        }
    }

    public function getBeforePaymentInfo($twrIdx)
    {
        global $common;
        $msg					=	new Message();

        $strSQL = "
                    SELECT
                        treatmentCode,
                        reservationPrice ,
                        onlinePrice 
                    FROM 
                        tbl_webReservation
                    WHERE idx = '$twrIdx';
                  ";

        $msg					=	$this->dbm->execute($strSQL, '2');

        return $msg;
    }

    public function getPaymentInfo($paymentKey, $payParam)
    {
        $secretKey = $payParam['apiSecretKey'];
        $credential = base64_encode($secretKey . ':');

        $url = 'https://api.tosspayments.com/v1/payments/'.$paymentKey;

        $curlHandle = curl_init($url);
        curl_setopt_array($curlHandle, [
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $credential,
                'Content-Type: application/json'
            ]
        ]);

        $response = curl_exec($curlHandle);
        $httpCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        $isSuccess = $httpCode == 200;
        $responseJson = json_decode($response);

        if ($isSuccess) {
            $result = ['result' => 1, 'msg' => '결제 성공', 'data' => $responseJson];
        } else {
            $result = ['result' => 0, 'msg' => '결제 실패'];
        }

        return $result;
    }

    public function refund($payParam, $paymentKey, $refundData)
    {
        $result = ['result' => 0, 'msg' => '결제 실패'];

        $secretKey = $payParam['apiSecretKey'];
        $bodyData = $refundData;

        $url = 'https://api.tosspayments.com/v1/payments/'.$paymentKey.'/cancel';

        $credential = base64_encode($secretKey . ':');

        $curlHandle = curl_init($url);
        curl_setopt_array($curlHandle, [
            CURLOPT_POST => TRUE,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $credential,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($bodyData)
        ]);

        $response = curl_exec($curlHandle);
        $httpCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        $isSuccess = $httpCode == 200;
        $responseJson = json_decode($response);
        if ($isSuccess) {
            $result = ['result' => 1, 'msg' => '결제 성공', 'data' => $responseJson];
        }

        return $result;
    }

    private function getMoPayResult($data)
    {
        $payResult = [
            'impUid' => $data['imp_uid'],
            'merchantUid' => $data['merchant_uid'],
            'payMethod' => $data['pay_method'],
            'payAmount' => $data['amount'],
            'paidAt' => $data['paid_at'],
            'pgProvider' => $data['pg_provider'],
            'pgTid' => $data['pg_tid'],
            'pgType' => '',
            'receiptUrl' => $data['receipt_url'],
            'buyerName' => $data['buyer_name'],
            'buyerTel' => $data['buyer_tel'],
            'buyerEmail' => $data['buyer_email'],
            'buyerAddr' => $data['buyer_addr'],
            'buyerPostCode' => $data['buyer_postcode'],
            'currency' => $data['currency'],
            'cardNumber' => $data['card_number'],
            'cardQuota' => $data['card_quota'],
            'cardName' => $data['card_name'],
            'bankName' => $data['bank_name'],
            'applyNum' => $data['apply_num'],
            'customData' => $data['custom_data'],
        ];

        return $payResult;
    }

    private function paymentConfirm($payParam, $payResult)
    {
        $result = ['result' => 0, 'msg' => '필수 파라미터 누락'];

        $paymentKey = $payResult['paymentKey'];
        $orderId = $payResult['orderId'];
        $amount = $payResult['amount'];
        $secretKey = $payParam['apiSecretKey'];

        if ($paymentKey && $orderId && $secretKey) {

            $url = 'https://api.tosspayments.com/v1/payments/confirm';
            $data = ['paymentKey' => $paymentKey, 'orderId' => $orderId, 'amount' => $amount];

            $credential = base64_encode($secretKey . ':');

            $curlHandle = curl_init($url);
            curl_setopt_array($curlHandle, [
                CURLOPT_POST => TRUE,
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Basic ' . $credential,
                    'Content-Type: application/json'
                ],
                CURLOPT_POSTFIELDS => json_encode($data)
            ]);

            $response = curl_exec($curlHandle);
            $httpCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
            $isSuccess = $httpCode == 200;
            $responseJson = json_decode($response);

            if ($isSuccess) {
                $result = ['result' => 1, 'msg' => '결제 성공', 'data' => $responseJson];
            } else {
                $result = ['result' => 0, 'msg' => $responseJson->message];
            }
        }

        return $result;
    }

    private function getPayMethod($code)
    {
        $cardName = '';
        switch ($code) {
            case '3K':
                $cardName = '기업비씨';
                break;
            case '46':
                $cardName = '광주';
                break;
            case '71':
                $cardName = '롯데';
                break;
            case '30':
                $cardName = '산업';
                break;
            case '31':
                $cardName = 'BC';
                break;
            case '51':
                $cardName = '삼성';
                break;
            case '38':
                $cardName = '새마을';
                break;
            case '41':
                $cardName = '신한';
                break;
            case '62':
                $cardName = '신협';
                break;
            case '36':
                $cardName = '씨티';
                break;
            case '33':
                $cardName = '우리';
                break;
            case 'W1':
                $cardName = '우리';
                break;
            case '37':
                $cardName = '우체국';
                break;
            case '39':
                $cardName = '저축';
                break;
            case '35':
                $cardName = '전북';
                break;
            case '42':
                $cardName = '제주';
                break;
            case '15':
                $cardName = '카카오뱅크';
                break;
            case '3A':
                $cardName = '케이뱅크';
                break;
            case '24':
                $cardName = '토스뱅크';
                break;
            case '21':
                $cardName = '하나';
                break;
            case '61':
                $cardName = '현대';
                break;
            case '11':
                $cardName = '국민';
                break;
            case '91':
                $cardName = '농협';
                break;
            case '34':
                $cardName = '수협';
                break;
            default:
                $cardName = '미확인';
                break;
        }
        return $cardName;
    }
}
?>
<?php
include $_SERVER['DOCUMENT_ROOT'] . '/common/commonPage/header.php';

$gubun = $_GET['gubun']; if ($gubun == '') $gubun = 'R';
?>

<? if ($gubun == 'M') { ?>
<div class="modal-inner">
<? } ?>
    <div id="payment-method"></div>
    <input type="hidden" id="pageGubun" name="pageGubun" value="<?=$gubun?>">
    <button type="button" class="n-click-evt h5 right pClose-btn" onClick="closeSAPage()">
        <i class="fas fa-times"></i>
    </button>
    <div id="agreement"></div>
    <div style="width:100%;padding:5px;">
        <button style="
                border-radius:2px;
                display: block;
                width: 100%;
                height: 50px;
                box-sizing: border-box;
                font-size: 1.2rem;
                color: #fff;
                background-color: #a73439;
                "
                id="payment-request-button"
                onclick="requestPayment();">결제하기</button>
    </div>
<? if ($gubun == 'M') { ?>
</div>
<? } ?>

<script>
    let gubun = $('#pageGubun').val();
    if (gubun == 'M') {
        var frm =   document.frmMain;
    } else {
        var frm =   document.frmInput;
    }

    let orderPrice = parseInt(Math.round(frm.onlinePrice.value*1.1));

    const paymentWidget = PaymentWidget(
        '<?=$clientKey?>',
        // 비회원 customerKey
        PaymentWidget.ANONYMOUS
    );

    const paymentMethodWidget = paymentWidget.renderPaymentMethods("#payment-method",
        { value: orderPrice,
            currency: 'KRW',
            country: 'KR',
        },
    );

    paymentWidget.renderAgreement("#agreement", {variantKey : "agreement" });

    function getOrderId()
    {
        var today = new Date();
        var year = today.getFullYear();
        var month = String(today.getMonth()+1).padStart(2, 0);
        var day = today.getDate();
        var hours = String(today.getHours()).padStart(2, 0); // 시
        var minutes = String(today.getMinutes()).padStart(2, 0);  // 분
        var seconds = String(today.getSeconds()).padStart(2, 0);  // 초
        var milliseconds = String(today.getMilliseconds()).padEnd(3, 0);
        var makeMerchantUid = `${year}${month}${day}${hours}${minutes}${seconds}${milliseconds}`;

        sessionStorage.setItem('odID', makeMerchantUid);    // 주문번호 세션 저장

        return makeMerchantUid;
    }

    function getPayParam()
    {
        var data        = new Object();

        var orderId = getOrderId();

        var orderPrice = parseInt(Math.round(frm.onlinePrice.value*1.1));
        // var orderVat = Math.round((orderPrice*0.1));
        var orderName = frm.eventOne.value;
        if (frm.eventCnt.value > 1) orderName = frm.eventOne.value + ' 외 ' + frm.eventCnt.value + ' 건';

        data.orderId = orderId;
        data.orderName = orderName;
        // data.successUrl = window.location.origin + "/payment/paymentSuccess.php";
        // data.failUrl = window.location.origin + "/payment/paymentFail.php";
        data.customerEmail = '';
        data.customerName = frm.reservationName.value;

        return data;
    }

    function requestPayment()
    {
        var payParam    =   getPayParam();

        paymentWidget.requestPayment(
            payParam
        )
            .then(function (data) {
                if (data) {
                    if (gubun == 'M') {
                        var form		=	document.querySelector("#frmMain");
                    } else {
                        var form		=	document.querySelector("#frmInput");
                    }
                    var postDate	=	new FormData(form);

                    var odID          = sessionStorage.getItem('odID');
                    postDate.append('odID', odID);
                    postDate.append('result', JSON.stringify(data));

                    $.ajax({
                        url						:	"/payment/paymentReservationProc.php",
                        type					:	"POST",
                        data					:	postDate,
                        dataType				:	"html",
                        //contentType			:	"application/x-www-form-urlencoded; charset=UTF-8",
                        async					:	true,
                        cache					:	false,
                        contentType				:	false,
                        processData				:	false,
                        success					:	function (data)
                        {
                            var rs                      =   data.split(',');
                            var rsResult				=	rs[0].replace( /(\s*)/g, "" );
                            var rsMsg                   =   rs[1];
                            if ( rsResult == "Y" ) {
                                sessionStorage.removeItem('odID');
                                if ( $('#outTime').val() == "N" ) {
                                    alert("예약요청이 완료되었습니다.\n예약이 확정되면 알림톡이 발송됩니다.");
                                } else {
                                    alert("예약요청이 완료되었습니다.\n예약확정은 진료시간 내 순차적으로 일정 확인 후,\n예약 확정 알림톡이 발송되오니,\n이점 양해바랍니다.");
                                }
                                if (gubun == 'M') {
                                    frm.action		=	"/index.php";
                                } else {
                                    frm.action		=	"/reservationFinish.php";
                                }
                                frm.submit();
                            }else if(rsResult == "AFTER_QR"){
                                sessionStorage.removeItem('odID');
                                alert("예약요청이 완료되었습니다.\n알림톡 확인 후 예약일정에 맞춰 내원 부탁드립니다.\n※ QR접수 QR코드는 내원일 전 알림톡으로 발송됩니다.\n※ 병원 사정에 따라 예약이 취소될 수 있사오니 이점 양해 바랍니다.");
                                if (gubun == 'M') {
                                    frm.action		=	"/index.php";
                                } else {
                                    frm.action		=	"/reservationFinish.php";
                                }
                                frm.submit();
                            }else if(rsResult == "AFTER"){
                                sessionStorage.removeItem('odID');
                                alert("예약이 확정되었습니다.\n알림톡 확인 후 예약일정에 맞춰 내원 부탁드립니다.\n※ 병원 사정에 따라 예약이 취소될 수 있사오니 이점 양해 바랍니다.");
                                if (gubun == 'M') {
                                    frm.action		=	"/index.php";
                                } else {
                                    frm.action		=	"/reservationFinish.php";
                                }
                                frm.submit();
                            } else if (rsResult == "N1") {
                                alert('결제승인에 실패하였습니다.\n실패 사유 : ' + rsMsg);
                                // if (gubun == 'M') {
                                //     location.href = "/index.php";
                                // } else {
                                //     location.href = "/reservation.php";
                                // }
                            } else {
                                alert(`${rsMsg}`);
                                if (gubun == 'M') {
                                    location.href = "/index.php";
                                } else {
                                    location.href = "/reservation.php";
                                }
                            }
                        }
                    });
                }
            })
            .catch(function (error) {
                alert(error.message);
            });
    }
</script>
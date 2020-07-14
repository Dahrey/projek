<?php
session_start();
require '../config.php';
require '../lib/session_user.php';

		if (isset($_POST['pesan'])) {
			require '../lib/session_login.php';
			$post_tipe = $conn->real_escape_string(trim(filter($_POST['tipe'])));
			$post_operator = $conn->real_escape_string(trim(filter($_POST['operator'])));
			$post_layanan = $conn->real_escape_string(trim(filter($_POST['layanan'])));
			$post_target = $conn->real_escape_string(trim(filter($_POST['target'])));
			$post_pin = $conn->real_escape_string(trim(filter($_POST['pin'])));

			$cek_layanan = $conn->query("SELECT * FROM layanan_pulsa WHERE service_id = '$post_layanan' AND status = 'Normal'");
			$data_layanan = mysqli_fetch_assoc($cek_layanan);

			$cek_pesanan = $conn->query("SELECT * FROM pembelian_pulsa WHERE target = '$post_target' AND status = 'Pending'");
			$data_pesanan = mysqli_fetch_assoc($cek_pesanan);

			$cek_rate_koin = $conn->query("SELECT * FROM setting_koin_didapat WHERE status = 'Aktif'");
			$data_rate_koin = mysqli_fetch_assoc($cek_rate_koin);

			$order_id = acak_nomor(3).acak_nomor(4);
	        $provider = $data_layanan['provider'];
	        $koin = $data_layanan['harga'] * $data_rate_koin['rate'];

			$cek_provider = $conn->query("SELECT * FROM provider_pulsa WHERE code = '$provider'");
			$data_provider = mysqli_fetch_assoc($cek_provider);

			$cek_rate = $conn->query("SELECT * FROM setting_rate WHERE tipe = 'Top Up'");
			$data_rate = mysqli_fetch_assoc($cek_rate);

	        $error = array();
	        if (empty($post_tipe)) {
			    $error ['tipe'] = '*Wajib Pilih Salah Satu.';
	        }
	        if (empty($post_operator)) {
			    $error ['operator'] = '*Wajib Pilih Salah Satu.';
	        }
	        if (empty($post_layanan)) {
			    $error ['layanan'] = '*Wajib Pilih Salah Satu.';
	        }
	        if (empty($post_target)) {
			    $error ['target'] = '*Tidak Boleh Kosong.';
	        }
	        if (empty($post_pin)) {
			    $error ['pin'] = '*Tidak Boleh Kosong.';
	        } else if ($post_pin <> $data_user['pin']) {
			    $error ['pin'] = '*PIN Yang Kamu Masukkan Salah.';
	        } else {

			if (mysqli_num_rows($cek_layanan) == 0) {
				$_SESSION['hasil'] = array('alert' => 'danger', 'pesan' => 'Ups, Layanan Tidak Tersedia.<script>swal("Ups Gagal!", "Layanan Tidak Tersedia.", "error");</script>');

			} else if (mysqli_num_rows($cek_provider) == 0) {
				$_SESSION['hasil'] = array('alert' => 'danger', 'pesan' => 'Ups, Server Kami Sedang Maintance.<script>swal("Ups Gagal!", "Server Kami Sedang Maintance.", "error");</script>');

			} else if ($data_user['saldo_top_up'] < $data_layanan['harga']) {
				$_SESSION['hasil'] = array('alert' => 'danger', 'pesan' => 'Yahh, Saldo Top Up Kamu Tidak Mencukupi Untuk Melakukan Pemesanan Ini.<script>swal("Yahh Gagal!", "Saldo Top Up Kamu Tidak Mencukupi Untuk Melakukan Pemesanan Ini.", "error");</script>');

			} else if (mysqli_num_rows($cek_pesanan) == 1) {
			    $_SESSION['hasil'] = array('alert' => 'danger', 'pesan' => 'Ups, Masih Terdapat Pesanan Dengan Nomor HP Yang Sama & Berstatus Pending.<script>swal("Ups Gagal!", "Masih Terdapat Pesanan Dengan Nomor HP Yang Sama & Berstatus Pending.", "error");</script>');

			} else {

		    $api_link = $data_provider['link'];
		    $api_key = $data_provider['api_key'];
		    $api_id = $data_provider['api_id'];

		    if ($provider == "MANUAL") {
			    $api_postdata = "";
		    } else if ($provider == "DG-PULSA") {
		    $sign = md5($api_id.$api_key.$order_id);
            $api_postdata = array( 
            	'username' => $api_id,
            	'buyer_sku_code' => $data_layanan['provider_id'],
            	'customer_no' => "$post_target",
            	'ref_id' => $order_id,
            	'sign' => $sign
            );
            $header = array(
                'Content-Type: application/json',
            );
			} else {
				die("System Error!");
			}

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $api_link);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($api_postdata));
                $chresult = curl_exec($ch);
                curl_close($ch);
                $json_result = json_decode($chresult, true);
                $result = json_decode($chresult);
                // print_r($result);

			    if ($provider == "DG-PULSA" && $json_result['data']['status'] == "Gagal") {
		            $_SESSION['hasil'] = array('alert' => 'danger', 'pesan' => 'Ups, '.$json_result['data']['message']);
			    } else {

			        if ($provider == "DG-PULSA") {
		                $provider_oid = $order_id;
			        }

						$check_top = $conn->query("SELECT * FROM top_users WHERE username = '$sess_username'");
						$data_top = mysqli_fetch_assoc($check_top);
						if ($conn->query("INSERT INTO pembelian_pulsa VALUES ('','$order_id', '$provider_oid', '$sess_username', '".$data_layanan['layanan']."', '".$data_layanan['harga']."', '".$data_rate['rate']."', '$koin', '$post_target', '', 'Pending', '$date', '$time', 'Website', '$provider', '0')") == true) {
							$conn->query("UPDATE users SET saldo_top_up = saldo_top_up-".$data_layanan['harga'].", pemakaian_saldo = pemakaian_saldo+".$data_layanan['harga']." WHERE username = '$sess_username'");
							$conn->query("INSERT INTO riwayat_saldo_koin VALUES ('', '$sess_username', 'Saldo', 'Pengurangan Saldo', '".$data_layanan['harga']."', 'Mengurangi Saldo Top Up Melalui Pemesanan Pulsa Reguler Dengan Kode Pesanan : WEB-$order_id', '$date', '$time')");
						    $conn->query("INSERT INTO semua_pembelian VALUES ('','WEB-$order_id','$order_id', '$sess_username', '".$data_layanan['operator']."', '".$data_layanan['layanan']."', '".$data_layanan['harga']."', '$post_target', 'Pending', '$date', '$time', 'WEB', '0')");
						    if (mysqli_num_rows($check_top) == 0) {
    						    $insert_topup = $conn->query("INSERT INTO top_users VALUES ('', 'Order', '$sess_username', '".$data_layanan['harga']."', '1')");
						    } else {
    						    $insert_topup = $conn->query("INSERT top_users SET jumlah = ".$data_top['jumlah']."+".$data_layanan['harga'].", total = ".$data_top['total']."+1 WHERE username = '$sess_username' AND method = 'Order'");
						    }
			    			$_SESSION['hasil'] = array('alert' => 'success', 'pesan' => 'Sip, Pesanan Kamu Telah Kami Terima.');
						} else {
							$_SESSION['hasil'] = array('alert' => 'danger', 'pesan' => 'Ups, Gagal! Sistem Kami Sedang Mengalami Gangguan.<script>swal("Ups Gagal!", "Sistem Kami Sedang Mengalami Gangguan.", "error");</script>');
						}
					}
				}
			}
		}

		require("../lib/header.php");

?>
<style type="text/css">
	.form-group{
		margin-bottom: 0;
	}
	.isi_p{
		padding: 5px;
		-webkit-box-shadow: 0px 0px 15px -5px rgba(0,0,0,0.75);
		-moz-box-shadow: 0px 0px 15px -5px rgba(0,0,0,0.75);
		box-shadow: 0px 0px 15px -5px rgba(0,0,0,0.75);
	}
	.exs{
        float: right;
        color: #ea6666;
        cursor: pointer;
      }
    .exs:hover{
        float: right;
        color: red;
        cursor: pointer;
    }
    .clasesItem:hover,.hovCard:hover{
    	color: #fff;
    	background-color: #039dfc;
    }
</style>

        <!-- Start Sub Header -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
	        <div class="kt-container">
	            <div class="kt-subheader__main">
		            <h3 class="kt-subheader__title">Pemesanan Baru</h3>
	                <div class="kt-subheader__breadcrumbs">
	                    <a href="<?php echo $config['web']['url'] ?>" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
	                	<span class="kt-subheader__breadcrumbs-separator"></span>
	                    <a href="<?php echo $config['web']['url'] ?>" class="kt-subheader__breadcrumbs-link">Halaman Utama</a>
	                	<span class="kt-subheader__breadcrumbs-separator"></span>
	                    <a href="<?php echo $config['web']['url'] ?>" class="kt-subheader__breadcrumbs-link">Pemesanan Baru</a>
	                </div>
	            </div>
	        </div>
        </div>
        <!-- End Sub Header -->

        <!-- Start Content -->
        <div class="kt-container kt-grid__item kt-grid__item--fluid">

        <!-- Start Page New Orders Pulsa Reguler -->
        <div class="row">
	        <div class="col-lg-7">
		        <div class="kt-portlet">
			        <div class="kt-portlet__head">
				        <div class="kt-portlet__head-label">
					        <h3 class="kt-portlet__head-title">
					            <i class="flaticon2-shopping-cart text-primary"></i>
					            Pemesanan Pulsa Reguler
					        </h3>
				        </div>
			        </div>
			        <div class="kt-portlet__body">
                    <?php
                    if (isset($_SESSION['hasil'])) {
                    ?>
                    <div class="alert alert-<?php echo $_SESSION['hasil']['alert'] ?> alert-dismissible" role="alert">
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <?php echo $_SESSION['hasil']['pesan'] ?>
                    </div>
                    <?php
                    unset($_SESSION['hasil']);
                    }
                    ?>
                        <form class="form-horizontal" method="POST">
	                        <input type="hidden" name="csrf_token" value="<?php echo $config['csrf_token'] ?>">
							<!-- <div class="form-group row">
								<label class="col-xl-3 col-lg-3 col-form-label">Tipe</label>
								<div class="col-lg-9 col-xl-6">
									<select class="form-control" name="tipe" id="tipe">
									    <option value="0">Pilih Salah Satu</option>
									    <option value="Pulsa">PULSA REGULER</option>
								    </select>
								    <span class="form-text text-muted type_l"><?php echo ($error['tipe']) ? $error['tipe'] : '';?></span>
								</div>
							</div> -->
							<input type="hidden" name="tipe" id="tipe" value="Pulsa">
							<br>
							<div id="panel_list" class="">
								<div class="form-group row">

									<label class="col-xl-3 col-lg-3 col-form-label">Tujuan </label>
									<div class="col-lg-9 col-xl-6">
									    <input type="text" id="nomor" name="target" class="form-control" placeholder="Nomor HP">
									    <span class="form-text text-muted"><?php echo ($error['target']) ? $error['target'] : '';?></span>
									</div>
								</div>
								<div id="rep" class="row" style="margin-top: 5px;">
								</div>
								<div id="catatan"></div>
								<div id="koin"></div>
								<div id="ajx" class="row" style="margin-top: 5px;">
								</div>
							</div>
							<!-- data hidden -->
							<input type="hidden" name="operator" id="operator">
							<input type="hidden" name="layanan" id="layanan">
							<!--------------------->
                            <div class="form-group row">
                                <label class="col-xl-3 col-lg-3 col-form-label">PIN</label>
                                <div class="col-lg-9 col-xl-6">
                                    <div class="input-group">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="la la-lock text-primary"></i></span></div>
                                        <input type="number" name="pin" class="form-control" placeholder="Masukkan PIN Kamu">
                                    </div>
                                    <span class="form-text text-muted"><?php echo ($error['pin']) ? $error['pin'] : '';?></span>
                                </div>
                            </div>
                            <div class="kt-portlet__foot">
                                <div class="kt-form__actions">
                                    <div class="row">
                                        <div class="col-lg-3 col-xl-3">
                                        </div>
                                        <div class="col-lg-9 col-xl-9">
                                            <button type="submit" name="pesan" class="btn btn-primary btn-elevate btn-pill btn-elevate-air">Submit</button>
                                            <button type="reset" class="btn btn-danger btn-elevate btn-pill btn-elevate-air">Ulangi</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
						</form>
					</div>
				</div>
	        </div>

	        <div class="col-lg-5">
		        <div class="kt-portlet">
			        <div class="kt-portlet__head">
				        <div class="kt-portlet__head-label">
					        <h3 class="kt-portlet__head-title">
					            <i class="flaticon-alert text-primary"></i>
					            Informasi
					        </h3>
				        </div>
			        </div>
			        <div class="kt-portlet__body">
						<ul>
							<li>Pesan Pulsa Reguler Masukkan Nomor HP Dengan Benar, Contoh 085600xxx245.</li>
							<li>Harap Masukan Nomor HP Dengan Benar, Tidak Ada Pengembalian Dana Untuk Kesalahan Pengguna Yang Pesanannya Sudah Terlajur Di Pesan.</li>
							<li>Jika Butuh Bantuan Silahkan Hubungi Kontak Kami.</li>
						</ul>
					</div>
				</div>
			</div>
		</div>
		<!-- End Page New Orders Pulsa Reguler -->

        </div>
        <!-- End Content -->

        <!-- Start Scrolltop -->
		
		<!-- End Scrolltop -->
		
		<script src="https://code.jquery.com/jquery-1.11.0.min.js"></script>
		<script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.2/rollups/aes.js"></script>
		<script type="text/javascript">
		$(document).ready(function() {
			// $('#nomor').attr('disabled','disabled');
			$('.exs').hide();
		    // $("#tipe").change(function() {
		    // 	$('#nomor').val('');
			   //  var tipe = $("#tipe").val();
			   //  if($(this).val() != 0){
			   //  	// $('#nomor').removeAttr('disabled');
			   //  }
		    //     // $.ajax({
			   //     //  url: '<?php echo $config['web']['url']; ?>ajax/type-top-up.php',
			   //     //  data: 'tipe=' + tipe,
			   //     //  type: 'POST',
			   //     //  dataType: 'html',
			   //     //  success: function(msg) {
				  //     //   $("#operator").html(msg);
			   //     //  }
		    //     // });
	     //    });
	        
	        // $("#operator").change(function() {
	        //     var tipe = $("#tipe").val();
		       //  var operator = $("#operator").val();
		       //  $.ajax({
			      //   url: '<?php echo $config['web']['url']; ?>ajax/service-top-up.php',
			      //   data  : 'tipe=' +tipe + '&operator=' + operator,
			      //   type: 'POST',
			      //   dataType: 'html',
			      //   success: function(msg) {
				     //    $("#layanan").html(msg);
			      //   }
		       //  });
	        // });
	        $('.exs').click(function(){
	        	$('.exs').hide();
	        	$('#panel_list').removeClass('isi_p');
	        	$('#ajx').hide();
	        	$('#rep').show();
	        });
	         $('#nomor').on('keyup change input',function(e){
	         	var char = $(this).val().length;
	         	if(char == 0){
	         		normal();
	         	}else{
	         		$('#panel_list').addClass('isi_p');
	        		$('#ajx').show();
	         	}
	         	if($('#tipe').val() != '0'){
	         		$('.type_l').html("");
	         		
		        	var nmr = $(this).val().split("");
		        	if(nmr[0] == '+'){
		        		 nomor($(this).val(),6);
		        	}else if(nmr[0] == '6'){
		        		 nomor($(this).val(),5);
		        	}else if(nmr[0] == '0'){
						 nomor($(this).val(),4);
		        	}else{
		        		
		        	}
	         	}else{
	         		if(empty(char)){
	         			$('.type_l').html("")
	         		}else{
	         			$('.type_l').html("<p style='color:red;'>pilih tipe Layanan</>")
	         		}
	         		
	         	}
	        });

	        $('#nomor').click(function(){
	        	$('.exs').show();
	        	$('#panel_list').addClass('isi_p');
	        	$('#ajx').show();
	        	$('#rep').hide();
	        });
	  		normal();
	  		function normal(){
	  			$('.exs').hide();
	  			$('#panel_list').removeClass('isi_p');
	  			$('#ajx').hide();
	  		}
	        
	        function nomor(nomor,lengths){
	        	var tipe = $('#tipe').val();
	        	var swit = 'reqnmr';
	        		var nomor = nomor;
			        $.ajax({
				        type : "POST",
				        url  : "<?php echo $config['web']['url']; ?>ajax/request-Operator.php",
				        data : {nomor:nomor,tipe:tipe,swit:swit},
				        success: function(response){
							var res = $.parseJSON(response);
							var data = '';
							var no = 1;
							for (var i = 0; i < res.length; i++) {
								// var la = ((res[i]['layanan'].split(" ").length == 2)?res[i]['layanan'].split(" ")[1]:res[i]['layanan']);
								var la = res[i]['layanan'];
							    data += '<div class="col-12 list_pls" style="cursor:pointer"'
							    data += 'data-layanan="'+la+
							    		'"data-harga="'+res[i]['harga']+
							    		'"data-operator="'+res[i]['operator']+
							    		'"data-id="'+res[i]['id']+
							    		'"data-service_id="'+res[i]['service_id']+
							    		'"data-tipe="'+res[i]['tipe']+
										'"data-deskripsi="'+res[i]['deskripsi']+'">'
								data +='<div class="card mb-3 clasesItem" style="border:1px solid #ccc;">'
								data +='<div class="card-body ">'
								data +='<h6 class="card-title m-0">'+la+' <span style="float: right; color: #fb4802;">'+format(res[i]['harga'])+'</span></h6>'
								data +='<p class="card-text">'+res[i]['deskripsi']+'</p>'
								data +=' </div>'
								data +='</div>'
								data +='</div>'            
							}
							$('#ajx').html(data);
							$('.list_pls').click(function(){
								$('#rep').show();
								$('#layanan').val($(this).data('service_id'));
								normal();
								layanan($(this).data('service_id'));
								$('#operator').val($(this).data('operator'));
								//requstOp($(this).data('tipe'),$(this).data('operator'));
								rep = '<div class="col-12 " style="cursor:pointer">'
								rep +='<div class="card mb-3" style="border:1px solid #ccc; color: #fff; background-color: #039dfc;">'
								rep +='<div class="card-body">'
								rep +='<h6 class="card-title m-0">'+$(this).data('layanan')+' <span style="float: right; color: #fb4802;">'+format(parseInt($(this).data('harga')))+'</span></h6>'
								rep +='<p class="card-text">'+$(this).data('deskripsi')+'</p>'
								rep +=' </div>'
								rep +='</div>'
								rep +='</div>'
								$('#rep').html(rep);
							})
				        }
				    });
		        
	        } 

	        function layanan(layanan_){
	        	var tipe = $("#tipe").val();
			    var layanan = layanan_;

			    $.ajax({
			        url: '<?php echo $config['web']['url']; ?>ajax/rate-order-coins-top-up.php',
			        data: 'layanan=' + layanan,
			        type: 'POST',
			        dataType: 'html',
			        success: function(msg) {
				        $("#koin").html(msg);
				        //console.log(msg);
			        }
		        });

		        $.ajax({
			        url: '<?php echo $config['web']['url']; ?>ajax/price-top-up.php',
			        data: 'layanan=' + layanan,
			        type: 'POST',
			        dataType: 'html',
			        success: function(msg) {
				        $("#harga").val(msg);
			        }
		        });
	        }
	        function requstOp(tipe,kode){
	        	 $.ajax({
				    url: '<?php echo $config['web']['url']; ?>ajax/request-Operator.php',
				    data: {tipe:tipe,kode:kode,swit:'reqOpr'},
				    type: 'POST',
				    dataType: 'html',
				    success: function(msg) {
					   $('#operator').val(msg);
				    }
			    });
	        }
	    });
		</script>

<?php
require ("../lib/footer.php");
?>

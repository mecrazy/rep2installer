<?php
//
// rep2 installer by mecrazy ( http://mecrazy.net/ )
// Tested version up from "1.8.103" to "1.8.104"
//

ini_set('error_reporting',E_ALL);

//Options
$options = array(
	'script' => basename(__FILE__), //Current script name
	'mode' => 'static', //mode string to detect response ( 'static' as a default )
	'installed' => false, //installed check result ( will be edited by function 'checkCurrentVersion' )
	'downloaduri' => 'http://akid.s17.xrea.com/cgi/dl/dl.php?dl=p2', //download rep2 from here
	'downloadpath' => 'rep2_latest.zip', //downloaded rep2 zip file name on server
	'versionfile' => './doc/ChangeLog.txt', //version check file on this server
	'versionuri' => 'http://akid.s17.xrea.com/p2/doc/ChangeLog.txt', //version check file on remote server
	'lang' => detectLang() //language mode string ( 'en' or 'ja' )
);

if(isset($_GET['mode'])){ $options['mode'] = $_GET['mode']; }

if($options['mode'] == 'checklatest'){
	ajaxCheckVersion('latest');
}elseif( ($options['mode'] == 'install') || ($options['mode'] == 'update')){
	downloadFile($options['downloaduri'],$options['downloadpath']);
	extractZip($path);
	$obj = array('success' => true);
	$json = json_encode($obj);
	header("Content-Type: application/json; charset=utf-8");
	echo $json;
}elseif($options['mode'] == 'checkcurrent'){
	ajaxCheckVersion('current');
}elseif($options['mode'] == 'disablehostcheck'){
	ajaxDisableHostCheck();
}elseif($options['mode'] == 'resetuser'){
	ajaxResetUser();
}elseif($options['mode'] == 'uninstall'){
	ajaxUninstall();
}elseif($options['mode'] == 'addlink'){
	ajaxAddlink();
}elseif($options['mode'] == 'changeurl'){
	ajaxChangeUrl();
}elseif($options['mode'] == 'static'){
	staticHtml();
}else{
	return404();
}

//Detect browser language
function detectLang(){
	$language = 'en';
	if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])){ $language = $_SERVER['HTTP_ACCEPT_LANGUAGE']; }
	if( ($language != 'en') && ($language != 'ja') ){
		if(strpos($language,'en',0) === 0){
			$language = 'en';
		}elseif(strpos($language,'ja',0) === 0){
			$language = 'ja';
		}else{
			$language = 'en';
		}
	}
	return $language;
}

//Check version offline
function checkCurrentVersion(){
	global $options;
	if($options['lang'] == 'ja'){
		$res = 'rep2はまだインストールされていません。';
	}else{
		$res = 'rep2 has not yet been installed.';
	}
	if(file_exists($options['versionfile'])){
		$txt = file_get_contents($options['versionfile']);
		$res = checkVersion($txt);
		$options['installed'] = true;
	}
	return $res;
}

//Check version online
function checkLatestVersion($uri){
	global $options;
	$res = 'Unknown';
	$txt = file_get_contents($options['versionuri']);
	$res = checkVersion($txt);
	return $res;
}

function checkVersion(){
	//I'm not sure this version check function is smart or not.
	//If you can code a better function, please update this function on your server or github.
	$res = 'Unknown';
	if(func_num_args() >= 1){
		$txt = func_get_arg(0);
		if(preg_match("/rep2 version ([0-9]|\.)+/",$txt,$matchA,PREG_OFFSET_CAPTURE)){
			$temp = substr($matchA[0][0],13);
			if(preg_match("/([0-9]|\.)+/",$temp,$matchB,PREG_OFFSET_CAPTURE)){
				$res = $matchB[0][0];
			}
		}
	}
	return $res;
}

//Downloader
function downloadFile(){
	if(func_num_args() >= 2){
		$uri = func_get_arg(0);
		$path = func_get_arg(1);
		if(file_exists($path)){ unlink($path); }
		$data = file_get_contents($uri);
		file_put_contents($path,$data);
	}
}

//Ajax version check
function ajaxCheckVersion($mode){
	if($mode == 'current'){
		$str = checkCurrentVersion();
	}else if($mode == 'latest'){
		$str = checkLatestVersion();
	}
	$obj = array('version' => $str);
	$json = json_encode($obj);
	header("Content-Type: application/json; charset=utf-8");
	echo $json;
}

//Ajax disable host check
function ajaxDisableHostCheck(){

	$result = false;
	$path = './conf/conf_admin.inc.php';
	$pathBK = './conf/conf_admin.inc_' . date("YmdHis") . '.php';

	if(file_exists($path)){
		if(file_exists($pathBK)){ unlink($pathBK); }
		copy($path,$pathBK);
		$txt = file_get_contents($path);
		$pattern = '/\$_conf\[\'secure\'\]\[\'auth_host\'\] += +1;/s';
		$replacement = '$_conf[\'secure\'][\'auth_host\'] = 0;';
		$txt = preg_replace($pattern,$replacement,$txt);
		unlink($path);
		file_put_contents($path,$txt);
		$result = true;
	}
	$obj = array('result' => $result);
	$json = json_encode($obj);
	header("Content-Type: application/json; charset=utf-8");
	echo $json;
}

//Ajax reset user
function ajaxResetUser(){
	$path = './data/p2_auth_user.php';
	if(file_exists($path)){ unlink($path); }
	$obj = array('result' => true);
	$json = json_encode($obj);
	header("Content-Type: application/json; charset=utf-8");
	echo $json;
}

//Ajax uninstall
function ajaxUninstall(){
	global $options;
	$arr = array();
	if($dir = opendir("./")){
		while(($file = readdir($dir)) !== false){
			if($file != "." && $file != ".." && $file != $options['script'] && $file != "data"){
				array_push($arr,$file);
				if(is_dir($file)){
					unlinkRecursive($file,true);
				}else{
					unlink($file);
				}
			}
		}
		closedir($dir);
	}
	$obj = array('result' => true);
	$json = json_encode($obj);
	header("Content-Type: application/json; charset=utf-8");
	echo $json;
}

//Ajax add link to main menu
function ajaxAddlink(){

	global $options;

	$phpfilename = $options['script'];
	$result = false;

	$newfilename = '';
	if(func_num_args() >= 1){
		$newfilename = func_get_arg(0);
		if($newfilename != ''){ $phpfilename = $newfilename; }
	}

	if($options['lang'] == 'ja'){ $title = 'インストーラ'; }else{ $title = 'Installer'; }

	//For PC
	$path = './lib/menu.inc.php';
	$pathBK = './lib/menu.inc_' . date("YmdHis") . '.php';
	if(file_exists($path)){
		if(file_exists($pathBK)){ unlink($pathBK); }
		copy($path,$pathBK);
		$txt = file_get_contents($path);
		$txt = mb_convert_encoding($txt,"UTF-8","sjis-win");//Convert to UTF-8
		$pattern = '/<a id="rep2installer" .*?<\/a>/';
		if(preg_match($pattern,$txt)){
			$replacement = '<a id="rep2installer" href="./' . $phpfilename . '" target="_blank">' . $title . '</a>';
		}else{
			$replacement = "";
			if(preg_match('/<a href="http:\/\/find\.2ch\.net\/" .*?<\/a>/',$txt,$match,PREG_OFFSET_CAPTURE)){
				$pattern = '/<a href="http:\/\/find\.2ch\.net\/" .*?<\/a>/';
				$replacement = $match[0][0];
			}else if(preg_match('/<a href="<\?php eh\(\$_conf\[\'editpref_php\'\]\) \?>">.*?<\/a>/',$txt,$match,PREG_OFFSET_CAPTURE)){
				$pattern = '/<a href="<\?php eh\(\$_conf\[\'editpref_php\'\]\) \?>">.*?<\/a>/';
				$replacement = $match[0][0];
			}
			if($replacement != ""){
				$replacement = $replacement . '<br>　<a id="rep2installer" href="./' . $phpfilename . '" target="_blank">' . $title . '</a>';
			}
		}
		if($replacement != ""){
			$txt = preg_replace($pattern,$replacement,$txt);
		}
		$txt = mb_convert_encoding($txt,"sjis-win","UTF-8");//Convert to ShiftJIS
		unlink($path);
		file_put_contents($path,$txt);
		$result = true;
	}

	//For smartphone
	$path = './iphone/index_print_k.inc.php';
	$pathBK = './iphone/index_print_k.inc_' . date("YmdHis") . '.php';
	if(file_exists($path)){
		if(file_exists($pathBK)){ unlink($pathBK); }
		copy($path,$pathBK);
		$txt = file_get_contents($path);
		$txt = mb_convert_encoding($txt,"UTF-8","sjis-win");//Convert to UTF-8
		$pattern = '/<a id="rep2installer" .*?<\/a>/';
		if(preg_match($pattern,$txt)){
			$replacement = '<a id="rep2installer" href="./' . $phpfilename . '" target="_blank">' . $title . '</a>';
		}else{
			$replacement = "";
			if(preg_match('/<\?php(.|\r|\n)*?foreach(.|\r|\n)*?\$menuKLinkHtmls(.|\r|\n)*?\?><li>(.|\r|\n)*?<\/li><\?php(.|\r|\n)*?\?>/',$txt,$match,PREG_OFFSET_CAPTURE)){
				$pattern = '/<\?php(.|\r|\n)*?foreach(.|\r|\n)*?\$menuKLinkHtmls(.|\r|\n)*?\?><li>(.|\r|\n)*?<\/li><\?php(.|\r|\n)*?\?>/';
				$replacement = $match[0][0];
			}
			if($replacement != ""){
				$replacement = $replacement . '<li><a id="rep2installer" href="./' . $phpfilename . '" target="_blank">' . $title . '</a></li>';
			}
		}
		if($replacement != ""){
			$txt = preg_replace($pattern,$replacement,$txt);
		}
		$txt = mb_convert_encoding($txt,"sjis-win","UTF-8");//Convert to ShiftJIS
		unlink($path);
		file_put_contents($path,$txt);
		$result = true;
	}

	if($newfilename == ''){
		$obj = array('result' => $result);
		$json = json_encode($obj);
		header("Content-Type: application/json; charset=utf-8");
		echo $json;
	}
}

function ajaxChangeUrl(){

	global $options;

	$newFile = generateRandomStr(40) . '.php';
	while(file_exists($newFile)){
		$newFile = generateRandomStr(40) . '.php';
	}
	copy($options['script'],$newFile);
	ajaxAddlinkToMainMenu($newFile);

	if(file_exists($newFile)){
		unlink($options['script']);
	}

	$result = true;
	$obj = array(
		'result' => $result,
		'redirectto' => $newFile,
	);
	$json = json_encode($obj);
	header("Content-Type: application/json; charset=utf-8");
	echo $json;

}

function generateRandomStr($len){
	$str = array_merge(range('a','z'),range('0','9'),range('0','9'),range('A','Z'),range('0','9'),range('0','9'));
	$res = '';
	for($i=0; $i<$len;$i++){ $res .= $str[rand(0,count($str))]; }
	return $res;
}

function extractZip($zip_path){
	$zip = new ZipArchive();
	$res = $zip->open($zip_path);
	if($res === true){
		if(!file_exists('./temporary')){ mkdir('./temporary',0777); }
		if(!file_exists('./data')){ mkdir('./data',0777); }
		$zip->extractTo('./temporary/');
		$zip->close();
		$zipFrom = './temporary/rep2';
		$zipTo = '../rep2';
		dir_copy($zipFrom, $zipTo);
		unlinkRecursive($zipFrom,true);
	}
}

function dir_copy($dir_name,$new_dir){
	if(!is_dir($new_dir)){ mkdir($new_dir); }
	if(is_dir($dir_name)){
		if($dh = opendir($dir_name)){
			while(($file = readdir($dh)) !== false){
				if($file == "." || $file == ".."){ continue; }
				if(is_dir($dir_name . "/" . $file)){ dir_copy($dir_name . "/" . $file, $new_dir . "/" . $file); }
				else{ copy($dir_name . "/" . $file, $new_dir . "/" . $file); }
			}
			closedir($dh);
		}
	}
	return true;
}

function unlinkRecursive($dir,$deleteRootToo){
	if(!$dh = @opendir($dir)){ return; }
	while(false !== ($obj = readdir($dh))){
		if($obj == '.' || $obj == '..'){ continue; }
		if(!@unlink($dir . '/' . $obj)){ unlinkRecursive($dir.'/'.$obj,true); }
	}
	closedir($dh);
	if($deleteRootToo){ @rmdir($dir); }
	return;
}

function staticHtml(){

	global $options;

	header("Content-type: text/html; charset=UTF-8");

	$vCurrent = checkCurrentVersion();
//	$vLatest = checkLatestVersion();
	$vLatest = '&nbsp';

?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<link href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap.min.css" rel="stylesheet">
<style>
<!--
.navbar .btn{ margin-left:10px; }
#indicator{display:none;position:fixed;top:0px;left:0px;right:0px;bottom:0px;width:100%;height:100%;background-color:#000000;opacity:.8;z-index:1100;}
#indicator_inner{position:relative;top:50%;display:block;margin:0px auto;}
-->
</style>
</head>
<body>

<div class="navbar navbar-default">
	<div class="navbar-header">
		<div class="navbar-brand">rep2 <?php if($options['lang'] == 'ja'){ ?>インストーラ<?php }else{ ?>installer<?php } ?></div>
		<button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
			<span class="icon-bar"></span>
			<span class="icon-bar"></span>
			<span class="icon-bar"></span>
		</button>
	</div>
	<div class="navbar-collapse collapse">
		<ul class="nav navbar-nav">
			<li><p class="navbar-btn"><a class="btn btn-primary" href="./" target="_blank"><?php if($options['lang'] == 'ja'){ ?>rep2を開く<?php }else{ ?>Go to rep2<?php } ?></a></p></li>
		</ul>
	</div>
</div>


<div class="container">

	<div class="row">
		<div class="col-xs-12 col-md-6 col-md-offset-3 col-lg-4 col-lg-offset-4">
			<div class="table-responsive">
				<table class="table table-bordered table-hover">
					<thead>
						<tr class="warning"><th class="text-center"><?php if($options['lang'] == 'ja'){ ?>インストール済みのバージョン<?php }else{ ?>rep2 version on this server<?php } ?></th></tr>
					</thead>
					<tbody>
						<tr><td id="Current_Version" class="text-center"><?php echo $vCurrent; ?></td></tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<div class="row">
		<div class="col-xs-12 col-md-6 col-md-offset-3 col-lg-4 col-lg-offset-4">
			<div class="table-responsive">
				<table class="table table-bordered table-hover">
					<thead>
						<tr class="warning"><th class="text-center"><?php if($options['lang'] == 'ja'){ ?>最新バージョン<?php }else{ ?>Latest version<?php } ?></th></tr>
					</thead>
					<tbody>
						<tr><td id="Latest_Version" class="text-center"><?php echo $vLatest; ?></td></tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<div class="row">
		<div class="col-xs-12 text-center">
			<button id="BTN_Check" class="btn btn-info"><?php if($options['lang'] == 'ja'){ ?>バージョンを再確認する<?php }else{ ?>Re-check each version numbers<?php } ?></button>
		</div>
	</div>

	<div class="row">&nbsp;</div>

	<div class="row">
		<div class="col-xs-12 text-center">
<?php if($options['installed']){ ?>
			<button id="BTN_Update" class="btn btn-warning"><?php if($options['lang'] == 'ja'){ ?>rep2をアップデート<?php }else{ ?>Update rep2<?php } ?></button>
<?php }else{ ?>
			<button id="BTN_Install" class="btn btn-warning"><?php if($options['lang'] == 'ja'){ ?>rep2をインストール<?php }else{ ?>Install rep2<?php } ?></button>
<?php } ?>
		</div>
	</div>

<?php if($options['installed']){ ?>

	<div class="row">&nbsp;</div>

	<div class="row">
		<div class="col-xs-12 text-center">
			<button id="BTN_Addlink" class="btn btn-warning"><?php if($options['lang'] == 'ja'){ ?>rep2のメインメニューにインストーラへのリンクを追加<?php }else{ ?>Add a installer link to the main menu of rep2.<?php } ?></button>
		</div>
	</div>

	<div class="row">&nbsp;</div>

	<div class="row">
		<div class="col-xs-12 text-center">
			<button id="BTN_Disablehostcheck" class="btn btn-warning"><?php if($options['lang'] == 'ja'){ ?>ホストチェック無効化<?php }else{ ?>Disable host check<?php } ?></button>
			<button id="BTN_Changeurl" class="btn btn-warning"><?php if($options['lang'] == 'ja'){ ?>インストーラのURLを複雑にする<?php }else{ ?>Change installer's URL tricky<?php } ?></button>
		</div>
	</div>

	<div class="row">&nbsp;</div>

	<div class="row">
		<div class="col-xs-12 text-center">
			<button id="BTN_Resetuser" class="btn btn-danger"><?php if($options['lang'] == 'ja'){ ?>ユーザ＆パスワードリセット<?php }else{ ?>Reset user name and password<?php } ?></button>
			<button id="BTN_Uninstall" class="btn btn-danger"><?php if($options['lang'] == 'ja'){ ?>rep2をアンインストール<?php }else{ ?>Uninstall rep2<?php } ?></button>
		</div>
	</div>

<?php } ?>

	<div class="row">&nbsp;</div>

</div>

<div id="indicator">
	<div id="indicator_inner">
		<div id="loading_progress" class="container" style="z-index:1100;">
			<div class="row">
				<div class="col-xs-8 col-xs-offset-2">
					<div class="progress">
						<div class="progress-bar progress-bar-info progress-bar-striped active" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width:100%"></div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-xs-12 text-center"><h3 style="color:#fff;" id="indicator_txt">Loading ...</h3></div>
			</div>
		</div>
	</div>
</div>

<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
<script src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.2/js/bootstrap.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/bootbox.js/4.3.0/bootbox.min.js"></script>

<script type="text/javascript">
$(function(){

var lang = '<?php echo $options['lang']; ?>';
var indicator = $('#indicator');
var indicatorTxt = $('#indicator_txt');

onLoad();
function onLoad(){
	if(lang == 'ja'){
		indicatorTxt.text('バージョンチェック中 ...');
	}else{
		indicatorTxt.text('Checking version ...');
	}
	indicator.fadeIn(function(){
		$.getJSON('<?php echo $options['script']; ?>?mode=checklatest').error(function(){
			console.log('error');
		}).success(function(json){
			$('#Latest_Version').html(json.version);
			versionDiff();
		}).complete(function(){
			indicator.fadeOut();
		});
	});
}

$('#BTN_Check').click(function(){
	if(lang == 'ja'){
		indicatorTxt.text('バージョンチェック中 ...');
	}else{
		indicatorTxt.text('Checking version ...');
	}
	var obj = {"current":"","latest":""};
	$('#Current_Version,#Latest_Version').html("&nbsp").eq(0).each(function(){
		indicator.fadeIn(function(){
			$.getJSON('<?php echo $options['script']; ?>?mode=checkcurrent').error(function(){
				console.log('error');
				indicator.fadeOut();
			}).success(function(json){
				obj.current = json.version;
				$('#Current_Version').html(json.version);
				$.getJSON('<?php echo $options['script']; ?>?mode=checklatest').error(function(){
					console.log('error');
				}).success(function(json){
					obj.latest = json.version;
					$('#Latest_Version').html(json.version);
					versionDiff();
				}).complete(function(){
					indicator.fadeOut();
				});
			}).complete(function(){
			});
		});
	});
});

function versionDiff(){
	var latest = $('#Latest_Version');
	if($('#Current_Version').text() == latest.text()){
		latest.css({'font-weight':'normal','color':'#000000'});
	}else{
		latest.css({'font-weight':'bold','color':'#eb6101'});
	}
}

$('#BTN_Install').click(function(){
	if(lang == 'ja'){
		indicatorTxt.text('rep2をインストールしています ...');
	}else{
		indicatorTxt.text('Installing rep2 ...');
	}
	indicator.fadeIn(function(){
		$.getJSON('<?php echo $options['script']; ?>?mode=install').error(function(){
			console.log('error');
		}).success(function(json){
			var msg = 'rep2 is installed. Do you want to disable host check and add installer link to the main menu?';
			if(lang == 'ja'){ msg = 'rep2をインストールしました。続けてホストチェック無効化とインストーラリンクのメインメニューへの追加を実施しますか？'; }
			bootbox.alert(msg,function(agree){
				if(agree){
					installOptions(true,true,true);
				}else{
					location.reload();
				}
			});
		}).complete(function(){
			indicator.fadeOut();
		});
	});
});

$('#BTN_Update').click(function(){
	var msg = 'After updating rep2, configuration files will be overwritten by new version. If you keep old configuration files, please backup them manually before updating.';
	if(lang == 'ja'){ msg = 'rep2をアップデートすると、設定ファイルは新しいバージョンのもので上書きされます。残したい場合は事前に手動でのバックアップが必要です。'; }
	bootbox.confirm(msg,function(agree){
		if(agree){
			indicatorTxt.text('Updating ...');
			indicator.fadeIn(function(){
				$.getJSON('<?php echo $options['script']; ?>?mode=install').error(function(){
					console.log('error');
				}).success(function(json){
					var msg = 'rep2 is updated. Do you want to disable host check and add installer link to the main menu?';
					if(lang == 'ja'){ msg = 'rep2をアップデートしました。続けてホストチェック無効化とインストーラリンクのメインメニューへの追加を実施しますか？'; }
					bootbox.confirm(msg,function(agree){
						if(agree){
							installOptions(true,true,true);
						}else{
							location.reload();
						}
					});
				}).complete(function(){
					indicator.fadeOut();
				});
			});
		}
	});
});

function installOptions(hostcheck,addmenu,reload){
	if(lang == 'ja'){
		indicatorTxt.text('修正中 ...');
	}else{
		indicatorTxt.text('Updating ...');
	}
	if(hostcheck){
		indicator.fadeIn(function(){
			$.getJSON('<?php echo $options['script']; ?>?mode=disablehostcheck').error(function(){
				indicator.fadeOut(function(){
					bootbox.alert('Error');
				});
			}).success(function(json){
				if(addmenu){
					$.getJSON('<?php echo $options['script']; ?>?mode=addlinktomain').error(function(){
						bootbox.alert('Error');
					}).success(function(json){
						if(lang == 'ja'){
							bootbox.alert('ホストチェックの無効化およびrep2メニューへのインストーラ追加が完了しました。',function(){
								if(reload){ location.reload(); }
							});
						}else{
							bootbox.alert('Disabling host check and adding installer link to the menu of rep2 are finished.',function(){
								if(reload){ location.reload(); }
							});
						}
					}).complete(function(){
						indicator.fadeOut();
					});
				}else{
					indicator.fadeOut(function(){
						if(lang == 'ja'){
							bootbox.alert('ホストチェックを無効化しました。',function(){ if(reload){ location.reload(); } });
						}else{
							bootbox.alert('Host check is disabled.',function(){ if(reload){ location.reload(); } });
						}
					});
				}
			}).complete(function(){
			});
		});
	}else if(addmenu){
		indicator.fadeIn(function(){
			$.getJSON('<?php echo $options['script']; ?>?mode=addlinktomain').error(function(){
				bootbox.alert('Error');
			}).success(function(json){
				if(lang == 'ja'){
					bootbox.alert('rep2メニューへのインストーラ追加が完了しました。',function(){ if(reload){ location.reload(); } });
				}else{
					bootbox.alert('Installer link is added to the menu of rep2.',function(){ if(reload){ location.reload(); } });
				}
			}).complete(function(){
				indicator.fadeOut();
			});
		});
	}
}

$('#BTN_Disablehostcheck').click(function(){
	installOptions(true,false,false);
});

$('#BTN_Resetuser').click(function(){
	if(lang == 'ja'){
		indicatorTxt.text('ユーザ＆パスワードをリセット中です。');
	}else{
		indicatorTxt.text('Removing user ...');
	}
	indicator.fadeIn(function(){
		$.getJSON('<?php echo $options['script']; ?>?mode=resetuser').error(function(){
			console.log('error');
		}).success(function(json){
			if(lang == 'ja'){
				bootbox.alert('ユーザ＆パスワードをリセットしました。');
			}else{
				bootbox.alert('User name and password are reset.')
			}
		}).complete(function(){
			indicator.fadeOut();
		});
	});
});

$('#BTN_Uninstall').click(function(){
	var msg = '';
	if(lang == 'ja'){
		msg = '本当にrep2を削除してよろしいですか？<br>( データは削除しません )';
	}else{
		msg = 'Are you sure to uninstall rep2?<br>( It will never remove data folder. )';
	}
	bootbox.confirm(msg,function(agree){
		if(agree){
			if(lang == 'ja'){
				indicatorTxt.text('削除中 ...');
			}else{
				indicatorTxt.text('Uninstalling ...');
			}
			indicator.fadeIn(function(){
				$.getJSON('<?php echo $options['script']; ?>?mode=uninstall').error(function(){
					console.log('error');
				}).success(function(json){
					msg = 'rep2 is uninstalled.';
					if(lang == 'ja'){ msg = 'rep2を削除しました。'; }
					bootbox.alert(msg,function(){ location.reload(); });
				}).complete(function(){
					indicator.fadeOut();
				});
			});
		}
	});
});

$('#BTN_Addlink').click(function(){
	installOptions(false,true,false);
});

$('#BTN_Changeurl').click(function(){
	var msg = 'If you have not added installer link to the main menu of rep2, you will never access installer. Do you agree?';
	if(lang == 'ja'){
		msg = 'インストーラへのリンクをrep2のメニューへ追加していない場合はアクセスできなくなります。続行してよろしいですか？';
	}
	bootbox.confirm(msg,function(agree){
		if(agree){
			if(lang == 'ja'){
				indicatorTxt.text('URLを変更しています ...');
			}else{
				indicatorTxt.text('Changing URL ...');
			}
			indicator.fadeIn(function(){
				$.getJSON('<?php echo $options['script']; ?>?mode=changeurl').error(function(){
					bootbox.alert('error');
				}).success(function(json){
					if(json.redirectto != ''){
						if(lang == 'ja'){
							bootbox.alert('完了しました。',function(){ location.href = json.redirectto; });
						}else{
							bootbox.alert('Complete.',function(){ location.href = json.redirectto; });
						}
					}
				}).complete(function(){
					indicator.fadeOut();
				});
			});
		}
	});
});

});
</script>
<!--<?php echo generateRandomStr(40); ?>-->
<!--<?php echo $options['script']; ?>-->
</body>
</html>
<?php
}

function return404(){
	header("HTTP/1.0 404 Not Found");
	header("Content-type: text/html; charset=UTF-8");
?><!DOCTYPE html><html><body><h1>404</h1><h4>Page Not Found</h4></body></html><?php
}

?>
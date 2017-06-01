<?php
	wp_enqueue_script('dup-handlebars');
	if(empty($_POST))
	{
		//F5 Refresh Check
		$redirect = admin_url('admin.php?page=duplicator&tab=new1');
		echo "<script>window.location.href = '{$redirect}'</script>";
		exit;
	}
	
	global $wp_version;
	$Package = new DUP_Package();
			
	if(isset($_POST['package-hash']))
	{
		// If someone is trying to pass the hasn into us that is illegal so stop it immediately.
		die('Unauthorized');
	}
	
	$Package->saveActive($_POST);
	$Package = DUP_Package::getActive();
	
	$mysqldump_on	 = DUP_Settings::Get('package_mysqldump') && DUP_DB::getMySqlDumpPath();
	$mysqlcompat_on  = isset($Package->Database->Compatible) && strlen($Package->Database->Compatible);
	$mysqlcompat_on  = ($mysqldump_on && $mysqlcompat_on) ? true : false;
	$dbbuild_mode    = ($mysqldump_on) ? 'mysqldump (fast)' : 'PHP (slow)';
    
    $zip_check = DUP_Util::getZipPath();
?>

<style>
	/* ============
	PROGRESS ARES-CHECKS */
	form#form-duplicator {text-align:center; max-width:650px; min-height:200px; margin:0px auto 0px auto; padding:0px;}
	div.dup-progress-title {font-size:22px; padding:5px 0 20px 0; font-weight: bold}
	div#dup-msg-success {padding:0 5px 5px 5px; text-align: left}
	
	div#dup-msg-success-subtitle {color:#999; margin:0; font-size: 11px}
	div#dup-msg-error {color:#A62426; padding:5px; max-width: 790px;}
	div#dup-msg-error-response-text { max-height:500px; overflow-y:scroll; border:1px solid silver; border-radius:3px; padding:10px;background:#fff}
	div.dup-hdr-error-details {text-align: left; margin:20px 0}

	div#dup-msg-success div.details {padding:10px 15px 10px 15px; margin:5px 0 10px 0; background: #fff; border-radius: 5px}
	div#dup-msg-success div.details-title {font-size:20px; border-bottom: 1px solid #dfdfdf; padding:5px; margin:0 0 10px 0; font-weight: bold}
	div.dup-scan-filter-status {display:inline; float: right; font-size:11px; margin-right:10px; color:maroon;}

	div.scan-header { font-size:16px; padding:7px 5px 5px 7px; font-weight: bold; background-color: #E0E0E0; border-bottom: 0px solid #C0C0C0 }
	div.scan-item {border:1px solid #E0E0E0; border-bottom: none;}
	div.scan-item-first { border-top-right-radius: 4px; border-top-left-radius: 4px}
	div.scan-item-last {border-bottom:1px solid #E0E0E0}
	div.scan-item div.title {background-color: #F1F1F1; width:100%; padding:4px 0 4px 0; cursor: pointer; height: 20px;}
	div.scan-item div.title:hover {background-color: #ECECEC;}
	div.scan-item div.text {font-weight: bold; font-size:14px; float:left;  position: relative; left: 10px}
	div.scan-item div.badge-pass {float:right; background:green; border-radius:5px; color:#fff; min-width:40px; text-align:center; position:relative; right:10px; font-size:12px}
	div.scan-item div.badge-warn {float:right; background:maroon; border-radius:5px; color:#fff; min-width:40px; text-align:center; position:relative; right:10px; font-size:12px}
	div.scan-item div.info {display:none; padding:10px; background: #fff}

	div.dup-scan-good {display:inline-block; color:green;font-weight: bold;}
	div.dup-scan-warn {display:inline-block; color:maroon;font-weight: bold;}
	div.dup-more-details {float:right; font-size: 14px}
	div.dup-more-details:hover {color:#777; cursor: pointer}

	
	/*FILES */
	div#data-arc-size1 {display: inline-block; float:right; font-size:11px; margin-right:5px;}
	i.data-size-help { float:right; margin-right:5px; display: block; font-size: 11px}
	div#data-arc-names-data {word-wrap: break-word;font-size:10px; border:1px dashed silver; padding:5px; display: none}

	div.hb-files-style div.container {border:1px solid #E0E0E0; border-radius:4px; margin:5px 0 10px 0}
	div.hb-files-style div.data {padding:8px; line-height: 22px; height:175px; overflow-y:scroll; }
	div.hb-files-style div.hdrs {background: #efefef; padding: 3px}
	div.hb-files-style div.directory i.dup-nav {cursor:pointer}
	div.hb-files-style div.directory i.fa {width:8px}
	div.hb-files-style div.directory label {font-weight: bold; cursor: pointer}
	div.hb-files-style div.files {padding: 2px 0 0 35px; font-size: 11px; display:none; line-height: 16px}

		/*DATABASE*/
	div#data-db-tablelist {max-height: 300px; overflow-y: scroll; border: 1px dashed silver; padding: 5px; margin-top:5px}
	div#data-db-tablelist div{padding:0px 0px 0px 15px;}
	div#data-db-tablelist span{display:inline-block; min-width: 75px}
	div#data-db-size1 {display: inline-block; float:right; font-size:11px; margin-right:5px;}
	
	/*DETAILS WINDOW*/
	div#dup-archive-details-window {font-size: 12px}
	table#dup-scan-db-details {line-height: 14px; margin:0;  width:98%}
	table#dup-scan-db-details td {padding:5px;}
	table#dup-scan-db-details td:first-child {font-weight: bold;  white-space: nowrap; width:120px}
	div#dup-scan-db-info {margin:0px 0px 0px 10px}



	/*WARNING*/
	div#dup-scan-warning-continue {display:none; text-align: center; padding: 0 0 15px 0}
	div#dup-scan-warning-continue div.msg1 label{font-size:16px; color:maroon}
	div#dup-scan-warning-continue div.msg2 {padding:2px; line-height: 13px}
	div#dup-scan-warning-continue div.msg2 label {font-size:11px !important}
	
	/*Footer*/
	div.dup-button-footer {text-align:center; margin:0}
	button.button {font-size:15px !important; height:30px !important; font-weight:bold; padding:3px 5px 5px 5px !important;}
</style>

<!-- =========================================
TOOL BAR: STEPS -->
<table id="dup-toolbar">
	<tr valign="top">
		<td style="white-space: nowrap">
			<div id="dup-wiz">
				<div id="dup-wiz-steps">
					<div class="completed-step"><a>1-<?php _e('Setup', 'duplicator'); ?></a></div>
					<div class="active-step"><a>2-<?php _e('Scan', 'duplicator'); ?> </a></div>
					<div><a>3-<?php _e('Build', 'duplicator'); ?> </a></div>
				</div>
				<div id="dup-wiz-title">
					<?php _e('Step 2: System Scan', 'duplicator'); ?>
				</div> 
			</div>	
		</td>
		<td>
			<a id="dup-pro-create-new"  href="?page=duplicator" class="add-new-h2"><i class="fa fa-archive"></i> <?php _e('Packages', 'duplicator'); ?></a> 
			<span> <?php _e('Create New', 'duplicator'); ?></span>
		</td>
	</tr>
</table>		
<hr class="dup-toolbar-line">


<form id="form-duplicator" method="post" action="?page=duplicator&tab=new3">

	<!--  PROGRESS BAR -->
	<div id="dup-progress-bar-area">
		<div class="dup-progress-title"><i class="fa fa-circle-o-notch fa-spin"></i> <?php _e('Scanning Site', 'duplicator'); ?></div>
		<div id="dup-progress-bar"></div>
		<b><?php _e('Please Wait...', 'duplicator'); ?></b><br/><br/>
		<i><?php _e('Keep this window open during the scan process.', 'duplicator'); ?></i><br/>
		<i><?php _e('This can take several minutes.', 'duplicator'); ?></i><br/>
	</div>

	<!--  ERROR MESSAGE -->
	<div id="dup-msg-error" style="display:none">
		<div class="dup-hdr-error"><i class="fa fa-exclamation-circle"></i> <?php _e('Scan Error', 'duplicator'); ?></div>
		<i><?php _e('Please try again!', 'duplicator'); ?></i><br/>
		<div class="dup-hdr-error-details">
			<b><?php _e("Server Status:", 'duplicator'); ?></b> &nbsp;
			<div id="dup-msg-error-response-status" style="display:inline-block"></div><br/>

			<b><?php _e("Error Message:", 'duplicator'); ?></b>
			<div id="dup-msg-error-response-text"></div>
		</div>
	</div>

	<!--  SUCCESS MESSAGE -->
	<div id="dup-msg-success" style="display:none">

		<div style="text-align:center">
			<div class="dup-hdr-success"><i class="fa fa-check-square-o fa-lg"></i> <?php _e('Scan Complete', 'duplicator'); ?></div>
			<div id="dup-msg-success-subtitle">
				<?php _e('Process Time:', 'duplicator'); ?> <span id="data-rpt-scantime"></span>
			</div>
		</div>

		<div class="details">
			<?php include ('s2.scan2.php') ?>
			<br/><br/>
			<?php include ('s2.scan3.php') ?>
		</div>

		<!-- WARNING CONTINUE -->
		<div id="dup-scan-warning-continue">
			<div class="msg1">
				<label for="dup-scan-warning-continue-checkbox">
					<?php _e('A warning status was detected, are you sure you want to continue?', 'duplicator');?>
				</label>
				<div style="padding:8px 0">
					<input type="checkbox" id="dup-scan-warning-continue-checkbox" onclick="Duplicator.Pack.warningContinue(this)"/>
					<label for="dup-scan-warning-continue-checkbox"><?php _e('Yes.  Continue with the build process!', 'duplicator');?></label>
				</div>
			</div>
			<div class="msg2">
				<label for="dup-scan-warning-continue-checkbox">
					<?php
						_e("Scan checks are not required to pass, however they could cause issues on some systems.", 'duplicator');
						echo '<br/>';
						_e("Please review the details for each warning by clicking on the detail link.", 'duplicator');
					?>
				</label>
			</div>
		</div>

		<div class="dup-button-footer" style="display:none">
			<input type="button" value="&#9664; <?php _e("Back", 'duplicator') ?>" onclick="window.location.assign('?page=duplicator&tab=new1')" class="button button-large" />
			<input type="button" value="<?php _e("Rescan", 'duplicator') ?>" onclick="Duplicator.Pack.rescan()" class="button button-large" />
			<input type="submit" value="<?php _e("Build", 'duplicator') ?> &#9654" class="button button-primary button-large" id="dup-build-button" />
		</div>
	</div>

</form>

<script>
jQuery(document).ready(function($)
{
	/*	Performs Ajax post to create check system  */
	Duplicator.Pack.runScanner = function()
	{
		var data = {action : 'duplicator_package_scan'}
		$.ajax({
			type: "POST",
			url: ajaxurl,
			dataType: "json",
			timeout: 10000000,
			data: data,
			complete: function() {$('.dup-button-footer').show()},
			success:  function(data) {
				
				Duplicator.Pack.loadScanData(data);
			},
			error: function(data) {
				$('#dup-progress-bar-area').hide();
				var status = data.status + ' -' + data.statusText;
				$('#dup-msg-error-response-status').html(status)
				$('#dup-msg-error-response-text').html(data.responseText);
				$('#dup-msg-error').show(200);
				console.log(data);
			}
		});
	}

	Duplicator.Pack.rescan = function()
	{
		$('#dup-msg-success,#dup-msg-error,.dup-button-footer').hide();
		$('#dup-progress-bar-area').show();
		Duplicator.Pack.runScanner();
	}

	Duplicator.Pack.warningContinue = function(checkbox)
	{
		($(checkbox).is(':checked'))
			?	$('#dup-build-button').prop('disabled',false).addClass('button-primary')
			:	$('#dup-build-button').prop('disabled',true).removeClass('button-primary');
	}

	Duplicator.Pack.intErrorView = function()
	{
		var html_msg;
		html_msg  = '<?php _e("Unable to perform a full scan, please try the following actions:", 'duplicator') ?><br/><br/>';
		html_msg += '<?php _e("1. Go back and create a root path directory filter to validate the site is scan-able.", 'duplicator') ?><br/>';
		html_msg += '<?php _e("2. Continue to add/remove filters to isolate which path is causing issues.", 'duplicator') ?><br/>';
		html_msg += '<?php _e("3. This message will go away once the correct filters are applied.", 'duplicator') ?><br/><br/>';

		html_msg += '<?php _e("Common Issues:", 'duplicator') ?><ul>';
		html_msg += '<li><?php _e("- On some budget hosts scanning over 30k files can lead to timeout/gateway issues. Consider scanning only your main WordPress site and avoid trying to backup other external directories.", 'duplicator') ?></li>';
		html_msg += '<li><?php _e("- Symbolic link recursion can cause timeouts.  Ask your server admin if any are present in the scan path.  If they are add the full path as a filter and try running the scan again.", 'duplicator') ?></li>';
		html_msg += '</ul>';
		$('#dup-msg-error-response-status').html('Scan Path Error [<?php echo rtrim(DUPLICATOR_WPROOTPATH, '/'); ?>]');
		$('#dup-msg-error-response-text').html(html_msg);
		$('#dup-msg-error').show(200);
	}

	//PAGE INIT:
	Duplicator.UI.AnimateProgressBar('dup-progress-bar');
	Duplicator.Pack.runScanner();

});
</script>
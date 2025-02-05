<?php
style('ndcregistration', 'admin');
script('ndcregistration', 'admin');
script('ndcregistration', 'settings');
?>
<form id="registration_settings_form" class="section">
	<h2><?php p($l->t('Registration')); ?></h2><span id="registration_settings_msg" class="msg"></span>
	<p style="margin-bottom:12px">
	<label for="registered_user_group"><?php p($l->t('Default group that all registered users belong')); ?></label>
	<select id="registered_user_group" name="registered_user_group">
		<option value="none" <?php echo $_['current'] === 'none' ? 'selected="selected"' : ''; ?>><?php p($l->t('None')); ?></option>
<?php
foreach ($_['groups'] as $group) {
	$selected = $_['current'] === $group ? 'selected="selected"' : '';
	echo '<option value="'.$group.'" '.$selected.'>'.$group.'</option>';
}
?>
	</select>
	</p>
	<p style="margin-bottom:12px">
	<label for="user_storage_capacity"><?php p($l->t('User preset storage capacity')); ?></label>
	<input type="text" style="text-align:right;" id="user_storage_capacity" name="user_storage_capacity" value=<?php p($_['user_storage_capacity']);?>> G
	</p>
	<p>
	<label for="allowed_domains"><?php p($l->t('Allowed mail address domains for registration')); ?></label>
	<input type="text" id="allowed_domains" name="allowed_domains" value=<?php p($_['allowed']);?>>
	</p>
	<p style="margin-bottom:12px">
	<em><?php p($l->t('Enter a semicolon-separated list of allowed domains. Example: owncloud.com;github.com'));?></em>
	</p>

	<p style="margin-bottom:12px">
	<input type="checkbox" class="checkbox" id="auto_account_active" name="auto_account_active" <?php if ($_['auto_account_active'] === "yes") {
	echo " checked";
} ?>>
	<label for="auto_account_active">&nbsp;<?php p($l->t('Automatic account activation')); ?>
	</label>
	</p>
</form>
<div class="section">
	<h2>帳號批次匯入</h2>

	<p class="setting-hint">
		上傳的檔案是 csv 格式，utf-8 編碼，共有三個欄位，欄位名稱放在第一列，分別是: ID, email, group<br>
		欄位需以雙引號括起來，各欄位間以逗號(,)分隔開，欄位內容不可空白。<br>
		<b>請注意！帳號匯入時，會寄發驗證郵件給使用者，請確認郵件伺服器設定正確</b><br><br>
	</p>

	<form class="uploadForm" method="post" action="<?php print_unescaped(\OC::$server->getURLGenerator()->linkToRoute('ndcregistration.csv.uploadFile')) ?>">
		<input id="uploadCsv" class="fileupload" name="csvFile" type="file" style="display: none;">
		<label for="uploadCsv" class="button icon-upload svg" title="Upload CSV"></label>
	</form>

	<div><span id="uploadMessage" class="msg"></span></div>
	<div id="errorMessages" style="display: none;"></div>

	<div class="logData" style="display: none;">
		<div>完成進度：<span id="fraction"></span></div>
		<div>最新進度：<span id="lastrow"></span></div>
	</div>

	<div class="registerResult">
		<div class="title">註冊失敗 (<span class="sumUncreated"></span>) </div>
		<ul class="uncreated"></ul>
		<div class="title">註冊成功 (<span class="sumCreated"></span>) </div>
		<ul class="created"></ul>
	</div>
</div>

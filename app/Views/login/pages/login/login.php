    <div class="main">
		<div class="main-w3lsrow">
			<div id="simple-wrap">
				<img alt="" id="simple-logo" src="<?= base_url('assets/login/images/couch_light.png'); ?>"/>
				<div class="simple-box">
					<div class="panel-heading simple-heading">
						<span class="login-heading" style="vertical-align: inherit;"><?= esc(lang('Auth.adminPanel')) ?></span>
					</div>
					<div class="simple-content">
						<div class="login-form">
							<form name="frm_login" action="loginPost" class="simple-form" method="post">
							    <?= csrf_field() ?>
								<?php if(session()->getFlashdata('msg')):?>
									<div class="alert alert-warning" role="alert"><?= esc(session()->getFlashdata('msg')) ?></div>
								<?php endif;?>
								<div class="field prepend">
									<input id="" name="username" autofocus="autofocus" class="text autofocus" placeholder="<?= esc(lang('Auth.username'), 'attr') ?>" required="required" tabindex="1" type="text" value=""/>
									<svg class="i"><use xlink:href="<?= base_url('assets/login/assets/login-icons.svg#person'); ?>"></use></svg>
								</div>
								<div class="field prepend">
									<input id="" name="password" autocorrect="off" autocapitalize="off" spellcheck="false" class="password" placeholder="<?= esc(lang('Auth.password'), 'attr') ?>" required="required" tabindex="2" type="password" value=""/>
									<svg class="i"><use xlink:href="<?= base_url('assets/login/assets/login-icons.svg#lock-locked'); ?>"></use></svg>
									<a class="login-swap tt" tabindex="4" title="<?= esc(lang('Auth.forgotTitle'), 'attr') ?>" href="<?= base_url('/forgot_password'); ?>">?</a>
								</div>
								<div class="simple-btns">
									<button class="btn btn-primary" tabindex="3" type="submit"><svg class="i"><use xlink:href="<?= base_url('assets/login/assets/login-icons.svg#account-login'); ?>"></use></svg><?= esc(lang('Auth.login')) ?></button>
								</div>
							</form>
						</div>
					</div> 
				</div>
			</div>
		</div>
	</div>	
	<!-- Modal untuk notifikasi -->
			<div class="modal fade" id="notificationModal" tabindex="-1" role="dialog" aria-labelledby="notificationModalLabel" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="notificationModalLabel"><?= esc(lang('Auth.notification')) ?></h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="<?= esc(lang('Auth.close'), 'attr') ?>" id="closeModalButton">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body text-center">
                            <p id="modalMessage"></p>
                            <div id="checkmarkIcon" style="display: none;">
                                <i class="fas fa-check-circle fa-3x text-success"></i>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal" id="closeModalButton"><?= esc(lang('Auth.close')) ?></button>
                        </div>
                    </div>
                </div>
            </div>


            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    <?php if (session()->getFlashdata('error')): ?>
                        document.getElementById('modalMessage').innerText = <?= json_encode((string) session()->getFlashdata('error'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                        document.getElementById('checkmarkIcon').style.display = 'none'; // Hide checkmark for error
                        $('#notificationModal').modal('show');
                    <?php endif; ?>

                    <?php if (session()->getFlashdata('success')): ?>
                        document.getElementById('modalMessage').innerText = <?= json_encode((string) session()->getFlashdata('success'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                        document.getElementById('checkmarkIcon').style.display = 'block'; // Show checkmark for success
                        $('#notificationModal').modal('show');
                    <?php endif; ?>

                    // Redirect to dashboard when modal is closed after success
                    $('#notificationModal').on('hidden.bs.modal', function (e) {
                        // Check if the success message is present
                        <?php if (session()->getFlashdata('success')): ?>
                            window.location.href = '<?= base_url('dashboard') ?>';
                        <?php endif; ?>
                    });
                });
            </script>


<!DOCTYPE html>
<html lang="<?= esc(service('request')->getLocale(), 'attr') ?>">
<head>
<title><?= esc(lang('Auth.title')) ?></title>
<?= $this->include('partials/shared/favicon') ?>
	<!-- Meta-Tags -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="utf-8">
    <meta name="keywords" content="Exact login Form a Responsive Web Template, Bootstrap Web Templates, Flat Web Templates, Android Compatible Web Template, Smartphone Compatible Web Template, Free Webdesigns for Nokia, Samsung, LG, Sony Ericsson, Motorola Web Design">
    
	<?php // Deferred: the 1.4 MB icon kit only draws the success check in the modal, so it must not block the page. ?>
	<script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous" defer></script>
	<link rel="preload" href="<?= base_url('assets/login/images/couch_light.png'); ?>" as="image">
	<style>
		.spinner-border {
            display: none;
        }

        #checkmarkIcon {
            animation: checkmarkAnimation 1s ease-in-out;
        }

        @keyframes checkmarkAnimation {
            0% { opacity: 0; transform: scale(0.8); }
            50% { opacity: 1; transform: scale(1.1); }
            100% { opacity: 1; transform: scale(1); }
        }
	</style>
	<script>
        addEventListener("load", function () {
            setTimeout(hideURLbar, 0);
        }, false);

        function hideURLbar() {
            window.scrollTo(0, 1);
        }
    </script>
    <!-- //Meta-Tags -->

	<!-- Custom Theme files -->
	<link href="<?= base_url('assets/login/css/style.css'); ?>" rel="stylesheet" type="text/css" media="all" />
	<link rel="stylesheet" href="<?= base_url('assets/login/font-awesome/css/font-awesome.min.css'); ?>">
	<!-- //Custom Theme files -->

	<link rel="stylesheet" href="<?= base_url('assets/login/css/bootstrap.min.css'); ?>" >
	<link rel="stylesheet" href="<?= base_url('assets/shared/css/lang-switch.css'); ?>" >
	<link rel="stylesheet" href="<?= base_url('assets/login/css/motion.css'); ?>" >
	
	<!-- web font --> 
	<link href="//fonts.googleapis.com/css?family=Raleway:100,100i,200,200i,300,300i,400,400i,500,500i,600,600i,700,700i,800,800i,900,900i&amp;subset=latin-ext" rel="stylesheet">
	<!-- //web font --> 
	
    <?php // Deferred in order (jQuery, then Bootstrap); both run before DOMContentLoaded, where the page scripts use $. ?>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js" defer></script>
    <script src="<?= base_url('assets/login/js/bootstrap.bundle.min.js') ?>" defer></script>
</head>
<body>
	<?php // Language control (PRD LANG-02). Wrapped so the shared mobile top padding never shifts this page. ?>
	<div><div class="nfc-lang-corner"><?= $this->setVar('locale', service('request')->getLocale())->include('partials/shared/lang-switch') ?></div></div>
	<!-- main content -->

	<?= $this->include($page); ?>

	<!-- //main -->
	<script src="<?= base_url('assets/shared/js/lang-switch.js') ?>"></script>

	
</body>
</html>
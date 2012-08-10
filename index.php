<?php
	require_once ('component/system.php');

	$_system = new system();

	//print_r($_system );

	$_template = @$_GET['template'];
	$_page = @isset($_GET['page']) ? $_GET['page'] : 0;
	$_id = @isset($_GET['id']) ? $_GET['id'] : 0;

	/*echo ('template:' . $_template . '<br />');
	echo ('page:' . $_page . '<br />');
	echo ('id:' . $_id . '<br />');*/

	$_system->display($_template, $_id, $_page);
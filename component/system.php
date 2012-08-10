<?php
	class system {

		private $_setting = array ();

		private $_connection = null;

		public function __construct ($setting = array ()) {
			
			$this->_setting['path']['root'] = str_replace ('/component/system.php', '', str_replace ('\\', '/', __FILE__));

			$this->_setting += $setting;

			if (!isset($this->_setting['directory']['upload'])) {
				$this->_setting['directory']['upload'] = '/upload/';
			}

			/* database setting */

			if (!isset($this->_setting['database']['type'])) {
				$this->_setting['database']['type'] = 'sqlite';
			}

			if ($this->_setting['database']['type'] == 'sqlite' && !isset($this->_setting['directory']['database'])) {
				$this->_setting['directory']['database'] = '/database/';
			}

			if ($this->_setting['database']['type'] == 'sqlite' && !isset($this->_setting['database']['name'])) {
				$this->_setting['database']['name'] = 'database.sqlite';
			}

			if (!isset($this->_setting['database']['option'])) {
				$this->_setting['database']['option'] = array();
			}

			if (!isset($this->_setting['database']['prefix'])) {
				$this->_setting['database']['prefix'] = '';
			}

			/* template setting */

			if (!isset($this->_setting['template']['extension'])) {
				$this->_setting['template']['extension'] = '.php';
			}

			if (!isset($this->_setting['directory']['theme'])) {
				$this->_setting['directory']['theme'] = '/';
			}

		}

		public function __destruct () {
			
			unset ($this->_setting);
			unset ($this->_connection);

		}

		/* system function */

		private function _clean ($element) {
			
			if (!is_array ($element)) {
				$element = htmlentities($element, ENT_QUOTES, 'utf-8');
			}
			else {
				foreach ($element as $key => $value) {
					$element[$key] = $this->_clean ($value);
				}
			}

			return $element;

		}

		public function upload ($file, $type = array ('jpg', 'jpeg', 'gif', 'png')) {
			$_result = '';
			
			$_extension = strtolower (end (explode ('.', $_FILES[$file]['name'])));
			
			if (in_array ($_extension, $type)) {
				if ($_extension == 'jpeg') {
					$_extension = 'jpg';
				}
				$_directory = $this->_setting['directory']['upload'] . $_extension . '/' . date ('Y/m/d') . '/';
				$_filename =  date ('His') . sprintf ("%03d", rand(0, 100)) . '.' . $_extension;

				if (!is_dir ($this->_setting['path']['root'] . $_directory)) {
					mkdir($this->_setting['path']['root'] . $_directory, 0666, true) or die ('[Error] can not create upload directory.' . PHP_EOL);
				}

				move_uploaded_file ($_FILES[$file]['tmp_name'], $this->_setting['path']['root'] . $_directory . $_filename) or die ('[Error] can not upload file.' . PHP_EOL);

				$_result = $_directory . $_filename;

			}
			
			return $_result;

		}

		/* database function */

		private function _connect () {

			switch ($this->_setting['database']['type']) {
				case 'sqlite':
					try {
						$this->_connection = new PDO ('sqlite:' . $this->_setting['path']['root'] . $this->_setting['directory']['database'] . $this->_setting['database']['name'], null, null, $this->_setting['database']['option']);
					}
					catch (PDOException $_exception) {
						die ('[Error] ' . $_exception -> getMessage () . PHP_EOL);
					}
					break;
				case 'mysql':
					try {
						$this->ç = new PDO ('mysql:dbname=' . $this->_setting['database']['name'] . ';host=' . $this->_setting['database']['host'],  $this->_setting['database']['username'],  $this->_setting['database']['password'], $this->_setting['database']['option']);
					}
					catch (PDOException $_exception) {
						die ('[Error] ' . $_exception -> getMessage () . PHP_EOL);
					}
					break;
			}

		}

		private function _query ($column, $table, $where = '', $order = '', $limit = 0, $skip = 0) {

			$_sql = 'select ' . $column . ' from ' . $this->_setting['database']['prefix'] . $table . ($where != '' ? ' where ' . $where : '') . ($order != '' ? ' order by ' . $order : '') . ($this->_setting['database']['type'] == 'sqlite' && $limit != 0 ? ' limit ' . $limit . ' offset ' . $skip : '') . ($this->_setting['database']['type'] == 'mysql' && $limit != 0 ? ' limit ' . $skip . ', ' . $limit : '');

			if ($this->_connection == null) {
				$this->_connect ();
			}

			if ($limit == 1) {
				return $this->_connection->query ($_sql)->fetch ();
			}
			else {
				return $this->_connection->query ($_sql)->fetchAll ();
			}

		}

		private function _execute ($sql) {
			
			if ($this->_connection == null) {
				$this->_connect ();
			}

			return $this->_connection->exec ($sql) or die (print_r ($this->_connection->errorInfo(), ture));

		}

		/* template function */

		private function _compile($template) {
			
			$_cache = $this->_setting['path']['root'] . '/cache/' . substr (md5 ($template), 8, -8) . substr (md5 (filemtime ($template)), 8, -8) . '.php';

			if (is_file ($_cache)) {
				return $_cache;
			}
			else {
				$_content = '';

				$_handle = fopen ($template, 'r') or die ('[Error] can not open \'' . $template . '\'.'. PHP_EOL);
				while (!feof ($_handle)) {
					$_content .= fgets ($_handle);
				}
				fclose ($_handle);

				$_pattern[] = '/<!--\s*\[(.*?)\s*=\s*(.*?)\]\s*-->/';
				$_pattern[] = '/<!--\s*#include\s*file\s*=\s*[\'"](.*)[\'"]\s*-->/';
				$_pattern[] = '/<!--\s*\{(.*?)\}\s*-->/';
				$_pattern[] = '/<!--\s*foreach\s*\((.*)\)\s*{\s*-->/';
				$_pattern[] = '/<!--\s*}\s*-->/';

				$_replacement[] = '<?php \\1 = $this->_\\2; ?>';
				$_replacement[] = '<?php include ($this->_compile ($this->_setting[\'path\'][\'root\'] . \'/template\' . $this->_setting[\'directory\'][\'theme\'] . \'\\1\')); ?>';
				$_replacement[] = '<?php echo (\\1); ?>';
				$_replacement[] = '<?php foreach (\\1) { ?>';
				$_replacement[] = '<?php } ?>';

				$_content = preg_replace ($_pattern, $_replacement, $_content);

				if (!is_dir ($this->_setting['path']['root'] . '/cache/')) {
					mkdir($this->_setting['path']['root'] . '/cache/', 0666, true) or die ('[Error] can not create cache directory.' . PHP_EOL);
				}

				foreach (glob ($this->_setting['path']['root'] . '/cache/' . '*.php') as $_file) {

					if (substr ($_file, 0, -(16 + strlen ($this->_setting['template']['extension']))) == substr ($_cache, 0, -(16 + strlen ($this->_setting['template']['extension'])))) {
						unlink ($_file);
					}
				}

				$_handle = fopen ($_cache, 'a') or die ('[Error] can not open \'' . $_cache . '\'.' . PHP_EOL);

				flock ($_handle, LOCK_EX + LOCK_NB);

				fwrite ($_handle, $_content) or die ('[Error] can not write to \'' . $_cache . '\'.' . PHP_EOL);

				flock ($_handle, LOCK_UN + LOCK_NB);

				return $_cache;
			}
			
		}

		public function display($template, $id, $page) {
			
			$_template = $template;

			if ($_template == '') {
				$_template = 'index.html';
			}

			if (strripos ($_template, '/') == strlen ($_template) - 1) {
				$_template .= 'index.html';
			}

			$_template = strpos ($_template, '.') > 0 ? substr ($_template, 0, strripos ($_template, '.')) . $this->_setting['template']['extension'] : $_template . $this->_setting['template']['extension'];
 
			if (is_file ($this->_setting['path']['root'] . '/template' . $this->_setting['directory']['theme'] . $_template)) {
				ob_start ();

				include ($this->_compile ($this->_setting['path']['root'] . '/template' . $this->_setting['directory']['theme'] . $_template));
				$_content = ob_get_contents ();

				ob_end_clean ();

				echo ($_content);
			}
			else {
				die ('[Error] template file \'' . $_template . '\' not found.' . PHP_EOL);
			}

		}

	}
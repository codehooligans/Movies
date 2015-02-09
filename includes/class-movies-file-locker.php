<?php
if (!class_exists('MoviesFileLocker')) {
	class MoviesFileLocker {

		private $lockFolder;
		private $item_key;
		private $data_item_key;
		private $lock_fp;
		private $has_lock;
		var $locker_info = array();
		
		function __construct($locker_file = '') {
			if (empty($locker_file)) return;
			
			$this->lock_file_full 	= $locker_file;
			$this->has_lock			= false;

			$this->lock_fp = fopen($this->lock_file_full, 'c+');
			$this->is_locked();
		}
		
		// See this bug on PHP flock third argument https://bugs.php.net/bug.php?id=31189&edit=2
		function __destruct() {
			if ($this->lock_fp) {
				flock($this->lock_fp, LOCK_UN);
				fclose($this->lock_fp);	
				unset($this->lock_fp);
			}
		}
	
		function is_locked() {
			if ($this->lock_fp) {
				if (flock($this->lock_fp, LOCK_EX | LOCK_NB)) {
					$this->has_lock = true; 
				} else {
					$this->has_lock = false; 
				}
			}
			return $this->has_lock;
		}

		function set_locker_info($locker_info = array()) {
			// Only the locking process can write to the file. 
			if ($this->is_locked()) {
				rewind($this->lock_fp);
				$locker_info['time_start'] = time();
				$locker_info['pid'] = getmypid();
				$write_ret = fwrite($this->lock_fp, serialize($locker_info) ."\r\n");
				fflush($this->lock_fp);
			}
		}
		
		function get_locker_info($info_key='') {
			if ($this->lock_fp) {
				rewind($this->lock_fp);
				$locker_info = fgets($this->lock_fp, 4096);
				if ($locker_info) {
					$locker_info = maybe_unserialize($locker_info);
					$locker_info['has_lock'] = $this->is_locked();
					if (strlen($info_key)) {
						if (isset($locker_info[$info_key]))
							return $locker_info[$info_key];
						else
							return false;
					}
					return $locker_info;
				}
				return false;
			} 
			return false;
		}
	}
}
<?php
// Backward compatibility for WP versions older than 3.3, in which the method MO::export does not exist
// FIXME to be removed once WP 3.2.1 and older versions is no more supported

	function pll_mo_export($mo) {
		$tmp_fh = fopen("php://temp", 'r+');
		if ( !$tmp_fh ) return false;
		pll_mo_export_to_file_handle( $mo, $tmp_fh );
		rewind( $tmp_fh );
		return stream_get_contents( $tmp_fh );
	}
	
	function pll_mo_export_to_file_handle($mo, $fh) {
		$entries = array_filter($mo->entries, create_function('$e', 'return !empty($e->translations);'));
		ksort($entries);
		$magic = 0x950412de;
		$revision = 0;
		$total = count($entries) + 1; // all the headers are one entry
		$originals_lenghts_addr = 28;
		$translations_lenghts_addr = $originals_lenghts_addr + 8 * $total;
		$size_of_hash = 0;
		$hash_addr = $translations_lenghts_addr + 8 * $total;
		$current_addr = $hash_addr;
		fwrite($fh, pack('V*', $magic, $revision, $total, $originals_lenghts_addr,
			$translations_lenghts_addr, $size_of_hash, $hash_addr));
		fseek($fh, $originals_lenghts_addr);
		
		// headers' msgid is an empty string
		fwrite($fh, pack('VV', 0, $current_addr));
		$current_addr++;
		$originals_table = chr(0);

		foreach($entries as $entry) {
			$originals_table .= $mo->export_original($entry) . chr(0);
			$length = strlen($mo->export_original($entry));
			fwrite($fh, pack('VV', $length, $current_addr));
			$current_addr += $length + 1; // account for the NULL byte after
		}
		
		$exported_headers = $mo->export_headers();
		fwrite($fh, pack('VV', strlen($exported_headers), $current_addr));
		$current_addr += strlen($exported_headers) + 1;
		$translations_table = $exported_headers . chr(0);
		
		foreach($entries as $entry) {
			$translations_table .= $mo->export_translations($entry) . chr(0);
			$length = strlen($mo->export_translations($entry));
			fwrite($fh, pack('VV', $length, $current_addr));
			$current_addr += $length + 1;
		}
		
		fwrite($fh, $originals_table);
		fwrite($fh, $translations_table);
		return true;
	}
?>

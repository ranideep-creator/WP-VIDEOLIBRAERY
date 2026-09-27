(function () {
	'use strict';

	var btn = document.getElementById( 'lvl-sync-btn' );
	var resultBox = document.getElementById( 'lvl-sync-result' );
	if ( ! btn || typeof lvlAdmin === 'undefined' ) {
		return;
	}

	btn.addEventListener( 'click', function () {
		var originalText = btn.textContent;
		btn.disabled = true;
		btn.textContent = 'Syncing\u2026';
		resultBox.className = 'lvl-sync-result';
		resultBox.textContent = '';

		var body = new URLSearchParams();
		body.set( 'action', 'lvl_sync_now' );
		body.set( 'nonce', lvlAdmin.nonce );

		fetch( lvlAdmin.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		} )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( res ) {
				if ( res.success ) {
					var d = res.data;
					resultBox.className = 'lvl-sync-result success';
					resultBox.textContent =
						'Done \u2014 ' + d.inserted + ' new, ' + d.updated + ' updated, ' + d.skipped +
						' skipped. ' + d.total_now + ' videos in the library now.';
					var totalEl = document.getElementById( 'lvl-stat-videos' );
					if ( totalEl ) {
						totalEl.textContent = d.total_now;
					}
				} else {
					resultBox.className = 'lvl-sync-result error';
					resultBox.textContent = res.data && res.data.message ? res.data.message : 'Sync failed.';
				}
			} )
			.catch( function () {
				resultBox.className = 'lvl-sync-result error';
				resultBox.textContent = 'Sync failed \u2014 could not reach the server.';
			} )
			.then( function () {
				btn.disabled = false;
				btn.textContent = originalText;
			} );
	} );
})();

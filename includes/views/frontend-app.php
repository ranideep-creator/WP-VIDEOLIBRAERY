<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="lvl-app" id="lvl-app">
	<div class="lvl-header">
		<div class="lvl-wordmark">Lokahitam</div>
		<div class="lvl-search-wrap">
			<input type="search" id="lvl-search" class="lvl-search" placeholder="Search episodes&hellip;" aria-label="Search videos">
		</div>
	</div>

	<div class="lvl-crumbs" id="lvl-crumbs" hidden>
		<button type="button" class="lvl-back" id="lvl-back">&larr; All series</button>
		<h2 class="lvl-current-title" id="lvl-current-title"></h2>
	</div>

	<div class="lvl-status" id="lvl-status"></div>

	<div class="lvl-grid lvl-grid-series" id="lvl-series-grid"></div>
	<div class="lvl-grid lvl-grid-videos" id="lvl-video-grid" hidden></div>

	<div class="lvl-loadmore-wrap">
		<button type="button" class="lvl-loadmore" id="lvl-loadmore" hidden>Load more</button>
	</div>

	<div class="lvl-modal" id="lvl-modal" hidden>
		<div class="lvl-modal-scrim" id="lvl-modal-scrim"></div>
		<div class="lvl-modal-panel" role="dialog" aria-modal="true" aria-label="Video player">
			<button type="button" class="lvl-modal-close" id="lvl-modal-close" aria-label="Close">&times;</button>
			<div class="lvl-modal-frame">
				<iframe id="lvl-modal-iframe" src="" title="Video player" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
			</div>
			<div class="lvl-modal-info">
				<h3 id="lvl-modal-title"></h3>
				<p id="lvl-modal-meta"></p>
			</div>
		</div>
	</div>
</div>

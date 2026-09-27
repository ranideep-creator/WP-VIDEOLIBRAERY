(function () {
	'use strict';

	var app = document.getElementById( 'lvl-app' );
	if ( ! app || typeof lvlData === 'undefined' ) {
		return;
	}

	var restUrl = lvlData.restUrl;
	var perPage = lvlData.perPage || 24;

	var seriesGrid = document.getElementById( 'lvl-series-grid' );
	var videoGrid = document.getElementById( 'lvl-video-grid' );
	var crumbs = document.getElementById( 'lvl-crumbs' );
	var currentTitle = document.getElementById( 'lvl-current-title' );
	var backBtn = document.getElementById( 'lvl-back' );
	var searchInput = document.getElementById( 'lvl-search' );
	var statusEl = document.getElementById( 'lvl-status' );
	var loadMoreBtn = document.getElementById( 'lvl-loadmore' );
	var modal = document.getElementById( 'lvl-modal' );
	var modalScrim = document.getElementById( 'lvl-modal-scrim' );
	var modalClose = document.getElementById( 'lvl-modal-close' );
	var modalIframe = document.getElementById( 'lvl-modal-iframe' );
	var modalTitle = document.getElementById( 'lvl-modal-title' );
	var modalMeta = document.getElementById( 'lvl-modal-meta' );

	var state = {
		view: 'series', // 'series' | 'videos' | 'search'
		playlist: '',
		query: '',
		page: 1,
		totalPages: 1,
		loading: false,
	};

	var searchTimer = null;

	var PLAY_ICON =
		'<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">' +
		'<circle cx="12" cy="12" r="12" fill="rgba(246,239,220,0.95)"/>' +
		'<path d="M9.5 7.5L17 12L9.5 16.5V7.5Z" fill="#2b2440"/></svg>';

	function fmtViews( n ) {
		n = parseInt( n, 10 ) || 0;
		if ( n >= 1000000 ) {
			return ( n / 1000000 ).toFixed( 1 ).replace( /\.0$/, '' ) + 'M views';
		}
		if ( n >= 1000 ) {
			return ( n / 1000 ).toFixed( 1 ).replace( /\.0$/, '' ) + 'K views';
		}
		return n + ( 1 === n ? ' view' : ' views' );
	}

	function setStatus( text, isEmpty ) {
		statusEl.textContent = text || '';
		statusEl.className = 'lvl-status' + ( isEmpty ? ' lvl-status-empty' : '' );
	}

	function loadSeries() {
		state.view = 'series';
		state.playlist = '';
		crumbs.hidden = true;
		videoGrid.hidden = true;
		loadMoreBtn.hidden = true;
		seriesGrid.hidden = false;
		seriesGrid.innerHTML = '';
		setStatus( 'Loading series\u2026' );

		fetch( restUrl + '/playlists' )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( data ) {
				if ( ! data.length ) {
					setStatus( 'No series yet \u2014 run a sync from the admin panel to bring in videos.', true );
					return;
				}
				setStatus( '' );
				data.forEach( function ( p ) {
					seriesGrid.appendChild( buildSeriesCard( p ) );
				} );
			} )
			.catch( function () {
				setStatus( 'Could not load the library right now. Please try again shortly.', true );
			} );
	}

	function buildSeriesCard( p ) {
		var card = document.createElement( 'button' );
		card.type = 'button';
		card.className = 'lvl-series-card';

		var cover = document.createElement( 'div' );
		cover.className = 'lvl-series-cover';
		var covers = p.covers && p.covers.length ? p.covers.slice( 0, 4 ) : [];
		for ( var i = 0; i < 4; i++ ) {
			var img = document.createElement( 'img' );
			img.loading = 'lazy';
			img.alt = '';
			img.src = covers.length ? covers[ i % covers.length ] : '';
			cover.appendChild( img );
		}

		var body = document.createElement( 'div' );
		body.className = 'lvl-series-body';

		var name = document.createElement( 'div' );
		name.className = 'lvl-series-name';
		name.textContent = p.playlist;

		var count = document.createElement( 'div' );
		count.className = 'lvl-series-count';
		count.textContent = p.count + ( 1 === p.count ? ' episode' : ' episodes' );

		body.appendChild( name );
		body.appendChild( count );
		card.appendChild( cover );
		card.appendChild( body );

		card.addEventListener( 'click', function () {
			openPlaylist( p.playlist );
		} );

		return card;
	}

	function openPlaylist( playlist ) {
		state.view = 'videos';
		state.playlist = playlist;
		state.query = '';
		state.page = 1;
		searchInput.value = '';

		currentTitle.textContent = playlist;
		crumbs.hidden = false;
		seriesGrid.hidden = true;
		videoGrid.hidden = false;
		videoGrid.innerHTML = '';

		fetchVideos( false );
	}

	function fetchVideos( append ) {
		if ( state.loading ) {
			return;
		}
		state.loading = true;
		loadMoreBtn.disabled = true;
		if ( ! append ) {
			setStatus( 'Loading episodes\u2026' );
		}

		var params = new URLSearchParams();
		params.set( 'page', state.page );
		params.set( 'per_page', perPage );
		if ( state.playlist ) {
			params.set( 'playlist', state.playlist );
		}
		if ( state.query ) {
			params.set( 'q', state.query );
		}

		fetch( restUrl + '/videos?' + params.toString() )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( data ) {
				state.loading = false;
				state.totalPages = data.total_pages;

				if ( ! append ) {
					videoGrid.innerHTML = '';
				}

				if ( ! data.items.length && ! append ) {
					setStatus(
						state.query
							? 'No episodes match "' + state.query + '". Try another title or clear the search.'
							: 'No episodes here yet.',
						true
					);
				} else {
					setStatus( '' );
					data.items.forEach( function ( v ) {
						videoGrid.appendChild( buildVideoCard( v ) );
					} );
				}

				loadMoreBtn.hidden = state.page >= state.totalPages;
				loadMoreBtn.disabled = false;
			} )
			.catch( function () {
				state.loading = false;
				setStatus( 'Could not load episodes right now. Please try again shortly.', true );
				loadMoreBtn.disabled = false;
			} );
	}

	function buildVideoCard( v ) {
		var card = document.createElement( 'button' );
		card.type = 'button';
		card.className = 'lvl-video-card';

		var thumbWrap = document.createElement( 'div' );
		thumbWrap.className = 'lvl-video-thumb';

		var img = document.createElement( 'img' );
		img.loading = 'lazy';
		img.alt = '';
		img.src = v.thumb;
		thumbWrap.appendChild( img );

		if ( v.part_number ) {
			var partBadge = document.createElement( 'span' );
			partBadge.className = 'lvl-video-part';
			partBadge.textContent = 'Part ' + v.part_number;
			thumbWrap.appendChild( partBadge );
		}

		if ( v.duration_formatted ) {
			var dur = document.createElement( 'span' );
			dur.className = 'lvl-video-duration';
			dur.textContent = v.duration_formatted;
			thumbWrap.appendChild( dur );
		}

		var play = document.createElement( 'span' );
		play.className = 'lvl-video-play';
		play.innerHTML = PLAY_ICON;
		thumbWrap.appendChild( play );

		var body = document.createElement( 'div' );
		body.className = 'lvl-video-body';

		var title = document.createElement( 'div' );
		title.className = 'lvl-video-title';
		title.textContent = v.title;

		var meta = document.createElement( 'div' );
		meta.className = 'lvl-video-meta';
		meta.textContent = fmtViews( v.views ) + ( 'search' === state.view && v.playlist ? ' \u00b7 ' + v.playlist : '' );

		body.appendChild( title );
		body.appendChild( meta );
		card.appendChild( thumbWrap );
		card.appendChild( body );

		card.addEventListener( 'click', function () {
			openVideo( v );
		} );

		return card;
	}

	function openVideo( v ) {
		modalIframe.src = 'https://www.youtube.com/embed/' + encodeURIComponent( v.video_id ) + '?autoplay=1&rel=0';
		modalTitle.textContent = v.title;
		modalMeta.textContent = fmtViews( v.views ) + ( v.likes ? ' \u00b7 ' + v.likes + ' likes' : '' );
		modal.hidden = false;
		document.body.style.overflow = 'hidden';
	}

	function closeModal() {
		modal.hidden = true;
		modalIframe.src = '';
		document.body.style.overflow = '';
	}

	backBtn.addEventListener( 'click', loadSeries );

	loadMoreBtn.addEventListener( 'click', function () {
		state.page++;
		fetchVideos( true );
	} );

	searchInput.addEventListener( 'input', function () {
		var q = searchInput.value.trim();
		clearTimeout( searchTimer );
		searchTimer = setTimeout( function () {
			state.query = q;
			state.page = 1;

			if ( q ) {
				state.view = 'search';
				state.playlist = '';
				crumbs.hidden = false;
				currentTitle.textContent = 'Search results for \u201c' + q + '\u201d';
				seriesGrid.hidden = true;
				videoGrid.hidden = false;
				videoGrid.innerHTML = '';
				fetchVideos( false );
			} else if ( 'search' === state.view ) {
				loadSeries();
			} else if ( 'videos' === state.view ) {
				fetchVideos( false );
			}
		}, 350 );
	} );

	modalScrim.addEventListener( 'click', closeModal );
	modalClose.addEventListener( 'click', closeModal );
	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key && ! modal.hidden ) {
			closeModal();
		}
	} );

	loadSeries();
})();

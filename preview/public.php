<?php
/**
 * Standalone visual-review fixture for the public widget.
 *
 * Use ?state=ready, loading, empty, error, or story to review key states.
 * Add ?teaser=1 for the four-card, four-column newest-first review fixture.
 */

$allowed_states = array( 'ready', 'loading', 'empty', 'error', 'story' );
$state          = isset( $_GET['state'] ) ? preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $_GET['state'] ) ) : 'ready';
$same_site      = ! empty( $_GET['same_site'] );
$hide_breed     = ! empty( $_GET['hide_breed'] );
$teaser         = ! empty( $_GET['teaser'] );

if ( ! in_array( $state, $allowed_states, true ) ) {
	$state = 'ready';
}

$pets = array(
	array( 'Mochi', 'Domestic Short Hair', 'Young', 'Medium', 'St. Petersburg, FL', 'https://placecats.com/900/675?position=top&pet=mochi' ),
	array( 'Juniper', 'Tortoiseshell', 'Adult', 'Small', 'St. Petersburg, FL', 'https://placecats.com/900/675?position=top&pet=juniper' ),
	array( 'Otis', 'Tabby', 'Kitten', 'Small', 'St. Petersburg, FL', 'https://placecats.com/900/675?position=top&pet=otis' ),
	array( 'Clementine', 'Calico', 'Adult', 'Medium', 'St. Petersburg, FL', 'https://placecats.com/900/675?position=top&pet=clementine' ),
	array( 'Louie', 'Domestic Long Hair', 'Young', 'Large', 'St. Petersburg, FL', 'https://placecats.com/900/675?position=top&pet=louie' ),
	array( 'Pepper', 'Tuxedo', 'Adult', 'Medium', 'St. Petersburg, FL', 'https://placecats.com/900/675?position=top&pet=pepper' ),
);

if ( $teaser ) {
	$pets = array_slice( $pets, 0, 4 );
}

$preview_columns = $teaser ? 4 : 3;

function preview_icon( $name ) {
	$paths = array(
		'book'    => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H11v16H6.5A2.5 2.5 0 0 0 4 21.5v-16ZM20 5.5A2.5 2.5 0 0 0 17.5 3H13v16h4.5a2.5 2.5 0 0 1 2.5 2.5v-16Z"></path>',
		'mail'    => '<rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m3 7 9 6 9-6"></path>',
		'shuffle' => '<path d="M16 3h5v5M4 20 21 3M21 16v5h-5M15 15l6 6M4 4l5 5"></path>',
		'refresh' => '<path d="M20 7v5h-5M4 17v-5h5"></path><path d="M6.1 9a7 7 0 0 1 11.7-2.6L20 9M4 15l2.2 2.6A7 7 0 0 0 17.9 15"></path>',
		'paw'     => '<ellipse cx="12" cy="15.5" rx="4.6" ry="3.8"></ellipse><circle cx="5.5" cy="10" r="2"></circle><circle cx="9.5" cy="6.5" r="2"></circle><circle cx="14.5" cy="6.5" r="2"></circle><circle cx="18.5" cy="10" r="2"></circle>',
		'close'   => '<path d="m6 6 12 12M18 6 6 18"></path>',
	);

	return '<svg class="pm-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

function preview_state_panel( $icon, $title, $text, $actions = '' ) {
	return '<div class="pm-state"><div class="pm-state-icon" aria-hidden="true">' . preview_icon( $icon ) . '</div><h4 class="pm-state-title">' . htmlspecialchars( $title ) . '</h4><p class="pm-state-text">' . htmlspecialchars( $text ) . '</p>' . $actions . '</div>';
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Purrfect Match - Public Widget Preview</title>
	<link rel="stylesheet" href="../assets/css/purrfect-match.css">
	<style>
		html, body { margin: 0; min-height: 100%; background: #eee9e2; }
		body { padding: clamp(14px, 3vw, 42px); }
		.preview-note { max-width: 76rem; margin: 0 auto 14px; color: #6f675d; font: 700 12px/1.4 "Avenir Next", Avenir, "Segoe UI", sans-serif; letter-spacing: .08em; text-transform: uppercase; }
		@media (max-width: 520px) { body { padding: 10px; } .preview-note { margin: 4px 4px 10px; } }
	</style>
</head>
<body>
	<div class="preview-note">Purrfect Match 1.8.0 / Public adoption grid / <?php echo htmlspecialchars( $state ); ?><?php echo $same_site ? ' / same-site embed' : ''; ?><?php echo $hide_breed ? ' / breed hidden' : ''; ?><?php echo $teaser ? ' / newest teaser' : ''; ?></div>
	<section class="pm-wrap pm-cols-<?php echo $preview_columns; ?>" id="pm-preview" style="--pm-brand:#e93396;--pm-brand-rgb:233,51,150;--pm-on-brand:#1b1714;--pm-cols:<?php echo $preview_columns; ?>" aria-labelledby="pm-preview-title">
		<div class="pm-shell">
			<header class="pm-banner">
				<span class="pm-blob pm-blob-1" aria-hidden="true"></span>
				<span class="pm-blob pm-blob-2" aria-hidden="true"></span>
				<div class="pm-banner-row">
					<div class="pm-banner-intro">
						<div class="pm-eyebrow"><svg class="pm-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"></path></svg><span>CJ Paws</span><span class="pm-eyebrow-dot" aria-hidden="true"></span><span class="pm-eyebrow-sub">Adoptable cats</span></div>
						<h2 class="pm-title" id="pm-preview-title">Find your purr‑fect match</h2>
						<p class="pm-subtitle">Filter by breed, size, and age, then meet the friend who feels like home.</p>
						<div class="pm-links"><?php if ( ! $same_site ) : ?><a class="pm-link" href="#"><svg class="pm-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"></path></svg>Visit cjpaws.org</a><?php endif; ?><span class="pm-powered pm-sr-only">Powered by CJ Paws</span></div>
					</div>
					<div class="pm-hero-mark" aria-hidden="true"><svg viewBox="0 0 120 120"><ellipse cx="60" cy="74" rx="27" ry="22"></ellipse><circle cx="27" cy="47" r="11"></circle><circle cx="49" cy="31" r="11"></circle><circle cx="75" cy="31" r="11"></circle><circle cx="96" cy="49" r="11"></circle></svg></div>
				</div>
			</header>

			<section class="pm-controls" aria-label="Filter adoptable pets">
				<div class="pm-controls-head">
					<div><h3 class="pm-controls-title">Refine your search</h3><p class="pm-controls-hint">Mix and match filters to find a new friend.</p></div>
					<div class="pm-actions"><button class="pm-btn" type="button"><?php echo preview_icon( 'shuffle' ); ?>Shuffle</button><button class="pm-btn pm-btn-quiet" type="button" hidden>Reset</button></div>
				</div>
				<div class="pm-filters pm-filters-<?php echo $hide_breed ? '2' : '3'; ?>">
					<?php if ( ! $hide_breed ) : ?><div class="pm-field"><label class="pm-label" for="preview-breed">Breed</label><select class="pm-select" id="preview-breed"><option>All breeds</option><option>Domestic Short Hair</option><option>Tabby</option></select></div><?php endif; ?>
					<div class="pm-field"><label class="pm-label" for="preview-size">Size</label><select class="pm-select" id="preview-size"><option>All sizes</option><option>Small</option><option>Medium</option></select></div>
					<div class="pm-field"><label class="pm-label" for="preview-age">Age</label><select class="pm-select" id="preview-age"><option>All ages</option><option>Kitten</option><option>Young</option><option>Adult</option></select></div>
				</div>
				<div class="pm-meta"><div class="pm-count<?php echo 'loading' === $state ? ' is-loading' : ''; ?>"><?php if ( 'loading' === $state ) : ?><span class="pm-loading"><span class="pm-paws" aria-hidden="true"><i></i><i></i><i></i></span>Finding adoptable pets...</span><?php elseif ( in_array( $state, array( 'ready', 'story' ), true ) ) : ?><span class="pm-count-pill">Showing <?php echo count( $pets ); ?> adoptable cats</span><?php endif; ?></div><div class="pm-chips"><span class="pm-chip-tip">Choose a filter to narrow the list.</span></div></div>
			</section>

			<div class="pm-body">
				<div class="pm-grid" role="list">
					<?php if ( 'loading' === $state ) : ?>
						<?php for ( $i = 0; $i < count( $pets ); $i++ ) : ?>
							<div class="pm-skel" role="listitem" aria-hidden="true"><div class="pm-skel-media"></div><div class="pm-skel-body"><div class="pm-skel-line lg"></div><div class="pm-skel-line md"></div><div class="pm-skel-line sm"></div><div class="pm-skel-pill"></div></div></div>
						<?php endfor; ?>
					<?php elseif ( 'empty' === $state ) : ?>
						<?php echo preview_state_panel( 'paw', 'No pets match those filters', 'Try removing a filter to see more friends.', '<button class="pm-btn pm-btn-brand" type="button">Clear filters</button>' ); ?>
					<?php elseif ( 'error' === $state ) : ?>
						<?php echo preview_state_panel( 'refresh', 'Our live listings are taking a cat nap', 'You can still browse adoptable pets on our partner platforms.', '<div class="pm-state-actions"><a class="pm-btn pm-btn-brand" href="#">View on Adopt-a-Pet</a><a class="pm-btn" href="#">View on Petfinder</a></div><button class="pm-link-btn" type="button">Try again</button>' ); ?>
					<?php else : ?>
						<?php foreach ( $pets as $index => $pet ) : ?>
							<article class="pm-card pm-card--flip<?php echo 'story' === $state && 0 === $index ? ' is-flipped' : ''; ?>" role="listitem">
								<div class="pm-card-inner">
									<div class="pm-card-front">
										<a class="pm-media-link" href="#"><div class="pm-card-media"><img class="pm-card-img" src="<?php echo htmlspecialchars( $pet[5], ENT_QUOTES ); ?>" alt="Photo of <?php echo htmlspecialchars( $pet[0] ); ?>"><div class="pm-badge"><?php echo htmlspecialchars( $pet[2] . ' / ' . $pet[3] ); ?></div></div></a>
										<div class="pm-card-body"><div class="pm-card-head"><div><h3 class="pm-name"><a class="pm-name-link" href="#"><?php echo htmlspecialchars( $pet[0] ); ?></a></h3><div class="pm-breed"><?php echo htmlspecialchars( $pet[1] ); ?></div><div class="pm-loc"><?php echo htmlspecialchars( $pet[4] ); ?></div></div><div class="pm-card-mark pm-paw" aria-hidden="true"><?php echo preview_icon( 'paw' ); ?></div></div>
											<button class="pm-flip-btn" type="button"><?php echo preview_icon( 'book' ); ?><span>Read story</span></button>
											<div class="pm-cta-row"><a class="pm-cta pm-cta-adopt" href="#"><?php echo preview_icon( 'mail' ); ?><span>Apply to adopt</span></a><a class="pm-cta-view" href="#">View profile</a></div>
										</div>
									</div>
									<?php if ( 'story' === $state && 0 === $index ) : ?>
										<section class="pm-card-back" aria-hidden="false"><div class="pm-back-head"><span class="pm-back-name">Mochi</span><button class="pm-flip-btn pm-flip-close" type="button" aria-label="Hide Mochi's story"><?php echo preview_icon( 'close' ); ?></button></div><div class="pm-back-story">Mochi is a curious, affectionate companion who loves sunny windows and quiet afternoons. She is ready to meet a family who will give her time to settle in and plenty of gentle attention.</div><a class="pm-cta" href="#">Apply to adopt</a></section>
									<?php endif; ?>
								</div>
							</article>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
				<?php if ( in_array( $state, array( 'ready', 'story' ), true ) ) : ?><div class="pm-more"><button type="button" class="pm-btn pm-btn-brand">Load more pets</button></div><?php endif; ?>
				<footer class="pm-footer"><span>Listings provided by Petfinder</span><span>Made with care for adoptable pets.</span></footer>
			</div>
		</div>
	</section>
</body>
</html>

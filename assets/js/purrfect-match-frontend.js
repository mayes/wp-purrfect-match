(function () {
	'use strict';

	var config = window.purrfectMatchConfig || {};
	var i18n = config.i18n || {};

	/* ======================================================================
	   Store — Central data and filter state
	   ====================================================================== */
	var Store = {
		pets: [],
		filters: { age: '', gender: '', size: '', breed: '', search: '' },
		favoritesOnly: false,

		init: function (petsData) {
			this.pets = Array.isArray(petsData) ? petsData : [];
		},

		getFiltered: function () {
			var self = this;
			var favorites = Favorites.getIds();

			return this.pets.filter(function (pet) {
				if (self.favoritesOnly && favorites.indexOf(pet.id) === -1) {
					return false;
				}
				if (self.filters.age && (pet.age || '').toLowerCase() !== self.filters.age) {
					return false;
				}
				if (self.filters.gender && (pet.gender || '').toLowerCase() !== self.filters.gender) {
					return false;
				}
				if (self.filters.size && (pet.size || '').toLowerCase() !== self.filters.size) {
					return false;
				}
				if (self.filters.breed && (pet.breed_primary || '').toLowerCase() !== self.filters.breed) {
					return false;
				}
				if (self.filters.search) {
					var haystack = ((pet.name || '') + ' ' + (pet.breed_primary || '') + ' ' + (pet.description_plain || '')).toLowerCase();
					if (haystack.indexOf(self.filters.search) === -1) {
						return false;
					}
				}
				return true;
			});
		}
	};

	/* ======================================================================
	   Favorites — localStorage persistence
	   ====================================================================== */
	var Favorites = {
		KEY: 'purrfect_match_favorites',

		getIds: function () {
			try {
				return JSON.parse(localStorage.getItem(this.KEY)) || [];
			} catch (e) {
				return [];
			}
		},

		save: function (ids) {
			try {
				localStorage.setItem(this.KEY, JSON.stringify(ids));
			} catch (e) {
				// Storage full or unavailable.
			}
		},

		toggle: function (petId) {
			var ids = this.getIds();
			var idx = ids.indexOf(petId);
			if (idx > -1) {
				ids.splice(idx, 1);
			} else {
				ids.push(petId);
			}
			this.save(ids);
			return idx === -1; // true if added
		},

		isFavorite: function (petId) {
			return this.getIds().indexOf(petId) > -1;
		}
	};

	/* ======================================================================
	   LazyLoader — IntersectionObserver for images
	   ====================================================================== */
	var LazyLoader = {
		observer: null,

		init: function () {
			if (!('IntersectionObserver' in window)) {
				// Fallback: load all images immediately.
				var imgs = document.querySelectorAll('.pm-lazy');
				for (var i = 0; i < imgs.length; i++) {
					this.loadImage(imgs[i]);
				}
				return;
			}

			var self = this;
			this.observer = new IntersectionObserver(function (entries) {
				for (var i = 0; i < entries.length; i++) {
					if (entries[i].isIntersecting) {
						self.loadImage(entries[i].target);
						self.observer.unobserve(entries[i].target);
					}
				}
			}, { rootMargin: '200px' });

			var images = document.querySelectorAll('.pm-lazy');
			for (var j = 0; j < images.length; j++) {
				this.observer.observe(images[j]);
			}
		},

		loadImage: function (img) {
			if (img.dataset.src) {
				img.src = img.dataset.src;
			}
			if (img.dataset.srcset) {
				img.srcset = img.dataset.srcset;
			}
			img.classList.add('pm-loaded');
		},

		observe: function (img) {
			if (this.observer) {
				this.observer.observe(img);
			} else {
				this.loadImage(img);
			}
		}
	};

	/* ======================================================================
	   Filters — Handle filter interactions
	   ====================================================================== */
	var Filters = {
		container: null,
		searchTimeout: null,

		init: function (container) {
			this.container = container;
			this.bindPills();
			this.bindSearch();
			this.bindBreedSelect();
			this.bindReset();
			this.bindFavoritesToggle();
			this.updateFavoritesButton();
		},

		bindPills: function () {
			var self = this;
			var pills = this.container.querySelectorAll('.pm-filters__pills .pm-pill');
			for (var i = 0; i < pills.length; i++) {
				pills[i].addEventListener('click', function () {
					var group = this.closest('[data-filter]');
					var filterName = group ? group.dataset.filter : '';
					var value = this.dataset.value || '';

					// Toggle: clicking active pill deactivates it.
					if (this.classList.contains('is-active')) {
						this.classList.remove('is-active');
						this.removeAttribute('aria-pressed');
						Store.filters[filterName] = '';
					} else {
						var siblings = group.querySelectorAll('.pm-pill');
						for (var j = 0; j < siblings.length; j++) {
							siblings[j].classList.remove('is-active');
							siblings[j].removeAttribute('aria-pressed');
						}
						this.classList.add('is-active');
						this.setAttribute('aria-pressed', 'true');
						Store.filters[filterName] = value;
					}

					self.applyFilters();
				});
			}
		},

		bindSearch: function () {
			var self = this;
			var input = this.container.querySelector('#pm-search');
			if (!input) return;

			input.addEventListener('input', function () {
				clearTimeout(self.searchTimeout);
				self.searchTimeout = setTimeout(function () {
					Store.filters.search = input.value.trim().toLowerCase();
					self.applyFilters();
				}, 300);
			});
		},

		bindBreedSelect: function () {
			var self = this;
			var select = this.container.querySelector('#pm-breed-filter');
			if (!select) return;

			select.addEventListener('change', function () {
				Store.filters.breed = this.value;
				self.applyFilters();
			});
		},

		bindReset: function () {
			var self = this;
			var btn = this.container.querySelector('.pm-filters__reset');
			if (!btn) return;

			btn.addEventListener('click', function () {
				Store.filters = { age: '', gender: '', size: '', breed: '', search: '' };
				Store.favoritesOnly = false;

				// Reset UI.
				var activePills = self.container.querySelectorAll('.pm-pill.is-active');
				for (var i = 0; i < activePills.length; i++) {
					activePills[i].classList.remove('is-active');
					activePills[i].removeAttribute('aria-pressed');
				}

				var search = self.container.querySelector('#pm-search');
				if (search) search.value = '';

				var breed = self.container.querySelector('#pm-breed-filter');
				if (breed) breed.value = '';

				self.applyFilters();
			});
		},

		bindFavoritesToggle: function () {
			var self = this;
			var btn = this.container.querySelector('.pm-pill--favorites');
			if (!btn) return;

			btn.addEventListener('click', function () {
				Store.favoritesOnly = !Store.favoritesOnly;
				this.classList.toggle('is-active', Store.favoritesOnly);
				this.setAttribute('aria-pressed', Store.favoritesOnly ? 'true' : 'false');
				self.applyFilters();
			});
		},

		updateFavoritesButton: function () {
			var btn = this.container.querySelector('.pm-pill--favorites');
			if (!btn) return;
			var hasFavs = Favorites.getIds().length > 0;
			btn.classList.toggle('has-favorites', hasFavs);
		},

		applyFilters: function () {
			var filtered = Store.getFiltered();
			var filteredIds = filtered.map(function (p) { return p.id; });
			Cards.updateVisibility(filteredIds);
			this.updateCount(filtered.length, Store.pets.length);
			this.updateFavoritesButton();
		},

		updateCount: function (visible, total) {
			var el = this.container.querySelector('.pm-filters__count');
			if (!el) return;

			if (visible === total) {
				el.textContent = i18n.showing + ' ' + total + ' ' + i18n.cats;
			} else {
				el.textContent = i18n.showing + ' ' + visible + ' ' + i18n.of + ' ' + total + ' ' + i18n.cats;
			}
		}
	};

	/* ======================================================================
	   Cards — Manage card visibility and favorites UI
	   ====================================================================== */
	var Cards = {
		container: null,

		init: function (container) {
			this.container = container;
			this.bindCardClicks();
			this.bindFavoriteButtons();
			this.syncFavoriteState();
		},

		bindCardClicks: function () {
			var cards = this.container.querySelectorAll('.pm-card');
			for (var i = 0; i < cards.length; i++) {
				cards[i].addEventListener('click', function (e) {
					// Don't open modal if clicking favorite button.
					if (e.target.closest('.pm-card__favorite')) return;
					var petId = parseInt(this.dataset.petId, 10);
					var pet = Store.pets.find(function (p) { return p.id === petId; });
					if (pet) Modal.open(pet);
				});

				cards[i].addEventListener('keydown', function (e) {
					if (e.key === 'Enter' || e.key === ' ') {
						e.preventDefault();
						this.click();
					}
				});
			}
		},

		bindFavoriteButtons: function () {
			if (!config.showFavorites) return;

			var btns = this.container.querySelectorAll('.pm-card__favorite');
			for (var i = 0; i < btns.length; i++) {
				btns[i].addEventListener('click', function (e) {
					e.stopPropagation();
					var petId = parseInt(this.dataset.petId, 10);
					var added = Favorites.toggle(petId);
					this.classList.toggle('is-favorited', added);

					var pet = Store.pets.find(function (p) { return p.id === petId; });
					var name = pet ? pet.name : '';
					this.setAttribute('aria-label',
						added
							? (i18n.removeFavorite || 'Remove from favorites').replace('%s', name)
							: (i18n.addFavorite || 'Add to favorites').replace('%s', name)
					);

					Filters.updateFavoritesButton();
					if (Store.favoritesOnly) {
						Filters.applyFilters();
					}
				});
			}
		},

		syncFavoriteState: function () {
			if (!config.showFavorites) return;

			var btns = this.container.querySelectorAll('.pm-card__favorite');
			for (var i = 0; i < btns.length; i++) {
				var petId = parseInt(btns[i].dataset.petId, 10);
				if (Favorites.isFavorite(petId)) {
					btns[i].classList.add('is-favorited');
				}
			}
		},

		updateVisibility: function (visibleIds) {
			var cards = this.container.querySelectorAll('.pm-card');
			for (var i = 0; i < cards.length; i++) {
				var petId = parseInt(cards[i].dataset.petId, 10);
				cards[i].classList.toggle('is-hidden', visibleIds.indexOf(petId) === -1);
			}
		}
	};

	/* ======================================================================
	   Modal — Pet detail lightbox
	   ====================================================================== */
	var Modal = {
		element: null,
		previousFocus: null,
		currentPet: null,

		init: function (container) {
			this.element = container.querySelector('.pm-modal');
			if (!this.element) return;

			var self = this;

			// Close button.
			var closeBtn = this.element.querySelector('.pm-modal__close');
			if (closeBtn) {
				closeBtn.addEventListener('click', function () { self.close(); });
			}

			// Backdrop click.
			var backdrop = this.element.querySelector('.pm-modal__backdrop');
			if (backdrop) {
				backdrop.addEventListener('click', function () { self.close(); });
			}

			// Keyboard.
			document.addEventListener('keydown', function (e) {
				if (e.key === 'Escape' && !self.element.hidden) {
					self.close();
				}
			});

			// Share buttons.
			var shareBtns = this.element.querySelectorAll('.pm-share-btn');
			for (var i = 0; i < shareBtns.length; i++) {
				shareBtns[i].addEventListener('click', function () {
					if (self.currentPet) {
						Sharing.share(self.currentPet, this.dataset.platform);
					}
				});
			}
		},

		open: function (pet) {
			if (!this.element) return;
			this.currentPet = pet;
			this.previousFocus = document.activeElement;

			// Photo.
			var photo = this.element.querySelector('#pm-modal-photo');
			if (photo) {
				var photoUrl = (pet.photos && pet.photos.length > 0) ? pet.photos[0].large || pet.photos[0].full : pet.photo_primary;
				photo.src = photoUrl;
				photo.alt = pet.name + ', ' + pet.breed_primary;
			}

			// Thumbnails.
			var thumbsContainer = this.element.querySelector('#pm-modal-thumbs');
			if (thumbsContainer) {
				thumbsContainer.innerHTML = '';
				if (pet.photos && pet.photos.length > 1) {
					for (var i = 0; i < pet.photos.length; i++) {
						var thumb = document.createElement('img');
						thumb.className = 'pm-modal__thumb' + (i === 0 ? ' is-active' : '');
						thumb.src = pet.photos[i].small || pet.photos[i].medium;
						thumb.alt = pet.name + ' photo ' + (i + 1);
						thumb.dataset.large = pet.photos[i].large || pet.photos[i].full;
						thumb.addEventListener('click', this.handleThumbClick.bind(this));
						thumbsContainer.appendChild(thumb);
					}
				}
			}

			// Name.
			var nameEl = this.element.querySelector('#pm-modal-name');
			if (nameEl) nameEl.textContent = pet.name;

			// Breed.
			var breedEl = this.element.querySelector('#pm-modal-breed');
			if (breedEl) {
				var breed = pet.breed_primary;
				if (pet.breed_mixed && pet.breed_secondary) {
					breed += ' / ' + pet.breed_secondary;
				} else if (pet.breed_mixed) {
					breed += ' Mix';
				}
				breedEl.textContent = breed;
			}

			// Meta pills.
			var metaEl = this.element.querySelector('#pm-modal-meta');
			if (metaEl) {
				metaEl.innerHTML = '';
				var metaItems = [pet.age, pet.gender, pet.size, pet.color_primary].filter(Boolean);
				for (var m = 0; m < metaItems.length; m++) {
					var span = document.createElement('span');
					span.className = 'pm-modal__meta-item';
					span.textContent = metaItems[m];
					metaEl.appendChild(span);
				}
			}

			// Compatibility badges.
			var badgesEl = this.element.querySelector('#pm-modal-badges');
			if (badgesEl) {
				badgesEl.innerHTML = '';
				var env = pet.environment || {};
				if (env.cats === true) badgesEl.innerHTML += '<span class="pm-badge pm-badge--cats">' + (i18n.goodWithCats || 'Good with cats') + '</span>';
				if (env.dogs === true) badgesEl.innerHTML += '<span class="pm-badge pm-badge--dogs">' + (i18n.goodWithDogs || 'Good with dogs') + '</span>';
				if (env.children === true) badgesEl.innerHTML += '<span class="pm-badge pm-badge--kids">' + (i18n.goodWithKids || 'Good with children') + '</span>';
			}

			// Attributes.
			var attrsEl = this.element.querySelector('#pm-modal-attrs');
			if (attrsEl) {
				attrsEl.innerHTML = '';
				var attrs = pet.attributes || {};
				if (attrs.spayed_neutered) attrsEl.innerHTML += '<span class="pm-attr pm-attr--yes">' + (i18n.spayedNeutered || 'Spayed/Neutered') + '</span>';
				if (attrs.house_trained) attrsEl.innerHTML += '<span class="pm-attr pm-attr--yes">' + (i18n.houseTrained || 'House Trained') + '</span>';
				if (attrs.shots_current) attrsEl.innerHTML += '<span class="pm-attr pm-attr--yes">' + (i18n.shotsCurrent || 'Shots Current') + '</span>';
				if (attrs.special_needs) attrsEl.innerHTML += '<span class="pm-attr pm-attr--special">' + (i18n.specialNeeds || 'Special Needs') + '</span>';
			}

			// Tags.
			var tagsEl = this.element.querySelector('#pm-modal-tags');
			if (tagsEl) {
				tagsEl.innerHTML = '';
				if (pet.tags && pet.tags.length > 0) {
					for (var t = 0; t < pet.tags.length; t++) {
						tagsEl.innerHTML += '<span class="pm-tag">' + this.escapeHtml(pet.tags[t]) + '</span>';
					}
				}
			}

			// Description.
			var descEl = this.element.querySelector('#pm-modal-desc');
			if (descEl) {
				descEl.innerHTML = pet.description || '<em>No description available.</em>';
			}

			// Petfinder link.
			var pfLink = this.element.querySelector('#pm-modal-petfinder');
			if (pfLink && pet.url) {
				pfLink.href = pet.url;
				pfLink.style.display = '';
			} else if (pfLink) {
				pfLink.style.display = 'none';
			}

			// Show modal.
			this.element.hidden = false;
			document.body.style.overflow = 'hidden';

			// Focus the close button.
			var closeBtn = this.element.querySelector('.pm-modal__close');
			if (closeBtn) closeBtn.focus();

			// Trap focus.
			this.element.addEventListener('keydown', this.trapFocus);
		},

		close: function () {
			if (!this.element) return;
			this.element.hidden = true;
			document.body.style.overflow = '';
			this.element.removeEventListener('keydown', this.trapFocus);
			this.currentPet = null;

			if (this.previousFocus) {
				this.previousFocus.focus();
				this.previousFocus = null;
			}
		},

		handleThumbClick: function (e) {
			var thumb = e.target;
			var photo = this.element.querySelector('#pm-modal-photo');
			if (photo && thumb.dataset.large) {
				photo.src = thumb.dataset.large;
			}

			var allThumbs = this.element.querySelectorAll('.pm-modal__thumb');
			for (var i = 0; i < allThumbs.length; i++) {
				allThumbs[i].classList.remove('is-active');
			}
			thumb.classList.add('is-active');
		},

		trapFocus: function (e) {
			if (e.key !== 'Tab') return;
			var modal = e.currentTarget;
			var focusable = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
			if (focusable.length === 0) return;

			var first = focusable[0];
			var last = focusable[focusable.length - 1];

			if (e.shiftKey && document.activeElement === first) {
				e.preventDefault();
				last.focus();
			} else if (!e.shiftKey && document.activeElement === last) {
				e.preventDefault();
				first.focus();
			}
		},

		escapeHtml: function (str) {
			var div = document.createElement('div');
			div.textContent = str;
			return div.innerHTML;
		}
	};

	/* ======================================================================
	   Sharing — Social share functionality
	   ====================================================================== */
	var Sharing = {
		share: function (pet, platform) {
			var url = pet.url || window.location.href;
			var text = 'Meet ' + pet.name + '! A ' + (pet.breed_primary || 'cat') + ' available for adoption.';
			var shareUrl = '';

			switch (platform) {
				case 'facebook':
					shareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url);
					break;
				case 'twitter':
					shareUrl = 'https://twitter.com/intent/tweet?text=' + encodeURIComponent(text) + '&url=' + encodeURIComponent(url);
					break;
				case 'email':
					shareUrl = 'mailto:?subject=' + encodeURIComponent('Check out ' + pet.name + ' for adoption!') + '&body=' + encodeURIComponent(text + '\n\n' + url);
					window.location.href = shareUrl;
					return;
			}

			if (shareUrl) {
				window.open(shareUrl, '_blank', 'width=600,height=400,menubar=no,toolbar=no');
			}
		}
	};

	/* ======================================================================
	   Bootstrap — Initialize everything on DOM ready
	   ====================================================================== */
	document.addEventListener('DOMContentLoaded', function () {
		var containers = document.querySelectorAll('.pm-container');

		for (var c = 0; c < containers.length; c++) {
			var container = containers[c];
			var dataEl = container.querySelector('[data-pm-pets]');
			if (!dataEl) continue;

			try {
				var pets = JSON.parse(dataEl.textContent);
				Store.init(pets);
			} catch (e) {
				continue;
			}

			LazyLoader.init();
			Cards.init(container);
			Filters.init(container);
			Modal.init(container);

			// Show initial count.
			Filters.updateCount(Store.pets.length, Store.pets.length);
		}
	});
})();
